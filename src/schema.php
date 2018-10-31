<?php

namespace RedMap;

abstract class Value
{
	public static function wrap ($value)
	{
		if ($value instanceof Value)
			return $value;

		return new Constant ($value);
	}

	protected function __construct ($initial, $update)
	{
		$this->initial = $initial;
		$this->update = $update;
	}

	public abstract function build_update ($column);
}

class Constant extends Value
{
	public function __construct ($value)
	{
		parent::__construct ($value, $value);
	}

	public function build_update ($column)
	{
		return Schema::MACRO_PARAM;
	}
}

class Coalesce extends Value
{
	public function __construct ($value)
	{
		parent::__construct ($value, $value);
	}

	public function build_update ($column)
	{
		return 'COALESCE(' . $column . ', ' . Schema::MACRO_PARAM . ')';
	}
}

class Increment extends Value
{
	public function __construct ($delta, $insert = null)
	{
		parent::__construct ($insert !== null ? $insert : $delta, $delta);
	}

	public function build_update ($column)
	{
		return $column . ' + ' . Schema::MACRO_PARAM;
	}
}

class Max extends Value
{
	public function __construct ($value)
	{
		parent::__construct ($value, $value);
	}

	public function build_update ($column)
	{
		return 'GREATEST(' . $column . ', ' . Schema::MACRO_PARAM . ')';
	}
}

class Min extends Value
{
	public function __construct ($value)
	{
		parent::__construct ($value, $value);
	}

	public function build_update ($column)
	{
		return 'LEAST(' . $column . ', ' . Schema::MACRO_PARAM . ')';
	}
}

class Schema
{
	const CLEAN_OPTIMIZE = 0;
	const CLEAN_TRUNCATE = 1;
	const FIELD_INTERNAL = 1;
	const FILTER_GROUP = '~';
	const FILTER_LINK = '+';
	const INGEST_COLUMN = 0;
	const INGEST_VALUE = 1;
	const INSERT_DEFAULT = 0;
	const INSERT_REPLACE = 1;
	const INSERT_UPSERT = 2;
	const LINK_IMPLICIT = 1;
	const LINK_OPTIONAL = 2;
	const MACRO_PARAM = '?';
	const MACRO_SCOPE = '@';
	const SET_INSERT = 0;
	const SET_REPLACE = 1;
	const SET_UPDATE = 2;
	const SET_UPSERT = 3;
	const SQL_BEGIN = '`';
	const SQL_END = '`';
	const SQL_NEXT = ',';
	const SQL_NOOP = 'SELECT 0';

	public function __construct ($table, $fields, $separator = '__', $links = array ())
	{
		$this->defaults = array ();

		foreach ($links as $name => &$link)
		{
			if (isset ($link[1]) && ($link[1] & self::LINK_IMPLICIT) !== 0)
				$this->defaults[$name] = array ();
		}		

		$this->fields = array ();

		foreach ($fields as $name => $field)
		{
			if ($field === null)
			{
				$expression = null;
				$flags = 0;
			}
			else if (is_string ($field))
			{
				$expression = $fields;
				$flags = 0;
			}
			else
			{
				$expression = isset ($field[1]) ? $field[1] : null;
				$flags = isset ($field[0]) ? $field[0] : 0;
			}

			$this->fields[$name] = array
			(
				$flags,
				$expression ?: self::MACRO_SCOPE . self::format_name ($name)
			);
		}

		$this->links = $links;
		$this->separator = $separator;
		$this->table = $table;
	}

	public function clean ($mode)
	{
		switch ($mode)
		{
			case self::CLEAN_OPTIMIZE:
				$procedure = 'redmap_' . uniqid ();

				return array
				(
					array
					(
						'CREATE PROCEDURE ' . self::format_name ($procedure) . '() ' .
						'BEGIN ' .
							'CASE (SELECT ENGINE FROM information_schema.TABLES where TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?) ' .
								'WHEN \'MEMORY\' THEN ' .
									'ALTER TABLE ' . self::format_name ($this->table) . ' ENGINE=MEMORY; ' .
								'ELSE ' .
									'OPTIMIZE TABLE ' . self::format_name ($this->table) . '; ' .
							'END CASE; ' .
						'END',
						array ($this->table)
					),
					array
					(
						'CALL ' . self::format_name ($procedure) . '()',
						array ()
					),
					array
					(
						'DROP PROCEDURE IF EXISTS ' . self::format_name ($procedure) . '',
						array ()
					)
				);

			case self::CLEAN_TRUNCATE:
				return array (array ('TRUNCATE TABLE ' . self::format_name ($this->table), array ()));

			default:
				throw new \Exception ("invalid mode '$mode'");
		}
	}

	public function delete ($filters = array ())
	{
		$alias = self::format_name ('_0');

		list ($condition, $params) = $this->build_condition ($filters, $alias);

		return array
		(
			'DELETE FROM ' . $alias .
			(' USING ' . self::format_name ($this->table) . ' ' . $alias) .
			($condition !== '' ? ' WHERE ' . $condition : ''),
			$params
		);
	}

	public function ingest ($assignments, $mode, $source, $filters = array (), $orders = array (), $count = null, $offset = null)
	{
		if (count ($assignments) === 0)
			return self::SQL_NOOP;

		$alias = self::format_alias (0);

		$ingest = '';
		$ingest_params = array ();

		$insert = '';

		$update = '';
		$update_params = array ();

		foreach ($assignments as $name => $assignment)
		{
			list ($type, $value) = $assignment;

			$column = $this->get_assignment ($name);
			$insert .= self::SQL_NEXT . $column;

			switch ($type)
			{
				case self::INGEST_COLUMN:
					// Make sure parent schema exist
					$fields = explode ($this->separator, $value);
					$schema = $source;

					for ($i = 0; $i + 1 < count ($fields); ++$i)
						list ($schema) = $schema->get_link ($fields[$i]);

					// Make sure field exists in target schema
					$schema->get_expression ($fields[count ($fields) - 1], '');

					// Emit unchanged reference
					$ingest .= self::SQL_NEXT . self::format_name ($value);

					if ($mode === self::INSERT_UPSERT)
						$update .= self::SQL_NEXT . $column . ' = ' . $alias . '.' . self::format_name ($value);

					break;

				case self::INGEST_VALUE:
					$value = Value::wrap ($value);

					$ingest .= self::SQL_NEXT . self::MACRO_PARAM;
					$ingest_params[] = $value->initial;

					if ($mode === self::INSERT_UPSERT)
					{
						$update .= self::SQL_NEXT . $column . ' = ' . $value->build_update ($column);
						$update_params[] = $value->update;
					}

					break;

				default:
					throw new \Exception ("invalid assignment type '$type'");
			}
		}

		list ($select, $select_params) = $source->select ($filters, $orders, $count, $offset);

		$duplicate = count ($update_params) > 0
			? ' ON DUPLICATE KEY UPDATE ' . substr ($update, strlen (self::SQL_NEXT))
			: '';

		$verb = $mode === self::INSERT_REPLACE
			? 'REPLACE'
			: 'INSERT';

		return array
		(
			$verb . ' INTO ' . self::format_name ($this->table) .
			' (' . substr ($insert, strlen (self::SQL_NEXT)) . ')' .
			' SELECT ' . substr ($ingest, strlen (self::SQL_NEXT)) . ' FROM (' . $select . ') ' . $alias .
			$duplicate,
			array_merge ($ingest_params, $select_params, $update_params)
		);
	}

	public function insert ($assignments, $mode = self::INSERT_DEFAULT)
	{
		if (count ($assignments) === 0)
			return self::SQL_NOOP;

		$insert = '';
		$insert_params = array ();

		$update = '';
		$update_params = array ();

		foreach ($assignments as $name => $value)
		{
			$column = $this->get_assignment ($name);
			$value = Value::wrap ($value);

			$insert .= self::SQL_NEXT . $column;
			$insert_params[] = $value->initial;

			if ($mode === self::INSERT_UPSERT)
			{
				$update .= self::SQL_NEXT . $column . ' = ' . $value->build_update ($column);
				$update_params[] = $value->update;
			}
		}

		$duplicate = count ($update_params) > 0
			? ' ON DUPLICATE KEY UPDATE ' . substr ($update, strlen (self::SQL_NEXT))
			: '';

		$verb = $mode === self::INSERT_REPLACE
			? 'REPLACE'
			: 'INSERT';

		return array
		(
			$verb . ' INTO ' . self::format_name ($this->table) .
			' (' . substr ($insert, strlen (self::SQL_NEXT)) . ')' .
			' VALUES (' . implode (self::SQL_NEXT, array_fill (0, count ($insert_params), self::MACRO_PARAM)) . ')' .
			$duplicate,
			array_merge ($insert_params, $update_params)
		);
	}

	public function select ($filters = array (), $orders = array (), $count = null, $offset = null)
	{
		// Build columns list from links to other schemas for "select" clause
		$aliases = array ();
		$unique = 1;

		$alias = self::format_alias ($unique++);

		list ($select, $relation, $relation_params, $condition, $condition_params) = $this->build_filter ($filters, $alias, ' WHERE ', '', '', $aliases, $unique);

		$params = array_merge ($relation_params, $condition_params);

		// Build filtering, ordering and pagination for "order by" and "limit" clauses
		$pagination = '';
		$sort = $this->build_sort ($orders, $aliases, $alias);

		if ($sort !== '')
			$pagination .= ' ORDER BY ' . $sort;

		if ($count !== null)
		{
			$pagination .= ' LIMIT ' . self::MACRO_PARAM . self::SQL_NEXT . self::MACRO_PARAM;
			$params[] = $offset !== null ? (int)$offset : 0;
			$params[] = (int)$count;
		}

		// Build statement
		return array
		(
			'SELECT ' . $this->build_select ($alias, '') . $select .
			' FROM ' . self::format_name ($this->table) . ' ' . $alias .
			$relation . $condition . $pagination,
			$params
		);
	}

	public function update ($assignments, $filters)
	{
		if (count ($assignments) === 0)
			return self::SQL_NOOP;

		// Build update statement for requested fields from current table
		$update = '';
		$update_params = array ();

		foreach ($assignments as $name => $value)
		{
			$column = $this->get_assignment ($name);
			$value = Value::wrap ($value);

			$update .= self::SQL_NEXT . $column . ' = ' . $value->build_update ($column);
			$update_params[] = $value->update;
		}

		// Build conditions and relations to other tables
		$aliases = array ();
		$unique = 0;

		$current = self::format_alias ($unique++);

		list ($select, $relation, $relation_params, $condition, $condition_params) = $this->build_filter ($filters, $current, ' WHERE ', '', '', $aliases, $unique);

		// Build query with parameters
		return array
		(
			'UPDATE ' . self::format_name ($this->table) . ' ' . $current .
			$relation .
			' SET ' . substr ($update, strlen (self::SQL_NEXT)) .
			$condition,
			array_merge ($relation_params, $update_params, $condition_params)
		);
	}

	private function build_condition ($filters, $source)
	{
		static $comparers;
		static $logicals;

		if (!isset ($comparers))
		{
			$comparers = array
			(
				'eq'	=> array ('', ' = ' . self::MACRO_PARAM),
				'ge'	=> array ('', ' >= ' . self::MACRO_PARAM),
				'gt'	=> array ('', ' > ' . self::MACRO_PARAM),
				'in'	=> array ('', ' IN ' . self::MACRO_PARAM),
				'is'	=> array ('', ' IS ' . self::MACRO_PARAM),
				'le'	=> array ('', ' <= ' . self::MACRO_PARAM),
				'like'	=> array ('', ' LIKE ' . self::MACRO_PARAM),
				'lt'	=> array ('', ' < ' . self::MACRO_PARAM),
				'm'		=> array ('MATCH (', ') AGAINST (' . self::MACRO_PARAM . ')'),
				'mb'	=> array ('MATCH (', ') AGAINST (' . self::MACRO_PARAM . ' IN BOOLEAN MODE)'),
				'ne'	=> array ('', ' != ' . self::MACRO_PARAM),
				'not'	=> array ('', ' IS NOT ' . self::MACRO_PARAM)
			);
		}

		if (!isset ($logicals))
			$logicals = array ('and' => 'AND', 'or' => 'OR');

		if (isset ($filters[self::FILTER_GROUP]) && isset ($logicals[$filters[self::FILTER_GROUP]]))
			$logical = ' ' . $logicals[$filters[self::FILTER_GROUP]] . ' ';
		else
			$logical = ' AND ';

		// Build conditions from given filters
		$condition = '';
		$params = array ();
		$separator = false;

		foreach ($filters as $name => $value)
		{
			if ($name === self::FILTER_GROUP || $name === self::FILTER_LINK)
				continue;

			// Append separator after first filter
			if ($separator)
				$condition .= $logical;

			$separator = true;

			// Complex sub-condition group
			if (is_array ($value) && is_numeric ($name))
			{
				list ($group_condition, $group_params) = $this->build_condition ($value, $source);

				if ($group_condition !== '')
				{
					$condition .= '(' . $group_condition . ')';
					$params = array_merge ($params, $group_params);
				}
			}

			// Simple field condition
			else
			{
				// Match name with custom comparison operator, e.g. "datetime|ge"
				if (preg_match ('/^(.*)\|([a-z]{1,4})$/', $name, $match) && isset ($comparers[$match[2]]))
				{
					list ($lhs, $rhs) = $comparers[$match[2]];

					$name = $match[1];
				}

				// Default to equality for non-null values
				else if ($value !== null)
					list ($lhs, $rhs) = $comparers['eq'];

				// Default to "is" operator otherwise
				else
					list ($lhs, $rhs) = $comparers['is'];

				// Build field condition
				$condition .= $lhs . $this->get_expression ($name, $source) . $rhs;
				$params[] = $value;
			}
		}

		return array ($condition, $params);
	}

	private function build_filter ($filters, $alias, $begin, $end, $prefix, &$aliases, &$unique)
	{
		if ($filters !== null)
		{
			list ($condition, $condition_params) = $this->build_condition ($filters, $alias);

			if ($condition !== '')
			{
				$condition = $begin . $condition . $end;
				$begin = ' AND (';
				$end = ')';
			}
		}
		else
		{
			$condition = '';
			$condition_params = array ();
		}

		$links = isset ($filters[self::FILTER_LINK]) ? $filters[self::FILTER_LINK] + $this->defaults : $this->defaults;
		$relation = '';
		$relation_params = array ();
		$select = '';

		foreach ($links as $name => $children)
		{
			list ($link_schema, $link_flags, $link_relations) = $this->get_link ($name);

			$link_alias = self::format_alias ($unique++);

			// Build fields selection and join to foreign table
			$namespace = $prefix . $name . $this->separator;

			if (($link_flags & self::LINK_OPTIONAL) === 0)
				$type = 'INNER';
			else
				$type = 'LEFT';

			$relation .= ' ' . $type . ' JOIN (' . self::format_name ($link_schema->table) . ' ' . $link_alias;
			$select .= self::SQL_NEXT . $link_schema->build_select ($link_alias, $namespace);

			// Resolve relation connections
			$connect_relation = ') ON ';
			$connect_relation_params = array ();
			$logical = '';

			foreach ($link_relations as $parent_name => $foreign_name)
			{
				$foreign_column = $link_schema->get_expression ($foreign_name, $link_alias);

				// Connection depends on field from parent schema
				if (isset ($this->fields[$parent_name]))
					$parent_column = $this->get_expression ($parent_name, $alias);

				// Connection depends on manually provided value
				else
				{
					if ($children === null || !isset ($children[$parent_name]))
						throw new \Exception ("relation from $this->table to $link_schema->table.$foreign_name through link '$name' depends on unspecified value '$parent_name'");

					$connect_relation_params[] = $children[$parent_name];
					$parent_column = self::MACRO_PARAM;

					unset ($children[$parent_name]);
				}

				$connect_relation .= $logical . $foreign_column . ' = ' . $parent_column;
				$logical = ' AND ';
			}

			// Recursively merge nested fields and tables
			$link_aliases = array ();

			list ($inner_select, $inner_relation, $inner_relation_params, $inner_condition, $inner_condition_params) = $link_schema->build_filter ($children, $link_alias, $begin, $end, $namespace, $link_aliases, $unique);

			if ($inner_condition !== '')
			{
				$begin = ' AND (';
				$end = ')';
			}

			$condition .= $inner_condition;
			$condition_params = array_merge ($condition_params, $inner_condition_params);
			$relation .= $inner_relation . $connect_relation;
			$relation_params = array_merge ($relation_params, $inner_relation_params, $connect_relation_params);
			$select .= $inner_select;

			$aliases[$name] = array ($link_alias, $link_aliases);
		}

		return array ($select, $relation, $relation_params, $condition, $condition_params);
	}

	private function build_select ($source, $namespace)
	{
		$query = '';

		foreach ($this->fields as $name => $field)
		{
			if (($field[0] & self::FIELD_INTERNAL) !== 0)
				continue;

			$query .= self::SQL_NEXT . $this->get_expression ($name, $source) . ' ' . self::format_name ($namespace . $name);
		}

		return (string)substr ($query, strlen (self::SQL_NEXT));
	}

	private function build_sort ($orders, $aliases, $source)
	{
		$query = '';

		// Build ordering rules on linked tables
		if (isset ($orders[self::FILTER_LINK]))
		{
			foreach ($orders[self::FILTER_LINK] as $name => $link_orders)
			{
				list ($link_schema, $link_flags, $link_relations) = $this->get_link ($name);

				if (!isset ($aliases[$name]))
					throw new \Exception ("can't order by fields from non-linked schema '$this->table.$name'");

				list ($link_alias, $link_aliases) = $aliases[$name];

				$query .= self::SQL_NEXT . $link_schema->build_sort ($link_orders, $link_aliases, $link_alias);
			}
		}

		// Build ordering rules on columns
		foreach ($orders as $name => $ascending)
		{
			if ($name === self::FILTER_LINK)
				continue;

			$query .= self::SQL_NEXT . $this->get_expression ($name, $source) . ($ascending ? '' : ' DESC');
		}

		return (string)substr ($query, strlen (self::SQL_NEXT));
	}

	/*
	** Get assignable column from given field name.
	** $name:	field name
	** return:	(SQL fragment, true if field is primary)
	*/
	private function get_assignment ($name)
	{
		static $pattern;

		if (!isset ($pattern))
			$pattern = '/^[[:blank:]]*' . preg_quote (self::MACRO_SCOPE, '/') . '[[:blank:]]*(?:' . preg_quote (self::SQL_BEGIN, '/') . ')?([0-9A-Za-z_]+)(?:' . preg_quote (self::SQL_END, '/') . ')?[[:blank:]]*$/';

		if (!isset ($this->fields[$name]))
			throw new \Exception ("can't assign to unknown field '$this->table.$name'");

		$field = $this->fields[$name];

		if (!preg_match ($pattern, $field[1], $match))
			throw new \Exception ("can't assign to read-only field '$this->table.$name'");

		return self::format_name ($match[1]);
	}

	/*
	** Get selectable expression from given field name.
	** $name:	field name
	** $source:	source table alias
	** return:	SQL fragment
	*/
	private function get_expression ($name, $source)
	{
		if (!isset ($this->fields[$name]))
			throw new \Exception ("cannot reference unknown field '$this->table.$name'");

		return str_replace (self::MACRO_SCOPE, $source . '.', $this->fields[$name][1]);
	}

	/*
	** Get linked schema by name.
	** $name:	link name
	** return:	(schema, flags, relations)
	*/
	private function get_link ($name)
	{
		if (!isset ($this->links[$name]))
			throw new \Exception ("can't link unknown relation '$name' to schema '$this->table'");

		$link = $this->links[$name];

		return array (is_callable ($link[0]) ? $link[0] () : $link[0], $link[1], $link[2]);
	}

	private static function format_alias ($suffix)
	{
		return self::format_name ('_' . $suffix);
	}

	private static function format_name ($name)
	{
		return self::SQL_BEGIN . $name . self::SQL_END;
	}
}

?>

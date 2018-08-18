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
	const COPY_EXPRESSION = 0;
	const COPY_FIELD = 1;
	const COPY_VALUE = 2;
	const FIELD_INTERNAL = 1;
	const FIELD_PRIMARY = 2;
	const FILTER_GROUP = '~';
	const FILTER_LINK = '+';
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

	public function __construct ($table, $fields, $separator = '__', $links = array ())
	{
		$this->defaults = array ();

		foreach ($fields as $name => &$field)
		{
			if ($field === null)
				$field = array (0, self::MACRO_SCOPE . self::SQL_BEGIN . $name . self::SQL_END);
			else if (is_string ($field))
				$field = array (0, $field);
			else if (!isset ($field[1]))
				$field[1] = self::MACRO_SCOPE . self::SQL_BEGIN . $name . self::SQL_END;
		}

		foreach ($links as $name => &$link)
		{
			if (isset ($link[1]) && ($link[1] & self::LINK_IMPLICIT) !== 0)
				$this->defaults[$name] = array ();
		}

		$this->fields = $fields;
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
						'CREATE PROCEDURE ' . self::SQL_BEGIN . $procedure . self::SQL_END . '() ' .
						'BEGIN ' .
							'CASE (SELECT ENGINE FROM information_schema.TABLES where TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?) ' .
								'WHEN \'MEMORY\' THEN ' .
									'ALTER TABLE ' . self::SQL_BEGIN . $this->table . self::SQL_END . ' ENGINE=MEMORY; ' .
								'ELSE ' .
									'OPTIMIZE TABLE ' . self::SQL_BEGIN . $this->table . self::SQL_END . '; ' .
							'END CASE; ' .
						'END',
						array ($this->table)
					),
					array
					(
						'CALL ' . self::SQL_BEGIN . $procedure . self::SQL_END . '()',
						array ()
					),
					array
					(
						'DROP PROCEDURE IF EXISTS ' . self::SQL_BEGIN . $procedure . self::SQL_END . '',
						array ()
					)
				);

			case self::CLEAN_TRUNCATE:
				return array (array ('TRUNCATE TABLE ' . self::SQL_BEGIN . $this->table . self::SQL_END, array ()));

			default:
				throw new \Exception ("invalid mode '$mode'");
		}
	}

	public function copy ($mode, $pairs, $source, $filters = array (), $orders = array (), $count = null, $offset = null)
	{
		$alias = self::SQL_BEGIN . '_s' . self::SQL_END;
		$scope = $alias . '.';

		// Extract target fields from input pairs
		$indices = array ();
		$params = array ();
		$selects = array ();
		$values = array ();

		foreach ($pairs as $name => $origin)
		{
			list ($column, $primary) = $this->get_assignment ($name);

			if ($origin[0] === self::COPY_EXPRESSION)
				$select = str_replace (self::MACRO_SCOPE, $scope, $origin[1]);
			else if ($origin[0] === self::COPY_FIELD)
			{
				$select = $source->get_column ($origin[1], $alias);

				if ($select === null)
					throw new \Exception ("can't copy from unknown field '$source->table.$origin[1]'");
			}
			else
			{
				$value = Value::wrap ($origin[1]);

				if ($primary)
					$indices[$column] = $value->initial;
				else
					$values[$column] = $value;

				continue;
			}

			if ($primary)
				$indices[$column] = $select;
			else
				$selects[$column] = $select;			
		}

		// Generate copy query for requested mode
		switch ($mode)
		{
			case self::SET_INSERT:
			case self::SET_REPLACE:
			case self::SET_UPSERT:
				list ($source_query, $source_params) = $source->get ($filters, $orders, $count, $offset);

				foreach ($values as $column => $value)
					$params[] = $value->initial;

				$params = array_merge ($params, $source_params);
				$update = '';

				if ($mode === self::SET_UPSERT && count ($selects) + count ($values) > 0)
				{
					foreach ($selects as $column => $select)
						$update .= self::SQL_NEXT . $column . ' = ' . $select;

					foreach ($values as $column => $value)
					{
						$params[] = $value->update;
						$update .= self::SQL_NEXT . $column . ' = ' . $value->build_update ($column);
					}

					$update = ' ON DUPLICATE KEY UPDATE ' . substr ($update, strlen (self::SQL_NEXT));
				}

				$columns = array_merge (array_keys ($indices), array_keys ($selects), array_keys ($values));
				$values = array_merge ($indices, $selects, array_fill (0, count ($values), self::MACRO_PARAM));

				return array
				(
					($mode === self::SET_REPLACE ? 'REPLACE' : 'INSERT') . ' INTO ' . self::SQL_BEGIN . $this->table . self::SQL_END .
					' (' . implode (self::SQL_NEXT, $columns) . ')' .
					' SELECT ' . implode (self::SQL_NEXT, $values) .
					' FROM (' . $source_query . ') ' . $alias .
					$update,
					$params
				);

			default:
				throw new \Exception ("invalid mode '$mode'");
		}
	}

	public function delete ($filters)
	{
		$alias = self::SQL_BEGIN . '_0' . self::SQL_END;

		list ($condition, $params) = $this->build_condition ($filters, $alias, ' WHERE ', '');

		return array
		(
			'DELETE FROM ' . $alias . ' ' .
			'USING ' . self::SQL_BEGIN . $this->table . self::SQL_END . ' ' . $alias . $condition,
			$params
		);
	}

	public function get ($filters = array (), $orders = array (), $count = null, $offset = null)
	{
		// Select columns from links to other schemas
		$alias = self::SQL_BEGIN . '_0' . self::SQL_END;
		$aliases = array ();
		$unique = 1;

		list ($select, $relation, $relation_params, $condition, $condition_params) = $this->build_filter ($filters, $alias, ' WHERE ', '', '', $aliases, $unique);

		// Build "where", "order by" and "limit" clauses
		$params = array_merge ($relation_params, $condition_params);
		$sort = $this->build_sort ($orders, $aliases, $alias);

		if ($count !== null)
		{
			$params[] = $offset !== null ? (int)$offset : 0;
			$params[] = (int)$count;
		}

		return array
		(
			'SELECT ' . $this->build_select ($alias, '') . $select .
			' FROM ' . self::SQL_BEGIN . $this->table . self::SQL_END . ' ' . $alias .
			$relation . $condition .
			($sort
				? ' ORDER BY ' . $sort
				: '') .
			($count
				? ' LIMIT ' . self::MACRO_PARAM . self::SQL_NEXT . self::MACRO_PARAM
				: ''),
			$params
		);
	}

	public function set ($mode, $pairs)
	{
		// Extract primary values (indices) and mutable values (changes)
		$changes = array ();
		$indices = array ();

		foreach ($pairs as $name => $value)
		{
			list ($column, $primary) = $this->get_assignment ($name);

			if ($primary)
				$indices[$column] = $value;
			else
				$changes[$column] = $value;
		}

		// Generate set query for requested mode
		switch ($mode)
		{
			case self::SET_INSERT:
			case self::SET_REPLACE:
			case self::SET_UPSERT:
				$params = array ();
				$update = '';

				foreach ($changes as $column => $value)
					$params[] = Value::wrap ($value)->initial;

				foreach ($indices as $column => $value)
					$params[] = $value;

				if ($mode === self::SET_UPSERT && count ($changes) > 0)
				{
					foreach ($changes as $column => $value)
					{
						$value = Value::wrap ($value);

						$params[] = $value->update;
						$update .= self::SQL_NEXT . $column . ' = ' . $value->build_update ($column);
					}

					$update = ' ON DUPLICATE KEY UPDATE ' . substr ($update, strlen (self::SQL_NEXT));
				}

				$columns = array_merge (array_keys ($changes), array_keys ($indices));

				return array
				(
					($mode === self::SET_REPLACE ? 'REPLACE' : 'INSERT') . ' INTO ' . self::SQL_BEGIN . $this->table . self::SQL_END .
					' (' . implode (self::SQL_NEXT, $columns) . ')' .
					' VALUES (' . implode (self::SQL_NEXT, array_fill (0, count ($columns), self::MACRO_PARAM)) . ')' .
					$update,
					$params
				);

			case self::SET_UPDATE:
				if (count ($changes) === 0)
					break;

				$params = array ();
				$update = '';
				$where = '';

				foreach ($changes as $column => $change)
				{
					$value = Value::wrap ($change);

					$params[] = $value->update;
					$update .= self::SQL_NEXT . $column . ' = ' . $value->build_update ($column);
				}

				foreach ($indices as $column => $value)
				{
					if ($where !== '')
						$where .= ' AND ';
					else
						$where .= ' WHERE ';

					$params[] = $value;
					$where .= $column . ' = ' . self::MACRO_PARAM;
				}

				return array
				(
					'UPDATE ' . self::SQL_BEGIN . $this->table . self::SQL_END .
					' SET ' . substr ($update, strlen (self::SQL_NEXT)) .
					$where,
					$params
				);

			default:
				throw new \Exception ("invalid mode '$mode'");
		}
	}

	private function build_condition ($filters, $alias, $begin, $end)
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

		$condition = '';
		$params = array ();
		$pattern = '/^(.*)\|([a-z]{1,4})$/';
		$separator = false;

		foreach ($filters as $name => $value)
		{
			if ($name === self::FILTER_GROUP || $name === self::FILTER_LINK)
				continue;

			// Complex sub-condition group
			if (is_array ($value) && is_numeric ($name))
			{
				list ($append_condition, $append_params) = $this->build_condition ($value, $alias, '(', ')');

				$params = array_merge ($params, $append_params);
			}

			// Simple field condition
			else
			{
				// Match name with custom comparison operator, e.g. "datetime|ge"
				if (preg_match ($pattern, $name, $match) && isset ($comparers[$match[2]]))
				{
					$comparer = $comparers[$match[2]];
					$name = $match[1];
				}

				// Default to equality for non-null values
				else if ($value !== null)
					$comparer = $comparers['eq'];

				// Default to "is" operator otherwise
				else
					$comparer = $comparers['is'];

				// Build field condition
				$column = $this->get_column ($name, $alias);

				if ($column === null)
					throw new \Exception ("can't filter on unknown field '$this->table.$name'");

				$append_condition = $comparer[0] . $column . $comparer[1];
				$params[] = $value;
			}

			// Append to full condition
			if ($separator)
				$condition .= $logical;

			$condition .= $append_condition;
			$separator = true;
		}

		if ($separator)
			return array ($begin . $condition . $end, $params);

		return array ('', array ());
	}

	private function build_filter ($filters, $alias, $begin, $end, $prefix, &$aliases, &$unique)
	{
		if ($filters !== null)
		{
			list ($condition, $condition_params) = $this->build_condition ($filters, $alias, $begin, $end);

			if ($condition !== '')
			{
				$begin = ' AND (';
				$end = ')';
			}
		}
		else
		{
			$condition = '';
			$condition_params = array ();
		}

		$link = isset ($filters[self::FILTER_LINK]) ? $filters[self::FILTER_LINK] + $this->defaults : $this->defaults;
		$relation = '';
		$relation_params = array ();
		$select = '';

		foreach ($link as $name => $children)
		{
			list ($link_schema, $link_flags, $link_relations) = $this->get_link ($name);

			$link_alias = self::SQL_BEGIN . '_' . $unique++ . self::SQL_END;

			// Build fields selection and join to foreign table
			$namespace = $prefix . $name . $this->separator;

			if (($link_flags & self::LINK_OPTIONAL) === 0)
				$type = 'INNER';
			else
				$type = 'LEFT';

			$relation .= ' ' . $type . ' JOIN (' . self::SQL_BEGIN . $link_schema->table . self::SQL_END . ' ' . $link_alias;
			$select .= self::SQL_NEXT . $link_schema->build_select ($link_alias, $namespace);

			// Resolve relation connections
			$connect_relation = ') ON ';
			$connect_relation_params = array ();
			$logical = '';

			foreach ($link_relations as $parent_name => $foreign_name)
			{
				$foreign_column = $link_schema->get_column ($foreign_name, $link_alias);

				if ($foreign_column === null)
					throw new \Exception ("can't link unknown field $link_schema->table.$foreign_name to $this->table.$parent_name for relation '$name'");

				$parent_column = $this->get_column ($parent_name, $alias);

				if ($parent_column === null)
				{
					if ($children === null || !isset ($children[$parent_name]))
						throw new \Exception ("can't link missing value '$parent_name' to $link_schema->table.$foreign_name for relation '$name' in schema $this->table");

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

	private function build_select ($alias, $namespace)
	{
		$columns = '';
		$scope = $alias . '.';

		foreach ($this->fields as $name => $field)
		{
			if (($field[0] & self::FIELD_INTERNAL) !== 0)
				continue;

			$columns .= self::SQL_NEXT . str_replace (self::MACRO_SCOPE, $scope, $field[1]) . ' ' . self::SQL_BEGIN . $namespace . $name . self::SQL_END;
		}

		return (string)substr ($columns, strlen (self::SQL_NEXT));
	}

	private function build_sort ($orders, $aliases, $alias)
	{
		$sort = '';

		// Build ordering rules on linked tables
		if (isset ($orders[self::FILTER_LINK]))
		{
			foreach ($orders[self::FILTER_LINK] as $name => $link_orders)
			{
				list ($link_schema, $link_flags, $link_relations) = $this->get_link ($name);

				if (!isset ($aliases[$name]))
					throw new \Exception ("can't order by fields from non-linked schema '$this->table.$name'");

				list ($link_alias, $link_aliases) = $aliases[$name];

				$sort .= self::SQL_NEXT . $link_schema->build_sort ($link_orders, $link_aliases, $link_alias);
			}
		}

		// Build ordering rules on columns
		foreach ($orders as $name => $ascending)
		{
			if ($name === self::FILTER_LINK)
				continue;

			$column = $this->get_column ($name, $alias);

			if ($column === null)
				throw new \Exception ("can't order by unknown field '$this->table.$name'");

			$sort .= self::SQL_NEXT . $column . ($ascending ? '' : ' DESC');
		}

		return (string)substr ($sort, strlen (self::SQL_NEXT));
	}

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

		return array (self::SQL_BEGIN . $match[1] . self::SQL_END, ($field[0] & self::FIELD_PRIMARY) !== 0);
	}

	private function get_column ($name, $alias)
	{
		if (!isset ($this->fields[$name]))
			return null;

		return str_replace (self::MACRO_SCOPE, $alias . '.', $this->fields[$name][1]);
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
}

?>

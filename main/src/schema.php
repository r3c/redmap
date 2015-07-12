<?php

namespace RedMap;

class Increment
{
	public function __construct ($delta)
	{
		$this->delta = $delta;
	}
}

class Schema
{
	const COPY_FIELD = 0;
	const COPY_VALUE = 1;
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

	public function clean ()
	{
		return 'OPTIMIZE TABLE ' . self::SQL_BEGIN . $this->table . self::SQL_END;
	}

	public function copy ($mode, $pairs, $source, $filters = array (), $orders = array (), $count = null, $offset = null)
	{
		// Extract source and target fields from both schemas
		$changes = array ();
		$indices = array ();

		foreach ($pairs as $name => $origin)
		{
			list ($column, $primary) = $this->get_assignment ($name);

			if ($primary)
				$indices[$column] = $origin;
			else
				$changes[$column] = $origin;
		}

		// Generate copy query for requested mode
		$alias = '_copy';

		switch ($mode)
		{
			case self::SET_INSERT:
			case self::SET_REPLACE:
				$insert = '';
				$params = array ();
				$select = '';

				foreach (array_merge ($changes, $indices) as $column => $origin)
				{
					$insert .= self::SQL_NEXT . $column;
					$select .= self::SQL_NEXT;

					if ($origin[0] === self::COPY_FIELD)
					{
						$source_column = $source->get_column ($origin[1], $alias);

						if ($source_column === null)
							throw new \Exception ("can't copy from '$source->table.$origin[1]', field doesn't exist");

						$select .= $source_column;
					}
					else
					{
						$params[] = $origin[1];
						$select .= self::MACRO_PARAM;
					}
				}

				list ($source_query, $source_params) = $source->get ($filters, $orders, $count, $offset);

				return array
				(
					($mode === self::SET_INSERT ? 'INSERT' : 'REPLACE') . ' INTO ' . self::SQL_BEGIN . $this->table . self::SQL_END .
					' (' . substr ($insert, strlen (self::SQL_NEXT)) . ')' .
					' SELECT ' . substr ($select, strlen (self::SQL_NEXT)) . ' FROM (' . $source_query . ') ' . $alias,
					array_merge ($params, $source_params)
				);

				break;

			default:
				throw new \Exception ("invalid mode '$mode'");
		}
	}	

	public function delete ($filters)
	{
		$alias = '_0';

		list ($condition, $params) = $this->build_condition ($filters, $alias, ' WHERE ', '');

		return array
		(
			'DELETE FROM ' . self::SQL_BEGIN . $alias . self::SQL_END . ' ' .
			'USING ' . self::SQL_BEGIN . $this->table . self::SQL_END . ' ' . self::SQL_BEGIN . $alias . self::SQL_END . $condition,
			$params
		);
	}

	public function get ($filters = array (), $orders = array (), $count = null, $offset = null)
	{
		// Select columns from links to other schemas
		$alias = '_0';
		$unique = 1;

		list ($select, $relation, $relation_params, $condition, $condition_params) = $this->build_filter ($filters, $alias, ' WHERE ', '', '', $unique);

		// Build "where", "order by" and "limit" clauses
		$params = array_merge ($relation_params, $condition_params);
		$sort = $this->build_sort ($orders);

		if ($count !== null)
		{
			$params[] = $offset !== null ? (int)$offset : 0;
			$params[] = (int)$count;

			$range = ' LIMIT ' . self::MACRO_PARAM . self::SQL_NEXT . self::MACRO_PARAM;
		}
		else
			$range = '';

		return array
		(
			'SELECT ' . $this->build_select ($alias, '') . $select .
			' FROM ' . self::SQL_BEGIN . $this->table . self::SQL_END . ' ' . self::SQL_BEGIN . $alias . self::SQL_END .
			$relation . $condition . $sort . $range,
			$params
		);
	}

	public function set ($mode, $pairs)
	{
		// Extract primary values (indices) and changeable values (changes)
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
				$insert = '';
				$params = array ();

				foreach (array_merge ($changes, $indices) as $column => $value)
				{
					$insert .= self::SQL_NEXT . $column;
					$params[] = $value;
				}

				return array
				(
					($mode === self::SET_INSERT ? 'INSERT' : 'REPLACE') . ' INTO ' . self::SQL_BEGIN . $this->table . self::SQL_END .
					' (' . substr ($insert, strlen (self::SQL_NEXT)) . ')' .
					' VALUES (' . implode (self::SQL_NEXT, array_fill (0, count ($params), self::MACRO_PARAM)) . ')',
					$params
				);

			case self::SET_UPDATE:
				if (count ($changes) === 0)
					break;

				$update = '';
				$params = array ();

				foreach ($changes as $column => $value)
				{
					$update .= self::SQL_NEXT . $column . ' = ';

					if ($value instanceof Increment)
					{
						$update .= $column . ' + ' . self::MACRO_PARAM;
						$value = $value->delta;
					}
					else
						$update .= self::MACRO_PARAM;

					$params[] = $value;
				}

				$condition = '';

				foreach ($indices as $column => $value)
				{
					if ($condition !== '')
						$condition .= ' AND ';
					else
						$condition .= ' WHERE ';

					$condition .= $column . ' = ' . self::MACRO_PARAM;
					$params[] = $value;
				}

				return array
				(
					'UPDATE ' . self::SQL_BEGIN . $this->table . self::SQL_END .
					' SET ' . substr ($update, strlen (self::SQL_NEXT)) .
					$condition,
					$params
				);

			case self::SET_UPSERT:
				if (count ($changes) === 0)
					break;

				$insert = '';
				$insert_values = array ();
				$update = '';
				$update_values = array ();

				foreach ($changes as $column => $value)
				{
					$insert .= self::SQL_NEXT . $column;
					$insert_values[] = $value;
					$update .= self::SQL_NEXT . $column . ' = ' . self::MACRO_PARAM;
					$update_values[] = $value;
				}

				foreach ($indices as $column => $value)
				{
					$insert .= self::SQL_NEXT . $column;
					$insert_values[] = $value;
				}

				return array
				(
					'INSERT INTO ' . self::SQL_BEGIN . $this->table . self::SQL_END .
					' (' . substr ($insert, strlen (self::SQL_NEXT)) . ')' .
					' VALUES (' . implode (self::SQL_NEXT, array_fill (0, count ($insert_values), self::MACRO_PARAM)) . ')' .
					' ON DUPLICATE KEY UPDATE ' . substr ($update, strlen (self::SQL_NEXT)),
					array_merge ($insert_values, $update_values)
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
		$pattern = '/^(.*)\|([a-z]{2,4})$/';
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
					throw new \Exception ("no valid field '$name' to filter on in schema '$this->table'");

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

	private function build_filter ($filters, $alias, $begin, $end, $prefix, &$unique)
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
			if (!isset ($this->links[$name]))
				throw new \Exception ("missing link '$name' in schema '$this->table'");

			$foreign = $this->links[$name];
			$foreign_schema = is_callable ($foreign[0]) ? $foreign[0] () : $foreign[0];
			$foreign_alias = '_' . $unique++;

			// Build fields selection and join to foreign table
			$namespace = $prefix . $name . $this->separator;

			if (($foreign[1] & self::LINK_OPTIONAL) === 0)
				$type = 'INNER';
			else
				$type = 'LEFT';

			$relation .= ' ' . $type . ' JOIN (' .
				self::SQL_BEGIN . $foreign_schema->table . self::SQL_END . ' ' .
				self::SQL_BEGIN . $foreign_alias . self::SQL_END;

			$select .= self::SQL_NEXT . $foreign_schema->build_select ($foreign_alias, $namespace);

			// Resolve relation connections
			$connect_relation = ') ON ';
			$connect_relation_params = array ();
			$logical = '';

			foreach ($foreign[2] as $parent_name => $foreign_name)
			{
				$foreign_column = $foreign_schema->get_column ($foreign_name, $foreign_alias);

				if ($foreign_column === null)
					throw new \Exception ("can't map missing field '$foreign_name' in schema '$foreign_schema->table' to '$parent_name' for link '$name' in schema '$this->table'");

				$parent_column = $this->get_column ($parent_name, $alias);

				if ($parent_column === null)
				{
					if ($children === null || !isset ($children[$parent_name]))
						throw new \Exception ("can't map missing value '$parent_name' to '$foreign_name' in schema '$foreign_schema->table' for link '$name' in schema '$this->table'");

					$connect_relation_params[] = $children[$parent_name];
					$parent_column = self::MACRO_PARAM;

					unset ($children[$parent_name]);
				}

				$connect_relation .= $logical . $foreign_column . ' = ' . $parent_column;
				$logical = ' AND ';
			}

			// Recursively merge nested fields and tables
			list ($inner_select, $inner_relation, $inner_relation_params, $inner_condition, $inner_condition_params) = $foreign_schema->build_filter ($children, $foreign_alias, $begin, $end, $namespace, $unique);

			$condition .= $inner_condition;
			$condition_params = array_merge ($condition_params, $inner_condition_params);
			$relation .= $inner_relation . $connect_relation;
			$relation_params = array_merge ($relation_params, $inner_relation_params, $connect_relation_params);
			$select .= $inner_select;
		}

		return array ($select, $relation, $relation_params, $condition, $condition_params);
	}

	private function build_select ($alias, $namespace)
	{
		$columns = '';
		$scope = self::SQL_BEGIN . $alias . self::SQL_END . '.';

		foreach ($this->fields as $name => $field)
		{
			if (($field[0] & self::FIELD_INTERNAL) !== 0)
				continue;

			$columns .= self::SQL_NEXT . str_replace (self::MACRO_SCOPE, $scope, $field[1]) . ' ' . self::SQL_BEGIN . $namespace . $name . self::SQL_END;
		}

		return substr ($columns, strlen (self::SQL_NEXT));
	}

	private function build_sort ($orders)
	{
		$sort = '';

		foreach ($orders as $name => $asc)
		{
			$column = $this->get_column ($name, '_0');

			if ($column === null)
				throw new \Exception ("no valid field '$name' to order by in schema '$this->table'");

			$sort .= ($sort === '' ? ' ORDER BY ' : self::SQL_NEXT) . $column . ($asc ? '' : ' DESC');
		}

		return $sort;
	}

	private function get_assignment ($name)
	{
		static $pattern;

		if (!isset ($pattern))
			$pattern = '/^[[:blank:]]*' . preg_quote (self::MACRO_SCOPE, '/') . '[[:blank:]]*' . preg_quote (self::SQL_BEGIN, '/') . '?([0-9A-Za-z_]+)' . preg_quote (self::SQL_END, '/') . '?[[:blank:]]*$/';

		if (!isset ($this->fields[$name]))
			throw new \Exception ("no valid field '$name' to update on in schema '$this->table'");

		$field = $this->fields[$name];

		if (!preg_match ($pattern, $field[1], $match))
			throw new \Exception ("can't assign value to field '$name' in schema '$this->table'");

		return array (self::SQL_BEGIN . $match[1] . self::SQL_END, ($field[0] & self::FIELD_PRIMARY) !== 0);
	}

	private function get_column ($name, $alias)
	{
		if (!isset ($this->fields[$name]))
			return null;

		return str_replace (self::MACRO_SCOPE, self::SQL_BEGIN . $alias . self::SQL_END . '.', $this->fields[$name][1]);
	}
}

?>

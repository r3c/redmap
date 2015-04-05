<?php

namespace RedMap;

class Schema
{
	const ESCAPE_BEGIN = '`';
	const ESCAPE_END = '`';
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

	private static $comparers = array ('eq' => '=', 'ge' => '>=', 'gt' => '>', 'in' => 'IN', 'is' => 'IS', 'le' => '<=', 'like' => 'LIKE', 'lt' => '<', 'ne' => '!=');
	private static $logicals = array ('and' => 'AND', 'or' => 'OR');

	public function __construct ($table, $fields, $separator = '__', $links = array ())
	{
		$this->defaults = array ();

		foreach ($fields as $name => &$field)
		{
			if ($field === null)
				$field = array (0, self::MACRO_SCOPE . self::ESCAPE_BEGIN . $name . self::ESCAPE_END);
			else if (is_string ($field))
				$field = array (0, $field);
			else if (!isset ($field[1]))
				$field[1] = self::MACRO_SCOPE . self::ESCAPE_BEGIN . $name . self::ESCAPE_END;
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
		return 'OPTIMIZE TABLE ' . self::ESCAPE_BEGIN . $this->table . self::ESCAPE_END;
	}

	public function delete ($filters)
	{
		$alias = '_0';
		$params = array ();

		$condition = $this->build_condition ($filters, $alias, ' WHERE ', '', $params);

		return array
		(
			'DELETE FROM ' . self::ESCAPE_BEGIN . $alias . self::ESCAPE_END . ' ' .
			'USING ' . self::ESCAPE_BEGIN . $this->table . self::ESCAPE_END . ' ' . self::ESCAPE_BEGIN . $alias . self::ESCAPE_END . $condition,
			$params
		);
	}

	public function get ($filters = array (), $orders = array (), $count = null, $offset = null)
	{
		// Select columns from links to other schemas
		$alias = '_0';
		$link = isset ($filters[self::FILTER_LINK]) ? $filters[self::FILTER_LINK] : array ();
		$unique = 1;

		list ($select, $relation, $params) = $this->build_connection ($link, $alias, '', $unique);

		// Build "where", "order by" and "limit" clauses
		$condition = $this->build_condition ($filters, $alias, ' WHERE ', '', $params);
		$sort = $this->build_sort ($orders);

		if ($count !== null)
		{
			$params[] = $offset !== null ? (int)$offset : 0;
			$params[] = (int)$count;

			$range = ' LIMIT ' . self::MACRO_PARAM . ', ' . self::MACRO_PARAM;
		}
		else
			$range = '';

		return array
		(
			'SELECT ' . $this->build_select ($alias, '') . $select .
			' FROM ' . self::ESCAPE_BEGIN . $this->table . self::ESCAPE_END . ' ' . self::ESCAPE_BEGIN . $alias . self::ESCAPE_END .
			$relation . $condition . $sort . $range,
			$params
		);
	}

	public function set ($mode, $pairs)
	{
		$changes = array ();
		$indices = array ();

		// Extract primary values (indices) and changeable values (changes)
		foreach ($pairs as $name => $value)
		{
			if (!isset ($this->fields[$name]))
				throw new \Exception ("no valid field '$name' to update on in schema '$this->table'");

			$field = $this->fields[$name];

			list ($column, $value) = $this->get_assignment ($name, $field, $value);

			if (($field[0] & self::FIELD_PRIMARY) === 0)
				$changes[$column] = $value;
			else
				$indices[$column] = $value;
		}

		// Generate set query for requested mode
		switch ($mode)
		{
			case self::SET_INSERT:
			case self::SET_REPLACE:
				$insert = '';
				$values = array ();

				foreach (array_merge ($changes, $indices) as $column => $value)
				{
					$insert .= ', ' . self::ESCAPE_BEGIN . $column . self::ESCAPE_END;
					$values[] = $value;
				}

				return array
				(
					($mode === self::SET_INSERT ? 'INSERT' : 'REPLACE') . ' INTO ' . self::ESCAPE_BEGIN . $this->table . self::ESCAPE_END .
					' (' . substr ($insert, 2) . ')' .
					' VALUES (' . implode (', ', array_fill (0, count ($values), self::MACRO_PARAM)) . ')',
					$values
				);

			case self::SET_UPDATE:
				if (count ($changes) === 0)
					break;

				$update = '';
				$values = array ();

				foreach ($changes as $column => $value)
				{
					$update .= ', ' . self::ESCAPE_BEGIN . $column . self::ESCAPE_END . ' = ' . self::MACRO_PARAM;
					$values[] = $value;
				}

				$condition = '';

				foreach ($indices as $column => $value)
				{
					if ($condition !== '')
						$condition .= ' AND ';
					else
						$condition .= ' WHERE ';

					$condition .= self::ESCAPE_BEGIN . $column . self::ESCAPE_END . ' = ' . self::MACRO_PARAM;
					$values[] = $value;
				}

				return array
				(
					'UPDATE ' . self::ESCAPE_BEGIN . $this->table . self::ESCAPE_END .
					' SET ' . substr ($update, 2) .
					$condition,
					$values
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
					$insert .= ', ' . self::ESCAPE_BEGIN . $column . self::ESCAPE_END;
					$insert_values[] = $value;
					$update .= ', ' . self::ESCAPE_BEGIN . $column . self::ESCAPE_END . ' = ' . self::MACRO_PARAM;
					$update_values[] = $value;
				}

				foreach ($indices as $column => $value)
				{
					$insert .= ', ' . self::ESCAPE_BEGIN . $column . self::ESCAPE_END;
					$insert_values[] = $value;
				}

				return array
				(
					'INSERT INTO ' . self::ESCAPE_BEGIN . $this->table . self::ESCAPE_END .
					' (' . substr ($insert, 2) . ')' .
					' VALUES (' . implode (', ', array_fill (0, count ($insert_values), self::MACRO_PARAM)) . ')' .
					' ON DUPLICATE KEY UPDATE ' . substr ($update, 2),
					array_merge ($insert_values, $update_values)
				);
		}

		return array ('SELECT NULL', array ());
	}

	private function build_condition ($filters, $alias, $begin, $end, &$params)
	{
		if (isset ($filters[self::FILTER_GROUP]) && isset (self::$logicals[$filters[self::FILTER_GROUP]]))
			$logical = ' ' . self::$logicals[$filters[self::FILTER_GROUP]] . ' ';
		else
			$logical = ' AND ';

		$condition = '';
		$pattern = '/^(.*)\|([a-z]{2,4})$/';
		$separator = false;

		foreach ($filters as $name => $value)
		{
			if ($name === self::FILTER_GROUP || $name === self::FILTER_LINK)
				continue;

			// Complex sub-condition group
			if (is_array ($value) && is_numeric ($name))
				$append = $this->build_condition ($value, $alias, '(', ')', $params);

			// Simple field condition
			else
			{
				// Match name with custom comparison operator, e.g. "datetime|ge"
				if (preg_match ($pattern, $name, $match) && isset (self::$comparers[$match[2]]))
				{
					$comparer = self::$comparers[$match[2]];
					$name = $match[1];
				}

				// Use equality by default
				else
					$comparer = '=';

				// Build field condition
				$column = $this->get_value ($name, $alias);

				if ($column === null)
					throw new \Exception ("no valid field '$name' to filter on in schema '$this->table'");

				$append = $column . ' ' . $comparer . ' ' . self::MACRO_PARAM;
				$params[] = $value;
			}

			// Append to full condition
			if ($separator)
				$condition .= $logical;

			$condition .= $append;
			$separator = true;
		}

		if ($separator)
			return $begin . $condition . $end;

		return '';
	}

	private function build_connection ($link, $alias, $base, &$unique)
	{
		$params = array ();
		$relation = '';
		$select = '';

		foreach ($link + $this->defaults as $name => $children)
		{
			if (!isset ($this->links[$name]))
				throw new \Exception ("missing link '$name' in schema '$this->table'");

			$foreign = $this->links[$name];
			$foreign_schema = is_callable ($foreign[0]) ? $foreign[0] () : $foreign[0];
			$foreign_alias = '_' . $unique++;

			// Build fields selection and join to foreign table
			$namespace = $base . $name . $this->separator;

			if (($foreign[1] & self::LINK_OPTIONAL) === 0)
				$type = 'INNER';
			else
				$type = 'LEFT';

			$relation .= ' ' . $type . ' JOIN (' .
				self::ESCAPE_BEGIN . $foreign_schema->table . self::ESCAPE_END . ' ' .
				self::ESCAPE_BEGIN . $foreign_alias . self::ESCAPE_END;

			$select .= ', ' . $foreign_schema->build_select ($foreign_alias, $namespace);

			// Recursively merge nested fields and tables
			$inner_link = isset ($children[self::FILTER_LINK]) ? $children[self::FILTER_LINK] : array ();

			list ($inner_select, $inner_relation, $inner_params) = $foreign_schema->build_connection ($inner_link, $foreign_alias, $namespace, $unique);

			$params = array_merge ($params, $inner_params);
			$relation .= $inner_relation;
			$select .= $inner_select;

			// Resolve relation conditions
			$relation .= ') ON ';
			$logical = '';

			foreach ($foreign[2] as $parent_name => $foreign_name)
			{
				$foreign = $foreign_schema->get_value ($foreign_name, $foreign_alias);

				if ($foreign === null)
					throw new \Exception ("can't map missing field '$foreign_name' in schema '$foreign_schema->table' to '$parent_name' for link '$name' in schema '$this->table'");

				$parent = $this->get_value ($parent_name, $alias);

				if ($parent === null)
				{
					if ($children === null || !isset ($children[$parent_name]))
						throw new \Exception ("can't map missing value '$parent_name' to '$foreign_name' in schema '$foreign_schema->table' for link '$name' in schema '$this->table'");

					$params[] = $children[$parent_name];
					$parent = self::MACRO_PARAM;

					unset ($children[$parent_name]);
				}

				$relation .= $logical . $foreign . ' = ' . $parent;
				$logical = ' AND ';
			}

			// Append external conditions
			if ($children !== null)
				$relation .= $foreign_schema->build_condition ($children, $foreign_alias, ' AND (', ')', $params);
		}

		return array ($select, $relation, $params);
	}

	private function build_select ($alias, $namespace)
	{
		$columns = '';
		$scope = self::ESCAPE_BEGIN . $alias . self::ESCAPE_END . '.';

		foreach ($this->fields as $name => $field)
		{
			if (($field[0] & self::FIELD_INTERNAL) !== 0)
				continue;

			$columns .= ', ' . str_replace (self::MACRO_SCOPE, $scope, $field[1]) . ' ' . self::ESCAPE_BEGIN . $namespace . $name . self::ESCAPE_END;
		}

		return substr ($columns, 2);
	}

	private function build_sort ($orders)
	{
		$sort = '';

		foreach ($orders as $name => $asc)
		{
			$column = $this->get_value ($name, '_0');

			if ($column === null)
				throw new \Exception ("no valid field '$name' to order by in schema '$this->table'");

			$sort .= ($sort === '' ? ' ORDER BY ' : ', ') . $column . ($asc ? '' : ' DESC');
		}

		return $sort;
	}

	private function get_assignment ($name, $field, $value)
	{
		static $pattern;

		if (!isset ($pattern))
			$pattern = '/^[[:blank:]]*' . preg_quote (self::MACRO_SCOPE, '/') . '[[:blank:]]*' . preg_quote (self::ESCAPE_BEGIN, '/') . '?([0-9A-Za-z_]+)' . preg_quote (self::ESCAPE_END, '/') . '?[[:blank:]]*$/';

		if (!preg_match ($pattern, $field[1], $match))
			throw new \Exception ("can't assign value to field '$name' in schema '$this->table'");

		return array ($match[1], $value);
	}

	private function get_value ($name, $alias)
	{
		if (!isset ($this->fields[$name]))
			return null;

		return str_replace (self::MACRO_SCOPE, self::ESCAPE_BEGIN . $alias . self::ESCAPE_END . '.', $this->fields[$name][1]);
	}
}

?>

<?php

namespace RedMap\Engines;

class MySQLEngine implements \RedMap\Engine
{
    const FILTER_GROUP = '~';
    const FILTER_LINK = '+';
    const MACRO_PARAM = '?';
    const MACRO_SCOPE = '@';
    const SQL_BEGIN = '`';
    const SQL_END = '`';
    const SQL_NEXT = ',';

    public $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function connect()
    {
        return $this->client->connect();
    }

    public function delete($schema, $filters = null)
    {
        if ($filters === null) {
            return $this->client->execute('TRUNCATE TABLE ' . self::format_name($schema->table));
        }

        $alias = self::format_name('_0');

        list($condition, $params) = $this->build_conditions($schema, $filters, $alias);

        return $this->client->execute(
            'DELETE FROM ' . $alias .
            (' USING ' . self::format_name($schema->table) . ' ' . $alias) .
            ($condition !== '' ? ' WHERE ' . $condition : ''),
            $params
        );
    }

    public function insert($schema, $assignments = array(), $mode = self::INSERT_APPEND)
    {
        $insert = '';
        $insert_params = array();

        $update = '';
        $update_params = array();

        foreach ($assignments as $name => $value) {
            $column = $this->get_assignment($schema, $name);
            $value = \RedMap\Value::wrap($value);

            $insert .= self::SQL_NEXT . $column;
            $insert_params[] = $value->initial;

            if ($mode === self::INSERT_UPSERT) {
                $update .= self::SQL_NEXT . $column . ' = ' . $value->build_update($column, self::MACRO_PARAM);
                $update_params[] = $value->update;
            }
        }

        // Build and execute statement
        $duplicate = count($update_params) > 0
            ? ' ON DUPLICATE KEY UPDATE ' . substr($update, strlen(self::SQL_NEXT))
            : '';

        $verb = $mode === self::INSERT_REPLACE
            ? 'REPLACE'
            : 'INSERT';

        return $this->client->insert(
            $verb . ' INTO ' . self::format_name($schema->table) .
            ' (' . substr($insert, strlen(self::SQL_NEXT)) . ')' .
            ' VALUES (' . implode(self::SQL_NEXT, array_fill(0, count($insert_params), self::MACRO_PARAM)) . ')' .
            $duplicate,
            array_merge($insert_params, $update_params)
        );
    }

    public function select($schema, $filters = array(), $orders = array(), $count = null, $offset = null)
    {
        list($select, $select_params) = $this->build_select($schema, $filters, $orders, $count, $offset);

        return $this->client->select($select, $select_params);
    }

    public function source($schema, $assignments, $mode, $origin, $filters = array(), $orders = array(), $count = null, $offset = null)
    {
        if (count($assignments) === 0) {
            return true;
        }

        $alias = self::format_alias(0);

        $insert = '';

        $source = '';
        $source_params = array();

        $update = '';
        $update_params = array();

        foreach ($assignments as $name => $assignment) {
            list($type, $value) = $assignment;

            $column = $this->get_assignment($schema, $name);
            $insert .= self::SQL_NEXT . $column;

            switch ($type) {
                case self::SOURCE_COLUMN:
                    // Make sure parent schema exist
                    $fields = explode($schema->separator, $value);
                    $from = $origin;

                    for ($i = 0; $i + 1 < count($fields); ++$i) {
                        list($from) = $this->get_link($from, $fields[$i]);
                    }

                    // Make sure field exists in target schema
                    $this->get_expression($from, $fields[count($fields) - 1], '');

                    // Emit unchanged reference
                    $source .= self::SQL_NEXT . self::format_name($value);

                    if ($mode === self::INSERT_UPSERT) {
                        $update .= self::SQL_NEXT . $column . ' = ' . $alias . '.' . self::format_name($value);
                    }

                    break;

                case self::SOURCE_VALUE:
                    $value = \RedMap\Value::wrap($value);

                    $source .= self::SQL_NEXT . self::MACRO_PARAM;
                    $source_params[] = $value->initial;

                    if ($mode === self::INSERT_UPSERT) {
                        $update .= self::SQL_NEXT . $column . ' = ' . $value->build_update($column, self::MACRO_PARAM);
                        $update_params[] = $value->update;
                    }

                    break;

                default:
                    throw new \RedMap\RuntimeException("invalid assignment type '$type'");
            }
        }

        list($select, $select_params) = $this->build_select($origin, $filters, $orders, $count, $offset);

        // Build and execute statement
        $duplicate = count($update_params) > 0
            ? ' ON DUPLICATE KEY UPDATE ' . substr($update, strlen(self::SQL_NEXT))
            : '';

        $verb = $mode === self::INSERT_REPLACE
            ? 'REPLACE'
            : 'INSERT';

        return $this->client->execute(
            $verb . ' INTO ' . self::format_name($schema->table) .
            ' (' . substr($insert, strlen(self::SQL_NEXT)) . ')' .
            ' SELECT ' . substr($source, strlen(self::SQL_NEXT)) . ' FROM (' . $select . ') ' . $alias .
            $duplicate,
            array_merge($source_params, $select_params, $update_params)
        );
    }

    public function update($schema, $assignments, $filters)
    {
        if (count($assignments) === 0) {
            return true;
        }

        // Build update statement for requested fields from current table
        $update = '';
        $update_params = array();

        foreach ($assignments as $name => $value) {
            $column = $this->get_assignment($schema, $name);
            $value = \RedMap\Value::wrap($value);

            $update .= self::SQL_NEXT . $column . ' = ' . $value->build_update($column, self::MACRO_PARAM);
            $update_params[] = $value->update;
        }

        // Build conditions and relations to other tables
        $aliases = array();
        $unique = 0;

        $current = self::format_alias($unique++);

        list($select, $relation, $relation_params, $condition, $condition_params) = $this->build_filters($schema, $filters, $current, ' WHERE ', '', '', $aliases, $unique);

        // Build and execute statement
        return $this->client->execute(
            'UPDATE ' . self::format_name($schema->table) . ' ' . $current .
            $relation .
            ' SET ' . substr($update, strlen(self::SQL_NEXT)) .
            $condition,
            array_merge($relation_params, $update_params, $condition_params)
        );
    }

    public function wash($schema)
    {
        $procedure = 'redmap_' . uniqid();

        if ($this->client->execute(
            'CREATE PROCEDURE ' . self::format_name($procedure) . '() ' .
            'BEGIN ' .
                'CASE (SELECT ENGINE FROM information_schema.TABLES where TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?) ' .
                    'WHEN \'MEMORY\' THEN ' .
                        'ALTER TABLE ' . self::format_name($schema->table) . ' ENGINE=MEMORY; ' .
                    'ELSE ' .
                        'OPTIMIZE TABLE ' . self::format_name($schema->table) . '; ' .
                'END CASE; ' .
            'END',
            array($schema->table)
        ) === null) {
            return false;
        }

        $success = $this->client->execute('CALL ' . self::format_name($procedure) . '()');
        $success = $this->client->execute('DROP PROCEDURE IF EXISTS ' . self::format_name($procedure)) && $success;

        return $success;
    }

    private function build_columns($schema, $source, $namespace)
    {
        $query = '';

        foreach ($schema->fields as $name => $field) {
            if (($field[0] & \RedMap\Schema::FIELD_INTERNAL) !== 0) {
                continue;
            }

            $query .= self::SQL_NEXT . $this->get_expression($schema, $name, $source) . ' ' . self::format_name($namespace . $name);
        }

        return (string)substr($query, strlen(self::SQL_NEXT));
    }

    private function build_conditions($schema, $filters, $source)
    {
        static $comparers;
        static $logicals;

        if (!isset($comparers)) {
            $comparers = array(
                'eq'	=> array('', ' = ' . self::MACRO_PARAM),
                'ge'	=> array('', ' >= ' . self::MACRO_PARAM),
                'gt'	=> array('', ' > ' . self::MACRO_PARAM),
                'in'	=> array('', ' IN ' . self::MACRO_PARAM),
                'is'	=> array('', ' IS ' . self::MACRO_PARAM),
                'le'	=> array('', ' <= ' . self::MACRO_PARAM),
                'like'	=> array('', ' LIKE ' . self::MACRO_PARAM),
                'lt'	=> array('', ' < ' . self::MACRO_PARAM),
                'm'		=> array('MATCH (', ') AGAINST (' . self::MACRO_PARAM . ')'),
                'mb'	=> array('MATCH (', ') AGAINST (' . self::MACRO_PARAM . ' IN BOOLEAN MODE)'),
                'ne'	=> array('', ' != ' . self::MACRO_PARAM),
                'not'	=> array('', ' IS NOT ' . self::MACRO_PARAM)
            );
        }

        if (!isset($logicals)) {
            $logicals = array('and' => 'AND', 'or' => 'OR');
        }

        if (isset($filters[self::FILTER_GROUP]) && isset($logicals[$filters[self::FILTER_GROUP]])) {
            $logical = ' ' . $logicals[$filters[self::FILTER_GROUP]] . ' ';
        } else {
            $logical = ' AND ';
        }

        // Build conditions from given filters
        $condition = '';
        $params = array();
        $separator = '';

        foreach ($filters as $name => $value) {
            if ($name === self::FILTER_GROUP || $name === self::FILTER_LINK) {
                continue;
            }

            // Complex sub-condition group
            if (is_array($value) && is_numeric($name)) {
                list($group_condition, $group_params) = $this->build_conditions($schema, $value, $source);

                if ($group_condition !== '') {
                    $condition .= $separator . '(' . $group_condition . ')';
                    $params = array_merge($params, $group_params);
                }
            }

            // Simple field condition
            else {
                // Match name with custom comparison operator, e.g. "datetime|ge"
                if (preg_match('/^(.*)\|([a-z]{1,4})$/', $name, $match) && isset($comparers[$match[2]])) {
                    list($lhs, $rhs) = $comparers[$match[2]];

                    $name = $match[1];
                }

                // Default to equality for non-null values
                elseif ($value !== null) {
                    list($lhs, $rhs) = $comparers['eq'];
                }

                // Default to "is" operator otherwise
                else {
                    list($lhs, $rhs) = $comparers['is'];
                }

                // Build field condition
                $condition .= $separator . $lhs . $this->get_expression($schema, $name, $source) . $rhs;
                $params[] = $value;
            }

            // Configure separator for next filter
            $separator = $logical;
        }

        return array($condition, $params);
    }

    private function build_filters($schema, $filters, $alias, $begin, $end, $prefix, &$aliases, &$unique)
    {
        if ($filters !== null) {
            list($condition, $condition_params) = $this->build_conditions($schema, $filters, $alias);

            if ($condition !== '') {
                $condition = $begin . $condition . $end;
                $begin = ' AND (';
                $end = ')';
            }
        } else {
            $condition = '';
            $condition_params = array();
        }

        $links = isset($filters[self::FILTER_LINK]) ? $filters[self::FILTER_LINK] + $schema->defaults : $schema->defaults;
        $relation = '';
        $relation_params = array();
        $select = '';

        foreach ($links as $name => $children) {
            list($link_schema, $link_flags, $link_relations) = $this->get_link($schema, $name);

            $link_alias = self::format_alias($unique++);

            // Build fields selection and join to foreign table
            $namespace = $prefix . $name . $schema->separator;

            if (($link_flags & \RedMap\Schema::LINK_OPTIONAL) === 0) {
                $type = 'INNER';
            } else {
                $type = 'LEFT';
            }

            $relation .= ' ' . $type . ' JOIN (' . self::format_name($link_schema->table) . ' ' . $link_alias;
            $select .= self::SQL_NEXT . $this->build_columns($link_schema, $link_alias, $namespace);

            // Resolve relation connections
            $connect_relation = ') ON ';
            $connect_relation_params = array();
            $logical = '';

            foreach ($link_relations as $parent_name => $foreign_name) {
                $foreign_column = $this->get_expression($link_schema, $foreign_name, $link_alias);

                // Connection depends on field from parent schema
                if (isset($schema->fields[$parent_name])) {
                    $parent_column = $this->get_expression($schema, $parent_name, $alias);
                }

                // Connection depends on manually provided value
                else {
                    if ($children === null || !isset($children[$parent_name])) {
                        throw new \RedMap\RuntimeException("relation from $schema->table to $link_schema->table.$foreign_name through link '$name' depends on unspecified value '$parent_name'");
                    }

                    $connect_relation_params[] = $children[$parent_name];
                    $parent_column = self::MACRO_PARAM;

                    unset($children[$parent_name]);
                }

                $connect_relation .= $logical . $foreign_column . ' = ' . $parent_column;
                $logical = ' AND ';
            }

            // Recursively merge nested fields and tables
            $link_aliases = array();

            list($inner_select, $inner_relation, $inner_relation_params, $inner_condition, $inner_condition_params) = $this->build_filters($link_schema, $children, $link_alias, $begin, $end, $namespace, $link_aliases, $unique);

            if ($inner_condition !== '') {
                $begin = ' AND (';
                $end = ')';
            }

            $condition .= $inner_condition;
            $condition_params = array_merge($condition_params, $inner_condition_params);
            $relation .= $inner_relation . $connect_relation;
            $relation_params = array_merge($relation_params, $inner_relation_params, $connect_relation_params);
            $select .= $inner_select;

            $aliases[$name] = array($link_alias, $link_aliases);
        }

        return array($select, $relation, $relation_params, $condition, $condition_params);
    }

    private function build_orders($schema, $orders, $aliases, $source)
    {
        $query = '';

        // Build ordering rules on linked tables
        if (isset($orders[self::FILTER_LINK])) {
            foreach ($orders[self::FILTER_LINK] as $name => $link_orders) {
                list($link_schema, $link_flags, $link_relations) = $this->get_link($schema, $name);

                if (!isset($aliases[$name])) {
                    throw new \RedMap\RuntimeException("can't order by fields from non-linked schema '$schema->table.$name'");
                }

                list($link_alias, $link_aliases) = $aliases[$name];

                $query .= self::SQL_NEXT . $this->build_orders($link_schema, $link_orders, $link_aliases, $link_alias);
            }
        }

        // Build ordering rules on columns
        foreach ($orders as $name => $ascending) {
            if ($name === self::FILTER_LINK) {
                continue;
            }

            $query .= self::SQL_NEXT . $this->get_expression($schema, $name, $source) . ($ascending ? '' : ' DESC');
        }

        return (string)substr($query, strlen(self::SQL_NEXT));
    }

    private function build_select($schema, $filters, $orders, $count, $offset)
    {
        // Build columns list and filtering conditions from links to other schemas for "select" and "where" clauses
        $aliases = array();
        $unique = 1;

        $alias = self::format_alias($unique++);

        list($select, $relation, $relation_params, $condition, $condition_params) = $this->build_filters($schema, $filters, $alias, ' WHERE ', '', '', $aliases, $unique);

        // Build ordering for "order by" clause
        $orders = $this->build_orders($schema, $orders, $aliases, $alias);

        if ($orders !== '') {
            $ordering = ' ORDER BY ' . $orders;
        } else {
            $ordering = '';
        }

        // Build pagination for "limit" clause
        if ($count !== null) {
            $pagination = ' LIMIT ' . self::MACRO_PARAM . self::SQL_NEXT . self::MACRO_PARAM;
            $pagination_params = array($offset !== null ? (int)$offset : 0, (int)$count);
        } else {
            $pagination = '';
            $pagination_params = array();
        }

        // Build and return statement
        return array(
            'SELECT ' . $this->build_columns($schema, $alias, '') . $select .
            ' FROM ' . self::format_name($schema->table) . ' ' . $alias .
            $relation . $condition . $ordering . $pagination,
            array_merge($relation_params, $condition_params, $pagination_params)
        );
    }

    /*
    ** Get assignable column from given field name.
    ** $schema:	source schema
    ** $name:	field name
    ** return:	(SQL fragment, true if field is primary)
    */
    private function get_assignment($schema, $name)
    {
        static $pattern;

        if (!isset($pattern)) {
            $pattern = '/^[[:blank:]]*' . preg_quote(self::MACRO_SCOPE, '/') . '[[:blank:]]*(?:' . preg_quote(self::SQL_BEGIN, '/') . ')?([0-9A-Za-z_]+)(?:' . preg_quote(self::SQL_END, '/') . ')?[[:blank:]]*$/';
        }

        if (!isset($schema->fields[$name])) {
            throw new \RedMap\RuntimeException("can't assign to unknown field '$schema->table.$name'");
        }

        $expression = $schema->fields[$name][1];

        // Assume column name is field name when no expression is defined
        if ($expression === null) {
            return self::format_name($name);
        }

        // Otherwise try to match column name in expression
        if (preg_match($pattern, $expression, $match)) {
            return self::format_name($match[1]);
        }

        throw new \RedMap\RuntimeException("can't assign to read-only field '$schema->table.$name'");
    }

    /*
    ** Get selectable expression from given field name.
    ** $schema:	source schema
    ** $name:	field name
    ** $source:	source table alias
    ** return:	SQL fragment
    */
    private function get_expression($schema, $name, $source)
    {
        if (!isset($schema->fields[$name])) {
            throw new \RedMap\RuntimeException("cannot reference unknown field '$schema->table.$name'");
        }

        $expression = $schema->fields[$name][1];

        if ($expression !== null) {
            return str_replace(self::MACRO_SCOPE, $source . '.', $expression);
        }

        return $source . '.' . self::format_name($name);
    }

    /*
    ** Get linked schema by name.
    ** $schema:	source schema
    ** $name:	link name
    ** return:	(schema, flags, relations)
    */
    private function get_link($schema, $name)
    {
        if (!isset($schema->links[$name])) {
            throw new \RedMap\RuntimeException("can't link unknown relation '$name' to schema '$schema->table'");
        }

        $link = $schema->links[$name];

        return array(is_callable($link[0]) ? $link[0]() : $link[0], $link[1], $link[2]);
    }

    private static function format_alias($suffix)
    {
        return self::format_name('_' . $suffix);
    }

    private static function format_name($name)
    {
        return self::SQL_BEGIN . $name . self::SQL_END;
    }
}

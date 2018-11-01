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

	public abstract function build_update ($column, $macro);
}

class Constant extends Value
{
	public function __construct ($value)
	{
		parent::__construct ($value, $value);
	}

	public function build_update ($column, $macro)
	{
		return $macro;
	}
}

class Coalesce extends Value
{
	public function __construct ($value)
	{
		parent::__construct ($value, $value);
	}

	public function build_update ($column, $macro)
	{
		return 'COALESCE(' . $column . ', ' . $macro . ')';
	}
}

class Increment extends Value
{
	public function __construct ($delta, $insert = null)
	{
		parent::__construct ($insert !== null ? $insert : $delta, $delta);
	}

	public function build_update ($column, $macro)
	{
		return $column . ' + ' . $macro;
	}
}

class Max extends Value
{
	public function __construct ($value)
	{
		parent::__construct ($value, $value);
	}

	public function build_update ($column, $macro)
	{
		return 'GREATEST(' . $column . ', ' . $macro . ')';
	}
}

class Min extends Value
{
	public function __construct ($value)
	{
		parent::__construct ($value, $value);
	}

	public function build_update ($column, $macro)
	{
		return 'LEAST(' . $column . ', ' . $macro . ')';
	}
}

interface Client
{
	function connect ();
	function error ();
	function execute ($query, $params = array ());
	function get_first ($query, $params = array (), $default = null);
	function get_rows ($query, $params = array (), $default = null);
	function get_value ($query, $params = array (), $default = null);
	function insert ($query, $params = array ());
}

interface Database
{
	const CLEAN_OPTIMIZE = 0;
	const CLEAN_TRUNCATE = 1;
	const INGEST_COLUMN = 0;
	const INGEST_VALUE = 1;
	const INSERT_APPEND = 0;
	const INSERT_REPLACE = 1;
	const INSERT_UPSERT = 2;

	function clean ($schema, $mode);
	function delete ($schema, $filters = array ());
	function ingest ($schema, $assignments, $mode, $source, $filters = array (), $orders = array (), $count = null, $offset = null);
	function insert ($schema, $assignments, $mode = self::INSERT_APPEND);
	function select ($schema, $filters = array (), $orders = array (), $count = null, $offset = null);
	function update ($schema, $assignments, $filters);
}

class Schema
{
	const FIELD_INTERNAL = 1;
	const LINK_IMPLICIT = 1;
	const LINK_OPTIONAL = 2;

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
				$expression = $field;
				$flags = 0;
			}
			else
			{
				$expression = isset ($field[1]) ? $field[1] : null;
				$flags = isset ($field[0]) ? $field[0] : 0;
			}

			$this->fields[$name] = array ($flags, $expression);
		}

		$this->links = $links;
		$this->separator = $separator;
		$this->table = $table;
	}
}

function _create_client ($scheme, $name, $host, $port, $user, $pass, $options, $callback)
{
	$base = dirname (__FILE__);

	switch ($scheme)
	{
		case 'mysql':
			require_once ($base . '/clients/mysql.php');

			$client = new Clients\MySQLClient ($name, $host ?: '127.0.0.1', $port ?: 3306, $user ?: 'root', $pass ?: '', $callback);

			if (isset ($options['charset']))
				$client->set_charset ($options['charset']);

			return $client;

		case 'mysqli':
			require_once ($base . '/clients/mysqli.php');

			$client = new Clients\MySQLiClient ($name, $host ?: '127.0.0.1', $port ?: 3306, $user ?: 'root', $pass ?: '', $callback);

			if (isset ($options['charset']))
				$client->set_charset ($options['charset']);

			if (isset ($options['reconnect']))
				$client->set_reconnect (!!$options['reconnect']);

			return $client;

		default:
			throw new \Exception ('scheme "' . $scheme . '" is not supported');
	}
}

function _create_database ($scheme, $client)
{
	$base = dirname (__FILE__);

	switch ($scheme)
	{
		case 'mysql':
		case 'mysqli':
			require_once ($base . '/databases/mysql.php');

			return new Databases\MySQLDatabase ();

		default:
			throw new \Exception ('scheme "' . $scheme . '" is not supported');
	}
}

function create_database ($url, $callback = null)
{
	$base = dirname (__FILE__);

	// Parse query string into components
	$components = parse_url ($url);

	if ($components === false)
		throw new \Exception ('could not parse connection string');

	if (!isset ($components['host']))
		throw new \Exception ('connection string must specify a host name');

	if (!isset ($components['path']))
		throw new \Exception ('connection string must specify a database name');

	if (!isset ($components['scheme']))
		throw new \Exception ('connection string must specify a scheme');

	if (isset ($components['query']))
		parse_str ($components['query'], $options);
	else
		$options = array ();

	// Read components and convert into connection properties
	$host = $components['host'];
	$pass = isset ($components['pass']) ? $components['pass'] : null;
	$name = (string)substr ($components['path'], 1);
	$port = isset ($components['port']) ? $components['port'] : null;
	$scheme = $components['scheme'];
	$user = isset ($components['user']) ? $components['user'] : null;

	// Create and setup client & database
	$client = _create_client ($scheme, $name, $host, $port, $user, $pass, $options, $callback);
	$database = _create_database ($scheme, $client);

	return array ($client, $database);
}

?>

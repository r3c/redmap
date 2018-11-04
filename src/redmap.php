<?php

namespace RedMap;

class ConfigurationException extends \Exception
{
	public function __construct ($value, $message)
	{
		parent::__construct ("$message: \"$value\"");
	}
}

class RuntimeException extends \Exception
{
	public function __construct ($message)
	{
		parent::__construct ($message);
	}
}

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
	function execute ($query, $params = array ());
	function insert ($query, $params = array ());
	function select ($query, $params = array (), $fallback = null);
}

interface Engine
{
	const CLEAN_OPTIMIZE = 0;
	const CLEAN_TRUNCATE = 1;
	const INSERT_APPEND = 0;
	const INSERT_REPLACE = 1;
	const INSERT_UPSERT = 2;
	const SOURCE_COLUMN = 0;
	const SOURCE_VALUE = 1;

	function clean ($schema, $mode);
	function connect ();
	function delete ($schema, $filters = array ());
	function insert ($schema, $assignments = array (), $mode = self::INSERT_APPEND);
	function select ($schema, $filters = array (), $orders = array (), $count = null, $offset = null);
	function source ($schema, $assignments, $mode, $origin, $filters = array (), $orders = array (), $count = null, $offset = null);
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

function _create_client ($scheme, $name, $host, $port, $user, $pass, $query, $callback)
{
	$base = dirname (__FILE__);

	switch ($scheme)
	{
		case 'mysql':
			require_once ($base . '/clients/mysql.php');

			$options = _extract ($query, array ('charset' => null));
			$client = new Clients\MySQLClient ($name, $host ?: '127.0.0.1', $port ?: 3306, $user ?: 'root', $pass ?: '', $callback);

			if ($options['charset'] !== null)
				$client->set_charset ($options['charset']);

			return $client;

		case 'mysqli':
			require_once ($base . '/clients/mysqli.php');

			$options = _extract ($query, array ('charset' => null, 'reconnect' => '0'));
			$client = new Clients\MySQLiClient ($name, $host ?: '127.0.0.1', $port ?: 3306, $user ?: 'root', $pass ?: '', $callback);

			if ($options['charset'] !== null)
				$client->set_charset ($options['charset']);

			$client->set_reconnect ((int)$options['reconnect'] !== 0);

			return $client;

		default:
			throw new ConfigurationException ($scheme, 'unknown scheme in connection string');
	}
}

function _create_engine ($scheme, $client)
{
	$base = dirname (__FILE__);

	switch ($scheme)
	{
		case 'mysql':
		case 'mysqli':
			require_once ($base . '/engines/mysql.php');

			return new Engines\MySQLEngine ($client);

		default:
			throw new ConfigurationException ($scheme, 'unknown scheme in connection string');
	}
}

function _extract ($query, $options)
{
	$unknown = array_diff_key ($query, $options);

	if (count ($unknown) !== 0)
		throw new ConfigurationException (implode (', ', array_keys ($unknown)), 'unknown option(s) in connection string');

	foreach ($options as $key => &$value)
	{
		if (isset ($query[$key]))
			$value = $query[$key];
	}

	return $options;
}

function open ($url, $callback = null)
{
	$base = dirname (__FILE__);

	// Parse query string into components
	$components = parse_url ($url);

	if ($components === false)
		throw new ConfigurationException ($url, 'could not parse connection string');

	if (!isset ($components['host']))
		throw new ConfigurationException ($url, 'missing host name in connection string');

	if (!isset ($components['path']))
		throw new ConfigurationException ($url, 'missing database name in connection string');

	if (!isset ($components['scheme']))
		throw new ConfigurationException ($url, 'missing scheme in connection string');

	if (isset ($components['query']))
		parse_str ($components['query'], $query);
	else
		$query = array ();

	// Read components and convert into connection properties
	$host = $components['host'];
	$pass = isset ($components['pass']) ? $components['pass'] : null;
	$name = (string)substr ($components['path'], 1);
	$port = isset ($components['port']) ? $components['port'] : null;
	$scheme = $components['scheme'];
	$user = isset ($components['user']) ? $components['user'] : null;

	// Create and setup client & engine
	$client = _create_client ($scheme, $name, $host, $port, $user, $pass, $query, $callback);
	$engine = _create_engine ($scheme, $client);

	return $engine;
}

?>

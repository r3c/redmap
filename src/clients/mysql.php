<?php

namespace RedMap\Clients;

class MySQLClient implements \RedMap\Client
{
	private $callback;
	private $charset;
	private $connection;
	private $server_host;
	private $server_name;
	private $server_pass;
	private $server_port;
	private $server_user;

	public function __construct ($name, $host, $port, $user, $pass, $callback)
	{
		$this->callback = $callback;
		$this->charset = null;
		$this->connection = null;
		$this->server_host = $host;
		$this->server_user = $user;
		$this->server_pass = $pass;
		$this->server_name = $name;
		$this->server_port = $port;
	}

	public function connect ()
	{
		$this->connection = mysql_connect ($this->server_host . ':' . $this->server_port, $this->server_user, $this->server_pass);

		if ($this->connection === false || !mysql_select_db ($this->server_name, $this->connection))
			return false;

		if ($this->charset !== null)
			mysql_set_charset ($this->charset, $this->connection);

		return true;
	}

	public function error ()
	{
		return mysql_error ($this->connection);
	}

	public function execute ($query, $params = array ())
	{
		if ($this->send ($query, $params) === false)
			return null;

		return mysql_affected_rows ($this->connection);
	}

	public function insert ($query, $params = array ())
	{
		if ($this->send ($query, $params) === false)
			return null;

		return mysql_insert_id ($this->connection);
	}

	public function select ($query, $params = array (), $default = null)
	{
		$handle = $this->send ($query, $params);

		if ($handle === false)
			return $default;

		$rows = array ();

		while (($row = mysql_fetch_assoc ($handle)) !== false)
			$rows[] = $row;

		return $rows;
	}

	public function set_charset ($charset)
	{
		$this->charset = $charset;
	}

	private function escape ($value)
	{
		if ($value === null)
			return 'NULL';

		if (is_array ($value))
		{
			$array = array ();

			foreach ($value as $v)
				$array[] = $this->escape ($v);

			return '(' . (count ($array) > 0 ? implode (',', $array) : 'NULL') . ')';
		}

		if (is_bool ($value))
			return mysql_real_escape_string ((bool)$value ? 1 : 0, $this->connection);

		if (is_int ($value))
			return mysql_real_escape_string ((int)$value, $this->connection);

		if (is_float ($value))
			return mysql_real_escape_string ((double)$value, $this->connection);

		return '\'' . mysql_real_escape_string ((string)$value, $this->connection) . '\'';
	}

	private function send ($query, $params)
	{
		for ($offset = 0; ($offset = strpos ($query, '?', $offset)) !== false; $offset += strlen ($escape))
		{
			$escape = $this->escape (array_shift ($params));
			$query = substr ($query, 0, $offset) . $escape . substr ($query, $offset + 1);
		}

		$result = mysql_query ($query, $this->connection);

		if ($result === false && $this->callback !== null)
		{
			$callback = $this->callback;
			$callback ($this, $query);
		}

		return $result;
	}
}

?>

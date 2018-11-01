<?php

namespace RedMap\Clients;

class MySQLiClient implements \RedMap\Client
{
	private $server_host;
	private $server_name;
	private $server_pass;
	private $server_port;
	private $server_user;

	public function __construct ($charset, $handler = null)
	{
		\mysqli_report (MYSQLI_REPORT_OFF);

		$this->charset = $charset;
		$this->connection = null;
		$this->handler = $handler;
		$this->reconnect = false;
	}

	public function connect ($user, $pass, $name, $host = '127.0.0.1', $port = 3306)
	{
		$this->server_host = $host;
		$this->server_user = $user;
		$this->server_pass = $pass;
		$this->server_name = $name;
		$this->server_port = $port;

		return $this->reset ();
	}

	public function error ()
	{
		return $this->connection->error;
	}

	public function execute ($query, $params = array ())
	{
		$result = $this->query ($query, $params);

		if ($result === false)
			return null;

		if ($result !== true && $this->connection->more_results ())
			$this->connection->next_result ();

		return $this->connection->affected_rows >= 0 ? $this->connection->affected_rows : null;
	}

	public function get_first ($query, $params = array (), $default = null)
	{
		$result = $this->query ($query, $params);

		if ($result === false)
			return $default;

		$row = $result->fetch_assoc ();
		$result->free ();

		return $row !== null ? $row : $default;
	}

	public function get_rows ($query, $params = array (), $default = null)
	{
		$result = $this->query ($query, $params);

		if ($result === false)
			return $default;

		$rows = array ();

		while (($row = $result->fetch_assoc ()) !== null)
			$rows[] = $row;

		$result->free ();

		return $rows;
	}

	public function get_value ($query, $params = array (), $default = null)
	{
		$result = $this->query ($query, $params);

		if ($result === false)
			return $default;

		$row = $result->fetch_row ();
		$result->free ();

		if ($row !== null && count ($row) >= 1)
			return $row[0];

		return $default;
	}

	public function insert ($query, $params = array ())
	{
		$result = $this->query ($query, $params);

		if ($result === false)
			return null;

		return $this->connection->insert_id;
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
			return $this->connection->escape_string ((bool)$value ? 1 : 0);

		if (is_int ($value))
			return $this->connection->escape_string ((int)$value);

		if (is_float ($value))
			return $this->connection->escape_string ((double)$value);

		return '\'' . $this->connection->escape_string ((string)$value) . '\'';
	}

	private function query ($query, $params)
	{
		for ($offset = 0; ($offset = strpos ($query, '?', $offset)) !== false; $offset += strlen ($escape))
		{
			$escape = $this->escape (array_shift ($params));
			$query = substr ($query, 0, $offset) . $escape . substr ($query, $offset + 1);
		}

		for ($reconnect = $this->reconnect; true; $reconnect = false)
		{
			$result = @$this->connection->query ($query);

			if ($result !== false)
				break;

			if (!$reconnect || $this->connection->errno !== 2006 || !$this->reset ())
			{
				if ($this->handler !== null)
				{
					$handler = $this->handler;
					$handler ($this, $query);
				}

				break;
			}
		}

		return $result;
	}

	private function reset ()
	{
		$this->connection = new \mysqli ($this->server_host, $this->server_user, $this->server_pass, $this->server_name, $this->server_port);

		if ($this->connection->connect_errno !== 0)
			return false;

		if ($this->charset !== null)
			$this->connection->set_charset ($this->charset);

		return true;
	}
}

?>

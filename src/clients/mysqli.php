<?php

namespace RedMap\Clients;

class MySQLiClient implements \RedMap\Client
{
    private $callback;
    private $charset;
    private $connection;
    private $reconnect;
    private $server_host;
    private $server_name;
    private $server_pass;
    private $server_port;
    private $server_user;

    public function __construct(string $name, string $host, int $port, string $user, string $pass, ?callable $callback)
    {
        \mysqli_report(MYSQLI_REPORT_OFF);

        $this->callback = $callback;
        $this->charset = null;
        $this->connection = null;
        $this->reconnect = false;
        $this->server_host = $host;
        $this->server_user = $user;
        $this->server_pass = $pass;
        $this->server_name = $name;
        $this->server_port = $port;
    }

    public function connect(): bool
    {
        $this->connection = new \mysqli($this->server_host, $this->server_user, $this->server_pass, $this->server_name, $this->server_port);

        if ($this->connection->connect_errno !== 0) {
            $this->error($this->connection->connect_error, null);

            return false;
        }

        if ($this->charset !== null) {
            $this->connection->set_charset($this->charset);
        }

        return true;
    }

    public function execute(string $query, array $parameters = array()): ?int
    {
        $result = $this->query($query, $parameters);

        if ($result === false) {
            return null;
        }

        if ($result !== true && $this->connection->more_results()) {
            $this->connection->next_result();
        }

        return $this->connection->affected_rows >= 0 ? $this->connection->affected_rows : null;
    }

    public function insert(string $query, array $parameters = array()): int|string|null
    {
        $result = $this->query($query, $parameters);

        if ($result === false) {
            return null;
        }

        return $this->connection->insert_id;
    }

    public function select(string $query, array $parameters = array(), ?array $fallback = null): ?array
    {
        $result = $this->query($query, $parameters);

        if ($result === false) {
            return $fallback;
        }

        $rows = array();

        while (($row = $result->fetch_assoc()) !== null) {
            $rows[] = $row;
        }

        $result->free();

        return $rows;
    }

    public function set_charset(string $charset)
    {
        $this->charset = $charset;
    }

    public function set_reconnect(bool $reconnect)
    {
        $this->reconnect = $reconnect;
    }

    private function error(string $message, ?string $query): void
    {
        if ($this->callback !== null) {
            $callback = $this->callback;
            $callback($message, $query);
        }
    }

    private function escape($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_array($value)) {
            $array = array();

            foreach ($value as $v) {
                $array[] = $this->escape($v);
            }

            return '(' . (count($array) > 0 ? implode(',', $array) : 'NULL') . ')';
        }

        if (is_bool($value)) {
            return $this->connection->escape_string((bool)$value ? 1 : 0);
        }

        if (is_int($value)) {
            return $this->connection->escape_string((int)$value);
        }

        if (is_float($value)) {
            return $this->connection->escape_string((float)$value);
        }

        return '\'' . $this->connection->escape_string((string)$value) . '\'';
    }

    private function query(string $query, array $parameters): mixed
    {
        $offset = 0;

        while (true) {
            $offset = strpos($query, '?', $offset);

            if ($offset === false) {
                break;
            }

            $escape = $this->escape(array_shift($parameters));
            $query = substr($query, 0, $offset) . $escape . substr($query, $offset + 1);
            $offset += strlen($escape);
        }

        for ($reconnect = $this->reconnect; true; $reconnect = false) {
            $result = @$this->connection->query($query);

            if ($result !== false) {
                return $result;
            }

            if (!$reconnect || $this->connection->errno !== 2006 || !$this->connect()) {
                $this->error($this->connection->error, $query);

                return false;
            }
        }
    }
}

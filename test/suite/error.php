<?php

require_once ('helper/sql.php');

// Create engine from invalid connection strings
function test_open ($connection, $message)
{
	try
	{
		RedMap\open ($connection);

		assert (false, 'invalid connection string should raise exception');
	}
	catch (Exception $exception)
	{
		assert (strpos ($exception->getMessage (), $message) !== false, 'error message must contain "' . $message . '" but was "' . $exception->getMessage () . '"');
	}
}

test_open ('//', 'could not parse connection string');
test_open ('unsupported://localhost/redmap', 'unknown scheme');
test_open ('localhost', 'missing host name');
test_open ('mysql://localhost', 'missing database name');
test_open ('mysql://localhost/name?unknown=1', 'unknown option(s)');

// Execute invalid query
$failed = false;

$engine = sql_connect (function ($error, $query) use (&$failed)
{
	assert (strpos ($error, 'syntax') !== false);
	assert (strpos ($query, 'ERROR') !== false);

	$failed = true;
});

assert ($engine->client->execute ('ERROR') === null);
assert ($failed);

echo 'OK';

?>

<?php

require_once ('helper/sql.php');

$failed = false;

$database = sql_connect (function ($error, $query) use (&$failed)
{
	assert (strpos ($error, 'syntax') !== false);
	assert (strpos ($query, 'ERROR') !== false);

	$failed = true;
});

// Execute invalid query
assert ($database->client->execute ('ERROR') === null);
assert ($failed);

echo 'OK';

?>

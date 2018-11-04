<?php

require_once ('helper/sql.php');

$failed = false;

$engine = sql_connect (function ($error, $query) use (&$failed)
{
	assert (strpos ($error, 'syntax') !== false);
	assert (strpos ($query, 'ERROR') !== false);

	$failed = true;
});

// Execute invalid query
assert ($engine->client->execute ('ERROR') === null);
assert ($failed);

echo 'OK';

?>

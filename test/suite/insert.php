<?php

require_once ('../src/redmap.php');
require_once ('helper/sql.php');

function test_insert ($insert, $select, $expected)
{
	sql_import ('setup/insert_start.sql');
	assert ($insert () != null);
	sql_compare ($select (), $expected);
	sql_import ('setup/insert_stop.sql');
}

$message = new RedMap\Schema
(
	'message',
	array
	(
		'id'		=> null,
		'sender'	=> null,
		'recipient'	=> null,
		'text'		=> null,
		'time'		=> null
	)
);

$database = sql_connect ();

// Insert, default, constants
test_insert
(
	function () use ($database, $message) { return $database->insert ($message, array ('sender' => 3, 'recipient' => 4, 'time' => 500, 'text' => 'Hello, World!')); },
	function () use ($database, $message) { return $database->select ($message, array ('id' => 3)); },
	array (array ('id' => 3, 'sender' => 3, 'recipient' => 4, 'time' => 500, 'text' => 'Hello, World!'))
);

// Insert, default, coalesce (use value) + increment (initial) + max (use value) + min (use value)
test_insert
(
	function () use ($database, $message) { return $database->insert ($message, array ('sender' => new RedMap\Max (3), 'recipient' => new RedMap\Min (4), 'time' => new RedMap\Increment (100, 500), 'text' => new RedMap\Coalesce ('Hello, World!'))); },
	function () use ($database, $message) { return $database->select ($message, array ('id' => 3)); },
	array (array ('id' => 3, 'sender' => 3, 'recipient' => 4, 'time' => 500, 'text' => 'Hello, World!'))
);

// Insert, upsert missing, constants
test_insert
(
	function () use ($database, $message) { return $database->insert ($message, array ('id' => 3, 'sender' => 42), RedMap\Database::INSERT_UPSERT); },
	function () use ($database, $message) { return $database->select ($message, array ('id' => 3)); },
	array (array ('id' => 3, 'sender' => 42, 'recipient' => 0, 'time' => 0, 'text' => ''))
);

// Insert, upsert missing, increment (initial)
test_insert
(
	function () use ($database, $message) { return $database->insert ($message, array ('id' => 3, 'sender' => new RedMap\Increment (1, 42)), RedMap\Database::INSERT_UPSERT); },
	function () use ($database, $message) { return $database->select ($message, array ('id' => 3)); },
	array (array ('id' => 3, 'sender' => 42, 'recipient' => 0, 'time' => 0, 'text' => ''))
);

// Insert, upsert existing, constant
test_insert
(
	function () use ($database, $message) { return $database->insert ($message, array ('id' => 2, 'sender' => 53, 'text' => 'Upserted!'), RedMap\Database::INSERT_UPSERT); },
	function () use ($database, $message) { return $database->select ($message, array ('id' => 2)); },
	array (array ('id' => 2, 'sender' => 53, 'recipient' => 1, 'time' => 1000, 'text' => 'Upserted!'))
);

// Insert, upsert existing, increment (update) + max (use value) + min (keep previous)
test_insert
(
	function () use ($database, $message) { return $database->insert ($message, array ('id' => 2, 'sender' => new RedMap\Increment (1, 53), 'recipient' => new RedMap\Max (3), 'time' => new RedMap\Min (2000), 'text' => 'Upserted!'), RedMap\Database::INSERT_UPSERT); },
	function () use ($database, $message) { return $database->select ($message, array ('id' => 2)); },
	array (array ('id' => 2, 'sender' => 3, 'recipient' => 3, 'time' => 1000, 'text' => 'Upserted!'))
);

// Insert, replace missing, constant
test_insert
(
	function () use ($database, $message) { return $database->insert ($message, array ('id' => 3, 'sender' => 1, 'recipient' => 9, 'time' => 0, 'text' => 'Replaced!'), RedMap\Database::INSERT_REPLACE); },
	function () use ($database, $message) { return $database->select ($message, array ('id' => 3)); },
	array (array ('id' => 3, 'sender' => 1, 'recipient' => 9, 'text' => 'Replaced!', 'time' => 0))
);

// Insert, replace missing, coalesce (use value)
test_insert
(
	function () use ($database, $message) { return $database->insert ($message, array ('id' => 3, 'sender' => new RedMap\Coalesce (1), 'recipient' => 9, 'time' => 0, 'text' => 'Replaced!'), RedMap\Database::INSERT_REPLACE); },
	function () use ($database, $message) { return $database->select ($message, array ('id' => 3)); },
	array (array ('id' => 3, 'sender' => 1, 'recipient' => 9, 'text' => 'Replaced!', 'time' => 0))
);

// Insert, replace existing, constant
test_insert
(
	function () use ($database, $message) { return $database->insert ($message, array ('id' => 2, 'recipient' => 7), RedMap\Database::INSERT_REPLACE); },
	function () use ($database, $message) { return $database->select ($message, array ('id' => 2)); },
	array (array ('id' => 2, 'sender' => 0, 'recipient' => 7, 'text' => '', 'time' => 0))
);

// Insert, replace existing, max (use value) + min (use value)
test_insert
(
	function () use ($database, $message) { return $database->insert ($message, array ('id' => 2, 'sender' => new RedMap\Min (5), 'recipient' => new RedMap\Max (0)), RedMap\Database::INSERT_REPLACE); },
	function () use ($database, $message) { return $database->select ($message, array ('id' => 2)); },
	array (array ('id' => 2, 'sender' => 5, 'recipient' => 0, 'text' => '', 'time' => 0))
);

echo 'OK';

?>

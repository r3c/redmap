<?php

require_once ('../src/redmap.php');
require_once ('helper/sql.php');

function test_delete ($delete, $select, $expected)
{
	global $engine;

	sql_import ($engine, 'setup/delete_start.sql');
	assert ($delete () !== null);
	sql_compare ($select (), $expected);
	sql_import ($engine, 'setup/delete_stop.sql');
}

$entry = new RedMap\Schema
(
	'entry',
	array
	(
		'id'	=> null
	)
);

$engine = sql_connect ();

// Delete single entry
test_delete
(
	function () use ($engine, $entry)
	{
		return $engine->delete ($entry, array ('id' => 1));
	},
	function () use ($engine, $entry)
	{
		return $engine->select ($entry, array (), array ('id' => true));
	},
	array
	(
		array ('id' => 2)
	)
);

// Delete all entries with filter
test_delete
(
	function () use ($engine, $entry)
	{
		return $engine->delete ($entry, array ('id|ge' => 1));
	},
	function () use ($engine, $entry)
	{
		return $engine->select ($entry, array (), array ('id' => true));
	},
	array ()
);

// Delete all entries without filter
test_delete
(
	function () use ($engine, $entry)
	{
		$success = $engine->delete ($entry, array ());

		$engine->insert ($entry, array ());

		return $success;
	},
	function () use ($engine, $entry)
	{
		return $engine->select ($entry, array (), array ('id' => true));
	},
	array
	(
		array ('id' => 3)
	)
);

// Truncate all entries without filter
test_delete
(
	function () use ($engine, $entry)
	{
		$success = $engine->delete ($entry);

		$engine->insert ($entry, array ());

		return $success;
	},
	function () use ($engine, $entry)
	{
		return $engine->select ($entry, array (), array ('id' => true));
	},
	array
	(
		array ('id' => 1)
	)
);

echo 'OK';

?>

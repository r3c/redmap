<?php

require ('../../main/src/drivers/mysqli.php');
require ('../../main/src/schema.php');
require ('storage/sql.php');

$message = new RedMap\Schema
(
	'message',
	array
	(
		'id'		=> array (RedMap\Schema::FIELD_PRIMARY),
		'author'	=> null,
		'text'		=> null,
		'time'		=> null
	)
);

// Start
sql_connect ();
sql_import ('../res/set_start.sql');

// Insert
$id = sql_assert_set ($message, RedMap\Schema::SET_INSERT, array ('author' => 1, 'text' => 'Hello, World!', 'time' => 500));

sql_assert_get ($message, array ('id' => $id), array (array ('id' => (string)$id, 'author' => '1', 'text' => 'Hello, World!', 'time' => '500')));

// Update missing row
sql_assert_set ($message, RedMap\Schema::SET_UPDATE, array ('id' => $id + 1, 'author' => 42));

sql_assert_get ($message, array ('id' => $id + 1), array ());

// Update existing row
sql_assert_set ($message, RedMap\Schema::SET_UPDATE, array ('id' => $id, 'author' => 42, 'text' => 'Updated'));

sql_assert_get ($message, array ('id' => $id), array (array ('id' => (string)$id, 'author' => '42', 'text' => 'Updated', 'time' => '500')));

// Upsert existing row
sql_assert_set ($message, RedMap\Schema::SET_UPSERT, array ('id' => $id, 'author' => 17));

sql_assert_get ($message, array ('id' => $id), array (array ('id' => (string)$id, 'author' => '17', 'text' => 'Updated', 'time' => '500')));

// Upsert missing row
sql_assert_set ($message, RedMap\Schema::SET_UPSERT, array ('id' => $id + 1, 'author' => 53));

sql_assert_get ($message, array ('id' => $id + 1), array (array ('id' => (string)($id + 1), 'author' => '53', 'text' => '', 'time' => '0')));

// Replace existing row
sql_assert_set ($message, RedMap\Schema::SET_REPLACE, array ('id' => $id, 'author' => 1));

sql_assert_get ($message, array ('id' => $id), array (array ('id' => (string)$id, 'author' => '1', 'text' => '', 'time' => '0')));

// Replace missing row
sql_assert_set ($message, RedMap\Schema::SET_REPLACE, array ('id' => $id + 2, 'author' => 2));

sql_assert_get ($message, array ('id' => $id + 2), array (array ('id' => (string)($id + 2), 'author' => '2', 'text' => '', 'time' => '0')));

// Stop
sql_import ('../res/set_stop.sql');

?>

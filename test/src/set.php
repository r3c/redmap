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
		'sender'	=> null,
		'recipient'	=> null,
		'text'		=> null,
		'time'		=> null
	)
);

$myinbox = new RedMap\Schema
(
	'myinbox',
	array
	(
		'id'		=> array (RedMap\Schema::FIELD_PRIMARY),
		'message'	=> null,
		'batch'		=> null
	)
);

// Start
sql_connect ();
sql_import ('../res/set_start.sql');

// Insert
$id = sql_assert_execute ($message->set (RedMap\Schema::SET_INSERT, array ('sender' => 1, 'text' => 'Hello, World!', 'time' => 500)));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 1, 'recipient' => 0, 'text' => 'Hello, World!', 'time' => 500)));

// Update missing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPDATE, array ('id' => $id + 1, 'sender' => 42)));
sql_assert_compare ($message->get (array ('id' => $id + 1), array ('id' => true)), array ());

// Update existing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPDATE, array ('id' => $id, 'sender' => 42, 'text' => 'Updated')));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'text' => 'Updated', 'time' => 500)));

// Update with positive increment
sql_assert_execute ($message->set (RedMap\Schema::SET_UPDATE, array ('id' => $id, 'time' => new RedMap\Increment (100))));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'time' => 42, 'text' => 'Updated', 'time' => 600)));

// Update with negative increment
sql_assert_execute ($message->set (RedMap\Schema::SET_UPDATE, array ('id' => $id, 'time' => new RedMap\Increment (-200))));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'text' => 'Updated', 'time' => 400)));

// Upsert existing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPSERT, array ('id' => $id, 'sender' => 42, 'text' => 'Upserted')));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'text' => 'Upserted', 'time' => 400)));

// Upsert missing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPSERT, array ('id' => $id + 1, 'sender' => 53, 'recipient' => 8, 'text' => 'Upserted too')));
sql_assert_compare ($message->get (array ('id' => $id + 1), array ('id' => true)), array (array ('id' => $id + 1, 'sender' => 53, 'recipient' => 8, 'text' => 'Upserted too', 'time' => 0)));

// Replace existing row
sql_assert_execute ($message->set (RedMap\Schema::SET_REPLACE, array ('id' => $id, 'sender' => 1, 'recipient' => 9)));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 1, 'recipient' => 9, 'text' => '', 'time' => 0)));

// Replace missing row
sql_assert_execute ($message->set (RedMap\Schema::SET_REPLACE, array ('id' => $id + 2, 'sender' => 2, 'recipient' => 9)));
sql_assert_compare ($message->get (array ('id' => $id + 2), array ('id' => true)), array (array ('id' => $id + 2, 'sender' => 2, 'recipient' => 9, 'text' => '', 'time' => 0)));

// Stop
sql_import ('../res/set_stop.sql');

?>

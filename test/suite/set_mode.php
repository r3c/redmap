<?php

require_once ('../src/drivers/mysqli.php');
require_once ('../src/schema.php');
require_once ('helper/sql.php');

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

// Start
sql_connect ();
sql_import ('setup/set_mode_start.sql');

// Insert
$id = sql_assert_execute ($message->set (RedMap\Schema::SET_INSERT, array ('sender' => 1, 'recipient' => 0, 'time' => 500, 'text' => 'Hello, World!')));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 1, 'recipient' => 0, 'time' => 500, 'text' => 'Hello, World!')));

// Update missing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPDATE, array ('id' => $id + 1, 'sender' => 42)));
sql_assert_compare ($message->get (array ('id' => $id + 1), array ('id' => true)), array ());

// Update existing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPDATE, array ('id' => $id, 'sender' => 42, 'text' => 'Updated')));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'time' => 500, 'text' => 'Updated')));

// Upsert existing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPSERT, array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'time' => 800, 'text' => 'Upserted')));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'time' => 800, 'text' => 'Upserted')));

// Upsert missing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPSERT, array ('id' => $id + 1, 'sender' => 53, 'recipient' => 8, 'time' => 0, 'text' => 'Upserted too')));
sql_assert_compare ($message->get (array ('id' => $id + 1), array ('id' => true)), array (array ('id' => $id + 1, 'sender' => 53, 'recipient' => 8, 'time' => 0, 'text' => 'Upserted too')));

// Replace existing row
sql_assert_execute ($message->set (RedMap\Schema::SET_REPLACE, array ('id' => $id, 'sender' => 1, 'recipient' => 9, 'time' => 0, 'text' => '')));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 1, 'recipient' => 9, 'text' => '', 'time' => 0)));

// Replace missing row
sql_assert_execute ($message->set (RedMap\Schema::SET_REPLACE, array ('id' => $id + 3, 'sender' => 2, 'recipient' => 9, 'time' => 0, 'text' => '')));
sql_assert_compare ($message->get (array ('id' => $id + 3), array ('id' => true)), array (array ('id' => $id + 3, 'sender' => 2, 'recipient' => 9, 'text' => '', 'time' => 0)));

// Stop
sql_import ('setup/set_mode_stop.sql');

echo 'OK';

?>

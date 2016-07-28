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

// Start
sql_connect ();
sql_import ('../res/set_start.sql');

// Insert
$id = sql_assert_execute ($message->set (RedMap\Schema::SET_INSERT, array ('sender' => 1, 'recipient' => 0, 'time' => 500, 'text' => 'Hello, World!')));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 1, 'recipient' => 0, 'time' => 500, 'text' => 'Hello, World!')));

// Update missing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPDATE, array ('id' => $id + 1, 'sender' => 42)));
sql_assert_compare ($message->get (array ('id' => $id + 1), array ('id' => true)), array ());

// Update existing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPDATE, array ('id' => $id, 'sender' => 42, 'text' => 'Updated')));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'time' => 500, 'text' => 'Updated')));

// Update with positive increment
sql_assert_execute ($message->set (RedMap\Schema::SET_UPDATE, array ('id' => $id, 'time' => new RedMap\Increment (100))));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'time' => 42, 'time' => 600, 'text' => 'Updated')));

// Update with negative increment
sql_assert_execute ($message->set (RedMap\Schema::SET_UPDATE, array ('id' => $id, 'time' => new RedMap\Increment (-200))));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'time' => 400, 'text' => 'Updated')));

// Upsert existing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPSERT, array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'time' => 800, 'text' => 'Upserted')));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 42, 'recipient' => 0, 'time' => 800, 'text' => 'Upserted')));

// Upsert missing row
sql_assert_execute ($message->set (RedMap\Schema::SET_UPSERT, array ('id' => $id + 1, 'sender' => 53, 'recipient' => 8, 'time' => 0, 'text' => 'Upserted too')));
sql_assert_compare ($message->get (array ('id' => $id + 1), array ('id' => true)), array (array ('id' => $id + 1, 'sender' => 53, 'recipient' => 8, 'time' => 0, 'text' => 'Upserted too')));

// Upsert missing row with max
sql_assert_execute ($message->set (RedMap\Schema::SET_UPSERT, array ('id' => $id + 2, 'sender' => 17, 'recipient' => 5, 'time' => new RedMap\Max (500), 'text' => 'First')));
sql_assert_compare ($message->get (array ('id' => $id + 2), array ('id' => true)), array (array ('id' => $id + 2, 'sender' => 17, 'recipient' => 5, 'time' => 500, 'text' => 'First')));

// Upsert existing row with max (keep current value)
sql_assert_execute ($message->set (RedMap\Schema::SET_UPSERT, array ('id' => $id + 2, 'sender' => 17, 'recipient' => 5, 'time' => new RedMap\Max (200), 'text' => 'Second')));
sql_assert_compare ($message->get (array ('id' => $id + 2), array ('id' => true)), array (array ('id' => $id + 2, 'sender' => 17, 'recipient' => 5, 'time' => 500, 'text' => 'Second')));

// Upsert existing row with max (use new value)
sql_assert_execute ($message->set (RedMap\Schema::SET_UPSERT, array ('id' => $id + 2, 'sender' => 17, 'recipient' => 5, 'time' => new RedMap\Max (1000), 'text' => 'Third')));
sql_assert_compare ($message->get (array ('id' => $id + 2), array ('id' => true)), array (array ('id' => $id + 2, 'sender' => 17, 'recipient' => 5, 'time' => 1000, 'text' => 'Third')));

// Replace existing row
sql_assert_execute ($message->set (RedMap\Schema::SET_REPLACE, array ('id' => $id, 'sender' => 1, 'recipient' => 9, 'time' => 0, 'text' => '')));
sql_assert_compare ($message->get (array ('id' => $id), array ('id' => true)), array (array ('id' => $id, 'sender' => 1, 'recipient' => 9, 'text' => '', 'time' => 0)));

// Replace missing row
sql_assert_execute ($message->set (RedMap\Schema::SET_REPLACE, array ('id' => $id + 3, 'sender' => 2, 'recipient' => 9, 'time' => 0, 'text' => '')));
sql_assert_compare ($message->get (array ('id' => $id + 3), array ('id' => true)), array (array ('id' => $id + 3, 'sender' => 2, 'recipient' => 9, 'text' => '', 'time' => 0)));

// Stop
sql_import ('../res/set_stop.sql');

?>

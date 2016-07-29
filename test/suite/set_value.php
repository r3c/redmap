<?php

require_once ('../src/drivers/mysqli.php');
require_once ('../src/schema.php');
require_once ('helper/sql.php');

$schema = new RedMap\Schema
(
	'table',
	array
	(
		'id'		=> array (RedMap\Schema::FIELD_PRIMARY),
		'value'		=> null
	)
);

// Start
sql_connect ();
sql_import ('setup/set_value_start.sql');

// Increment: insert
sql_assert_execute ($schema->set (RedMap\Schema::SET_INSERT, array ('id' => 1, 'value' => new RedMap\Increment (2, 3))));
sql_assert_compare ($schema->get (array ('id' => 1), array ('id' => true)), array (array ('id' => 1, 'value' => 3)));

// Increment: upsert with positive
sql_assert_execute ($schema->set (RedMap\Schema::SET_UPSERT, array ('id' => 1, 'value' => new RedMap\Increment (100))));
sql_assert_compare ($schema->get (array ('id' => 1), array ('id' => true)), array (array ('id' => 1, 'value' => 103)));

// Increment: update with negative
sql_assert_execute ($schema->set (RedMap\Schema::SET_UPDATE, array ('id' => 1, 'value' => new RedMap\Increment (-100))));
sql_assert_compare ($schema->get (array ('id' => 1), array ('id' => true)), array (array ('id' => 1, 'value' => 3)));

// Max: upsert missing row
sql_assert_execute ($schema->set (RedMap\Schema::SET_UPSERT, array ('id' => 2, 'value' => new RedMap\Max (500))));
sql_assert_compare ($schema->get (array ('id' => 2), array ('id' => true)), array (array ('id' => 2, 'value' => 500)));

// Max: upsert existing row (keep current value)
sql_assert_execute ($schema->set (RedMap\Schema::SET_UPSERT, array ('id' => 2, 'value' => new RedMap\Max (200))));
sql_assert_compare ($schema->get (array ('id' => 2), array ('id' => true)), array (array ('id' => 2, 'value' => 500)));

// Max: upsert existing row (use new value)
sql_assert_execute ($schema->set (RedMap\Schema::SET_UPSERT, array ('id' => 2, 'value' => new RedMap\Max (1000))));
sql_assert_compare ($schema->get (array ('id' => 2), array ('id' => true)), array (array ('id' => 2, 'value' => 1000)));

// Coalesce: insert
sql_assert_execute ($schema->set (RedMap\Schema::SET_UPSERT, array ('id' => 3, 'value' => new RedMap\Coalesce (5))));
sql_assert_compare ($schema->get (array ('id' => 3), array ('id' => true)), array (array ('id' => 3, 'value' => 5)));

// Coalesce: update
sql_assert_execute ($schema->set (RedMap\Schema::SET_UPSERT, array ('id' => 3, 'value' => new RedMap\Coalesce (10))));
sql_assert_compare ($schema->get (array ('id' => 3), array ('id' => true)), array (array ('id' => 3, 'value' => 5)));

// Min: upsert missing row
sql_assert_execute ($schema->set (RedMap\Schema::SET_UPSERT, array ('id' => 4, 'value' => new RedMap\Min (200))));
sql_assert_compare ($schema->get (array ('id' => 4), array ('id' => true)), array (array ('id' => 4, 'value' => 200)));

// Min: upsert existing row (keep current value)
sql_assert_execute ($schema->set (RedMap\Schema::SET_UPSERT, array ('id' => 4, 'value' => new RedMap\Min (500))));
sql_assert_compare ($schema->get (array ('id' => 4), array ('id' => true)), array (array ('id' => 4, 'value' => 200)));

// Min: upsert existing row (use new value)
sql_assert_execute ($schema->set (RedMap\Schema::SET_UPSERT, array ('id' => 4, 'value' => new RedMap\Min (100))));
sql_assert_compare ($schema->get (array ('id' => 4), array ('id' => true)), array (array ('id' => 4, 'value' => 100)));

// Stop
sql_import ('setup/set_value_stop.sql');

echo 'OK';

?>

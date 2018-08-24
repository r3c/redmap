<?php

require_once ('../src/drivers/mysqli.php');
require_once ('../src/schema.php');
require_once ('helper/sql.php');

$source = new RedMap\Schema
(
	'source',
	array
	(
		'id'	=> array (RedMap\Schema::FIELD_PRIMARY),
		'name'	=> null
	)
);

$target = new RedMap\Schema
(
	'target',
	array
	(
		'id'		=> array (RedMap\Schema::FIELD_PRIMARY),
		'key'		=> array (RedMap\Schema::FIELD_PRIMARY),
		'name'		=> null,
		'counter'	=> null
	)
);

// Start
sql_connect ();
sql_import ('setup/copy_start.sql');

// Insert
sql_assert_execute ($target->copy
(
	RedMap\Schema::SET_INSERT,
	array
	(
		'id'		=> array (RedMap\Schema::COPY_FIELD, 'id'),
		'key'		=> array (RedMap\Schema::COPY_FIELD, 'id'),
		'name'		=> array (RedMap\Schema::COPY_FIELD, 'name'),
		'counter'	=> array (RedMap\Schema::COPY_VALUE, 0),
	),
	$source,
	array ('id|le' => 2)
));

sql_assert_compare
(
	$target->get (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'key' => 1, 'name' => 'Apple', 'counter' => 0),
		array ('id' => 2, 'key' => 2, 'name' => 'Banana', 'counter' => 0)
	)
);

// Replace
sql_assert_execute ($source->update (array ('name' => 'Ananas'), array ('id' => 1)));
sql_assert_execute ($target->copy
(
	RedMap\Schema::SET_REPLACE,
	array
	(
		'id'		=> array (RedMap\Schema::COPY_FIELD, 'id'),
		'key'		=> array (RedMap\Schema::COPY_VALUE, 0),
		'name'		=> array (RedMap\Schema::COPY_FIELD, 'name'),
		'counter'	=> array (RedMap\Schema::COPY_VALUE, 1),
	),
	$source,
	array ('id' => 1)
));

sql_assert_compare
(
	$target->get (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'key' => 0, 'name' => 'Ananas', 'counter' => 1),
		array ('id' => 2, 'key' => 2, 'name' => 'Banana', 'counter' => 0)
	)
);

// Upsert
sql_assert_execute ($target->copy
(
	RedMap\Schema::SET_UPSERT,
	array
	(
		'id'		=> array (RedMap\Schema::COPY_FIELD, 'id'),
		'key'		=> array (RedMap\Schema::COPY_VALUE, 7),
		'name'		=> array (RedMap\Schema::COPY_FIELD, 'name'),
		'counter'	=> array (RedMap\Schema::COPY_VALUE, 2),
	),
	$source,
	array ('id|le' => 3)
));

sql_assert_compare
(
	$target->get (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'key' => 0, 'name' => 'Ananas', 'counter' => 2),
		array ('id' => 2, 'key' => 2, 'name' => 'Banana', 'counter' => 2),
		array ('id' => 3, 'key' => 7, 'name' => 'Carrot', 'counter' => 2)
	)
);

// Upsert with expressions

sql_assert_execute ($target->copy
(
	RedMap\Schema::SET_UPSERT,
	array
	(
		'id'		=> array (RedMap\Schema::COPY_FIELD, 'id'),
		'key'		=> array (RedMap\Schema::COPY_VALUE, 4),
		'name'		=> array (RedMap\Schema::COPY_FIELD, 'name'),
		'counter'	=> array (RedMap\Schema::COPY_VALUE, new RedMap\Max (1)),
	),
	$source,
	array ('id' => 1)
));

sql_assert_execute ($target->copy
(
	RedMap\Schema::SET_UPSERT,
	array
	(
		'id'		=> array (RedMap\Schema::COPY_FIELD, 'id'),
		'key'		=> array (RedMap\Schema::COPY_VALUE, 3),
		'name'		=> array (RedMap\Schema::COPY_FIELD, 'name'),
		'counter'	=> array (RedMap\Schema::COPY_VALUE, new RedMap\Max (2)),
	),
	$source,
	array ('id' => 2)
));

sql_assert_execute ($target->copy
(
	RedMap\Schema::SET_UPSERT,
	array
	(
		'id'		=> array (RedMap\Schema::COPY_FIELD, 'id'),
		'key'		=> array (RedMap\Schema::COPY_VALUE, 2),
		'name'		=> array (RedMap\Schema::COPY_FIELD, 'name'),
		'counter'	=> array (RedMap\Schema::COPY_VALUE, new RedMap\Max (3)),
	),
	$source,
	array ('id' => 3)
));

sql_assert_execute ($target->copy
(
	RedMap\Schema::SET_UPSERT,
	array
	(
		'id'		=> array (RedMap\Schema::COPY_FIELD, 'id'),
		'key'		=> array (RedMap\Schema::COPY_VALUE, 1),
		'name'		=> array (RedMap\Schema::COPY_FIELD, 'name'),
		'counter'	=> array (RedMap\Schema::COPY_VALUE, new RedMap\Max (3)),
	),
	$source,
	array ('id' => 4)
));

sql_assert_compare
(
	$target->get (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'key' => 0, 'name' => 'Ananas', 'counter' => 2),
		array ('id' => 2, 'key' => 2, 'name' => 'Banana', 'counter' => 2),
		array ('id' => 3, 'key' => 7, 'name' => 'Carrot', 'counter' => 3),
		array ('id' => 4, 'key' => 1, 'name' => 'Orange', 'counter' => 3)
	)
);

// Stop
sql_import ('setup/copy_stop.sql');

echo 'OK';

?>

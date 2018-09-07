<?php

require_once ('../src/drivers/mysqli.php');
require_once ('../src/schema.php');
require_once ('helper/sql.php');

function test_ingest ($ingest, $get, $expected)
{
	sql_import ('setup/ingest_start.sql');
	sql_assert_execute ($ingest);
	sql_assert_compare ($get, $expected);
	sql_import ('setup/ingest_stop.sql');
}

$source = new RedMap\Schema
(
	'source',
	array
	(
		'id'	=> null,
		'name'	=> null
	)
);

$target = new RedMap\Schema
(
	'target',
	array
	(
		'id'		=> null,
		'key'		=> null,
		'name'		=> null,
		'counter'	=> null
	)
);

sql_connect ();

// Insert
test_ingest
(
	$target->ingest
	(
		array
		(
			'id'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'key'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'name'		=> array (RedMap\Schema::INGEST_COLUMN, 'name'),
			'counter'	=> array (RedMap\Schema::INGEST_VALUE, 0)
		),
		RedMap\Schema::INSERT_DEFAULT,
		$source,
		array ('id|le' => 2)
	),
	$target->select (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'key' => 1, 'name' => 'Apple', 'counter' => 0),
		array ('id' => 2, 'key' => 2, 'name' => 'Banana', 'counter' => 0),
		array ('id' => 3, 'key' => 17, 'name' => 'Foo', 'counter' => 17),
		array ('id' => 4, 'key' => 42, 'name' => 'Bar', 'counter' => 42)
	)
);

// Replace
test_ingest
(
	$target->ingest
	(
		array
		(
			'id'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'key'		=> array (RedMap\Schema::INGEST_VALUE, 0),
			'name'		=> array (RedMap\Schema::INGEST_COLUMN, 'name'),
			'counter'	=> array (RedMap\Schema::INGEST_VALUE, 1),
		),
		RedMap\Schema::INSERT_REPLACE,
		$source,
		array ('id|ge' => 3)
	),
	$target->select (array (), array ('id' => true)),
	array
	(
		array ('id' => 3, 'key' => 0, 'name' => 'Carrot', 'counter' => 1),
		array ('id' => 4, 'key' => 0, 'name' => 'Orange', 'counter' => 1)
	)
);

// Upsert
test_ingest
(
	$target->ingest
	(
		array
		(
			'id'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'key'		=> array (RedMap\Schema::INGEST_VALUE, 7),
			'name'		=> array (RedMap\Schema::INGEST_COLUMN, 'name'),
			'counter'	=> array (RedMap\Schema::INGEST_VALUE, 2),
		),
		RedMap\Schema::INSERT_UPSERT,
		$source,
		array ('id|le' => 3)
	),
	$target->select (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'key' => 7, 'name' => 'Apple', 'counter' => 2),
		array ('id' => 2, 'key' => 7, 'name' => 'Banana', 'counter' => 2),
		array ('id' => 3, 'key' => 7, 'name' => 'Carrot', 'counter' => 2),
		array ('id' => 4, 'key' => 42, 'name' => 'Bar', 'counter' => 42)
	)
);

// Upsert, max
test_ingest
(
	$target->ingest
	(
		array
		(
			'id'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'key'		=> array (RedMap\Schema::INGEST_VALUE, 3),
			'name'		=> array (RedMap\Schema::INGEST_COLUMN, 'name'),
			'counter'	=> array (RedMap\Schema::INGEST_VALUE, new RedMap\Max (20)),
		),
		RedMap\Schema::INSERT_UPSERT,
		$source
	),
	$target->select (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'key' => 3, 'name' => 'Apple', 'counter' => 20),
		array ('id' => 2, 'key' => 3, 'name' => 'Banana', 'counter' => 20),
		array ('id' => 3, 'key' => 3, 'name' => 'Carrot', 'counter' => 20),
		array ('id' => 4, 'key' => 3, 'name' => 'Orange', 'counter' => 42)
	)
);

echo 'OK';

?>

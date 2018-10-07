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

$category = new RedMap\Schema
(
	'category',
	array
	(
		'id'	=> null,
		'name'	=> null
	)
);

$food = new RedMap\Schema
(
	'food',
	array
	(
		'category'	=> null,
		'id'		=> null,
		'name'		=> null
	),
	'__',
	array
	(
		'category'	=> array ($category, 0, array ('category' => 'id'))
	)
);

$stock = new RedMap\Schema
(
	'stock',
	array
	(
		'id'		=> null,
		'name'		=> null,
		'price'		=> null,
		'quantity'	=> null
	)
);

sql_connect ();

// Insert
test_ingest
(
	$stock->ingest
	(
		array
		(
			'id'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'name'		=> array (RedMap\Schema::INGEST_COLUMN, 'name'),
			'price'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'quantity'	=> array (RedMap\Schema::INGEST_VALUE, 0)
		),
		RedMap\Schema::INSERT_DEFAULT,
		$food,
		array ('id|le' => 2)
	),
	$stock->select (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Apple', 'price' => 1, 'quantity' => 0),
		array ('id' => 2, 'name' => 'Banana', 'price' => 2, 'quantity' => 0),
		array ('id' => 3, 'name' => 'Foo', 'price' => 5, 'quantity' => 17),
		array ('id' => 4, 'name' => 'Bar', 'price' => 7, 'quantity' => 42)
	)
);

// Insert, child reference
test_ingest
(
	$stock->ingest
	(
		array
		(
			'id'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'name'		=> array (RedMap\Schema::INGEST_COLUMN, 'category__name'),
			'price'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'quantity'	=> array (RedMap\Schema::INGEST_VALUE, 0)
		),
		RedMap\Schema::INSERT_DEFAULT,
		$food,
		array ('id' => 1, '+' => array ('category' => null)) // FIXME [ingest-nested-implicit]: no error is raised when "category" is not linked here (and missing from selected columns)
	),
	$stock->select (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Fruit', 'price' => 1, 'quantity' => 0),
		array ('id' => 3, 'name' => 'Foo', 'price' => 5, 'quantity' => 17),
		array ('id' => 4, 'name' => 'Bar', 'price' => 7, 'quantity' => 42)
	)
);

// Replace
test_ingest
(
	$stock->ingest
	(
		array
		(
			'id'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'name'		=> array (RedMap\Schema::INGEST_COLUMN, 'name'),
			'price'		=> array (RedMap\Schema::INGEST_VALUE, 0),
			'quantity'	=> array (RedMap\Schema::INGEST_VALUE, 1),
		),
		RedMap\Schema::INSERT_REPLACE,
		$food,
		array ('id|ge' => 3)
	),
	$stock->select (array (), array ('id' => true)),
	array
	(
		array ('id' => 3, 'name' => 'Carrot', 'price' => 0, 'quantity' => 1),
		array ('id' => 4, 'name' => 'Orange', 'price' => 0, 'quantity' => 1)
	)
);

// Upsert
test_ingest
(
	$stock->ingest
	(
		array
		(
			'id'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'name'		=> array (RedMap\Schema::INGEST_COLUMN, 'name'),
			'price'		=> array (RedMap\Schema::INGEST_VALUE, 3),
			'quantity'	=> array (RedMap\Schema::INGEST_VALUE, 2),
		),
		RedMap\Schema::INSERT_UPSERT,
		$food,
		array ('id|le' => 3)
	),
	$stock->select (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Apple', 'price' => 3, 'quantity' => 2),
		array ('id' => 2, 'name' => 'Banana', 'price' => 3, 'quantity' => 2),
		array ('id' => 3, 'name' => 'Carrot', 'price' => 3, 'quantity' => 2),
		array ('id' => 4, 'name' => 'Bar', 'price' => 7, 'quantity' => 42)
	)
);

// Upsert, max
test_ingest
(
	$stock->ingest
	(
		array
		(
			'id'		=> array (RedMap\Schema::INGEST_COLUMN, 'id'),
			'name'		=> array (RedMap\Schema::INGEST_COLUMN, 'name'),
			'price'		=> array (RedMap\Schema::INGEST_VALUE, 3),
			'quantity'	=> array (RedMap\Schema::INGEST_VALUE, new RedMap\Max (20)),
		),
		RedMap\Schema::INSERT_UPSERT,
		$food
	),
	$stock->select (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Apple', 'price' => 3, 'quantity' => 20),
		array ('id' => 2, 'name' => 'Banana', 'price' => 3, 'quantity' => 20),
		array ('id' => 3, 'name' => 'Carrot', 'price' => 3, 'quantity' => 20),
		array ('id' => 4, 'name' => 'Orange', 'price' => 3, 'quantity' => 42)
	)
);

echo 'OK';

?>

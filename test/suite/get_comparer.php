<?php

require_once ('../src/drivers/mysqli.php');
require_once ('../src/schema.php');
require_once ('storage/sql.php');

$book = new RedMap\Schema
(
	'book',
	array
	(
		'id'	=> array (RedMap\Schema::FIELD_PRIMARY),
		'name'	=> null,
		'year'	=> null
	)
);

// Start
sql_connect ();
sql_import ('setup/get_comparer_start.sql');

// Get with default 'equal' comparer
sql_assert_compare
(
	$book->get (array ('id' => 1), array ('id' => true)),
	array
	(
		array ('id' => '1', 'name' => 'My First Book', 'year' => '2001')
	)
);

// Get with default 'is' comparer
sql_assert_compare
(
	$book->get (array ('year' => null), array ('id' => true)),
	array
	(
		array ('id' => '4', 'name' => 'Unknown Book', 'year' => null)
	)
);

// Greater or equal
sql_assert_compare
(
	$book->get (array ('id|ge' => 3), array ('id' => true)),
	array
	(
		array ('id' => '3', 'name' => 'A Third Book', 'year' => 2003),
		array ('id' => '4', 'name' => 'Unknown Book', 'year' => null)
	)
);

// Greater than
sql_assert_compare
(
	$book->get (array ('id|gt' => 3), array ('id' => true)),
	array
	(
		array ('id' => '4', 'name' => 'Unknown Book', 'year' => null)
	)
);

// Lower or equal
sql_assert_compare
(
	$book->get (array ('id|le' => 2), array ('id' => true)),
	array
	(
		array ('id' => '1', 'name' => 'My First Book', 'year' => 2001),
		array ('id' => '2', 'name' => 'My Second Book', 'year' => 2002)
	)
);

// Greater than
sql_assert_compare
(
	$book->get (array ('id|lt' => 2), array ('id' => true)),
	array
	(
		array ('id' => '1', 'name' => 'My First Book', 'year' => 2001)
	)
);

// Like
sql_assert_compare
(
	$book->get (array ('name|like' => 'Unknown%'), array ('id' => true)),
	array
	(
		array ('id' => '4', 'name' => 'Unknown Book', 'year' => null)
	)
);

// Match boolean
sql_assert_compare
(
	$book->get (array ('name|mb' => 'boo*'), array ('id' => true)),
	array
	(
		array ('id' => '1', 'name' => 'My First Book', 'year' => 2001),
		array ('id' => '2', 'name' => 'My Second Book', 'year' => 2002),
		array ('id' => '3', 'name' => 'A Third Book', 'year' => 2003),
		array ('id' => '4', 'name' => 'Unknown Book', 'year' => null)
	)
);

// Stop
sql_import ('setup/get_comparer_stop.sql');

echo 'OK';

?>

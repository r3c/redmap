<?php

require ('../../main/src/drivers/mysqli.php');
require ('../../main/src/schema.php');
require ('storage/sql.php');

$company = new RedMap\Schema
(
	'company',
	array
	(
		'id'	=> array (RedMap\Schema::FIELD_PRIMARY),
		'name'	=> null
	)
);

$employee = new RedMap\Schema
(
	'employee',
	array
	(
		'company'	=> array (RedMap\Schema::FIELD_INTERNAL),
		'id'		=> array (RedMap\Schema::FIELD_PRIMARY),
		'manager'	=> array (RedMap\Schema::FIELD_INTERNAL),
		'name'		=> null
	),
	'__',
	array
	(
		'company'	=> array ($company, 0, array ('company' => 'id')),
		'manager'	=> array (function () { return $GLOBALS['employee']; }, RedMap\Schema::LINK_OPTIONAL, array ('manager' => 'id'))
	)
);

// Start
sql_connect ();
sql_import ('../res/get_link_start.sql');

// Test
sql_assert_get
(
	$employee,
	array ('+' => array ('company' => null)),
	array
	(
		array ('id' => '1', 'name' => 'Alice', 'company__id' => '1', 'company__name' => 'Google'),
		array ('id' => '2', 'name' => 'Bob', 'company__id' => '1', 'company__name' => 'Google'),
		array ('id' => '3', 'name' => 'Carol', 'company__id' => '2', 'company__name' => 'Facebook'),
		array ('id' => '4', 'name' => 'Dave', 'company__id' => '2', 'company__name' => 'Facebook'),
		array ('id' => '5', 'name' => 'Eve', 'company__id' => '3', 'company__name' => 'Amazon'),
		array ('id' => '6', 'name' => 'Mallory', 'company__id' => '4', 'company__name' => 'Apple'),
	)
);

sql_assert_get
(
	$employee,
	array ('id' => 1, '+'  => array ('company' => null, 'manager' => null)),
	array
	(
		array ('id' => '1', 'name' => 'Alice', 'company__id' => '1', 'company__name' => 'Google', 'manager__id' => null, 'manager__name' => null)
	)
);

sql_assert_get
(
	$employee,
	array ('id' => 2, '+' => array ('manager' => null)),
	array
	(
		array ('id' => '2', 'name' => 'Bob', 'manager__id' => '1', 'manager__name' => 'Alice')
	)
);

sql_assert_get
(
	$employee,
	array ('+' => array ('company' => array ('name|like' => 'A%'))),
	array
	(
		array ('id' => '5', 'name' => 'Eve', 'company__id' => '3', 'company__name' => 'Amazon'),
		array ('id' => '6', 'name' => 'Mallory', 'company__id' => '4', 'company__name' => 'Apple')
	)
);

sql_assert_get
(
	$employee,
	array ('id|in' => array (1, 2), '+' => array ('manager' => array ('+' => array ('company' => null)))),
	array
	(
		array ('id' => '1', 'name' => 'Alice', 'manager__id' => null, 'manager__name' => null, 'manager__company__id' => null, 'manager__company__name' => null),
		array ('id' => '2', 'name' => 'Bob', 'manager__id' => '1', 'manager__name' => 'Alice', 'manager__company__id' => '1', 'manager__company__name' => 'Google')
	)
);

// Stop
sql_import ('../res/get_link_stop.sql');

?>

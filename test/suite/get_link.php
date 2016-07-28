<?php

require_once ('../src/drivers/mysqli.php');
require_once ('../src/schema.php');
require_once ('helper/sql.php');

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
		'manager'	=> array (function () { return $GLOBALS['employee']; }, RedMap\Schema::LINK_OPTIONAL, array ('manager' => 'id')),
		'report'	=> array (function () { return $GLOBALS['report']; }, RedMap\Schema::LINK_OPTIONAL, array ('id' => 'employee', '!day' => 'day'))
	)
);

$report = new RedMap\Schema
(
	'report',
	array
	(
		'employee'	=> array (RedMap\Schema::FIELD_PRIMARY),
		'day'		=> array (RedMap\Schema::FIELD_PRIMARY),
		'summary'	=> null
	)
);

// Start
sql_connect ();
sql_import ('setup/get_link_start.sql');

// Link with company
sql_assert_compare
(
	$employee->get (array ('+' => array ('company' => null)), array ('id' => true)),
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

// Filter by id, link with company and manager
sql_assert_compare
(
	$employee->get (array ('id' => 1, '+'  => array ('company' => null, 'manager' => null)), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Alice', 'company__id' => 1, 'company__name' => 'Google', 'manager__id' => null, 'manager__name' => null)
	)
);

// Filter by id, link with manager
sql_assert_compare
(
	$employee->get (array ('id' => 2, '+' => array ('manager' => null)), array ('id' => true)),
	array
	(
		array ('id' => 2, 'name' => 'Bob', 'manager__id' => 1, 'manager__name' => 'Alice')
	)
);

// Link with manager, filter by missing manager
sql_assert_compare
(
	$employee->get (array ('+' => array ('manager' => array ('id' => null))), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Alice', 'manager__id' => null, 'manager__name' => null),
		array ('id' => 3, 'name' => 'Carol', 'manager__id' => null, 'manager__name' => null),
		array ('id' => 5, 'name' => 'Eve', 'manager__id' => null, 'manager__name' => null),
		array ('id' => 6, 'name' => 'Mallory', 'manager__id' => null, 'manager__name' => null)
	)
);

// Link with company, filter by company name
sql_assert_compare
(
	$employee->get (array ('+' => array ('company' => array ('name|like' => 'A%'))), array ('id' => true)),
	array
	(
		array ('id' => 5, 'name' => 'Eve', 'company__id' => 3, 'company__name' => 'Amazon'),
		array ('id' => 6, 'name' => 'Mallory', 'company__id' => 4, 'company__name' => 'Apple')
	)
);

// Filter by id, link with manager and company of manager
sql_assert_compare
(
	$employee->get (array ('id|in' => array (1, 2), '+' => array ('manager' => array ('+' => array ('company' => null)))), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Alice', 'manager__id' => null, 'manager__name' => null, 'manager__company__id' => null, 'manager__company__name' => null),
		array ('id' => 2, 'name' => 'Bob', 'manager__id' => 1, 'manager__name' => 'Alice', 'manager__company__id' => 1, 'manager__company__name' => 'Google')
	)
);

// Filter by id, link with external report on day 2
sql_assert_compare
(
	$employee->get (array ('id' => 1, '+' => array ('report' => array ('!day' => 2))), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Alice', 'report__employee' => 1, 'report__day' => 2, 'report__summary' => 'Alice\'s day 2 summary')
	)
);

// Stop
sql_import ('setup/get_link_stop.sql');

echo 'OK';

?>

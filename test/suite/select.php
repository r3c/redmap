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
		'name'	=> null,
		'ipo'	=> null
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

sql_connect ();
sql_import ('setup/select_start.sql');

// Select, 1 table, default 'equal' operator
sql_assert_compare
(
	$company->select (array ('id' => 1), array ('id' => true)),
	array
	(
		array ('id' => '1', 'name' => 'Google', 'ipo' => '2004')
	)
);

// Select, 1 table, default 'is' operator
sql_assert_compare
(
	$company->select (array ('ipo' => null), array ('id' => true)),
	array
	(
		array ('id' => '4', 'name' => 'Apple', 'ipo' => null)
	)
);

// Select, 1 table, greater or equal operator
sql_assert_compare
(
	$company->select (array ('ipo|ge' => 2000), array ('name' => true)),
	array
	(
		array ('id' => '2', 'name' => 'Facebook', 'ipo' => 2012),
		array ('id' => '1', 'name' => 'Google', 'ipo' => 2004)
	)
);

// Select, 1 table, greater than operator
sql_assert_compare
(
	$company->select (array ('id|gt' => 3), array ('id' => true)),
	array
	(
		array ('id' => '4', 'name' => 'Apple', 'ipo' => null)
	)
);

// Select, 1 table, lower or equal operator
sql_assert_compare
(
	$company->select (array ('id|le' => 2), array ('id' => true)),
	array
	(
		array ('id' => '1', 'name' => 'Google', 'ipo' => 2004),
		array ('id' => '2', 'name' => 'Facebook', 'ipo' => 2012)
	)
);

// Select, 1 table, lower than operator
sql_assert_compare
(
	$company->select (array ('ipo|lt' => 2004), array ('id' => true)),
	array
	(
		array ('id' => '3', 'name' => 'Amazon', 'ipo' => 1997)
	)
);

// Select, 1 table, like operator
sql_assert_compare
(
	$company->select (array ('name|like' => 'A%'), array ('id' => true)),
	array
	(
		array ('id' => '3', 'name' => 'Amazon', 'ipo' => 1997),
		array ('id' => '4', 'name' => 'Apple', 'ipo' => null)
	)
);

// Select, 1 table, match boolean operator
sql_assert_compare
(
	$report->select (array ('summary|mb' => 'com*'), array ('day' => false)),
	array
	(
		array ('employee' => '1', 'day' => 3, 'summary' => 'Left the company'),
		array ('employee' => '1', 'day' => 1, 'summary' => 'Joined the company')
	)
);

// Link with company
sql_assert_compare
(
	$employee->select (array ('+' => array ('company' => null)), array ('id' => true)),
	array
	(
		array ('id' => '1', 'name' => 'Alice', 'company__id' => '1', 'company__name' => 'Google', 'company__ipo' => 2004),
		array ('id' => '2', 'name' => 'Bob', 'company__id' => '1', 'company__name' => 'Google', 'company__ipo' => 2004),
		array ('id' => '3', 'name' => 'Carol', 'company__id' => '2', 'company__name' => 'Facebook', 'company__ipo' => 2012),
		array ('id' => '4', 'name' => 'Dave', 'company__id' => '2', 'company__name' => 'Facebook', 'company__ipo' => 2012),
		array ('id' => '5', 'name' => 'Eve', 'company__id' => '3', 'company__name' => 'Amazon', 'company__ipo' => 1997),
		array ('id' => '6', 'name' => 'Mallory', 'company__id' => '4', 'company__name' => 'Apple', 'company__ipo' => null),
	)
);

// Filter by id, link with company and manager
sql_assert_compare
(
	$employee->select (array ('id' => 1, '+'  => array ('company' => null, 'manager' => null)), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Alice', 'company__id' => 1, 'company__name' => 'Google', 'company__ipo' => 2004, 'manager__id' => null, 'manager__name' => null)
	)
);

// Filter by id, link with manager
sql_assert_compare
(
	$employee->select (array ('id' => 2, '+' => array ('manager' => null)), array ('id' => true)),
	array
	(
		array ('id' => 2, 'name' => 'Bob', 'manager__id' => 1, 'manager__name' => 'Alice')
	)
);

// Link with manager, filter by missing manager
sql_assert_compare
(
	$employee->select (array ('+' => array ('manager' => array ('id' => null))), array ('id' => true)),
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
	$employee->select (array ('+' => array ('company' => array ('name|like' => 'A%'))), array ('id' => true)),
	array
	(
		array ('id' => 5, 'name' => 'Eve', 'company__id' => 3, 'company__name' => 'Amazon', 'company__ipo' => 1997),
		array ('id' => 6, 'name' => 'Mallory', 'company__id' => 4, 'company__name' => 'Apple', 'company__ipo' => null)
	)
);

// Filter by id, link with manager and company of manager
sql_assert_compare
(
	$employee->select (array ('id|in' => array (1, 2), '+' => array ('manager' => array ('+' => array ('company' => null)))), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Alice', 'manager__id' => null, 'manager__name' => null, 'manager__company__id' => null, 'manager__company__name' => null, 'manager__company__ipo' => null),
		array ('id' => 2, 'name' => 'Bob', 'manager__id' => 1, 'manager__name' => 'Alice', 'manager__company__id' => 1, 'manager__company__name' => 'Google', 'manager__company__ipo' => 2004)
	)
);

// Filter by id, link with external report on day 2
sql_assert_compare
(
	$employee->select (array ('id' => 1, '+' => array ('report' => array ('!day' => 2))), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Alice', 'report__employee' => 1, 'report__day' => 2, 'report__summary' => 'Read a few things')
	)
);

// Link with both company and manager, filter only on linked fields
sql_assert_compare
(
	$employee->select (array ('+' => array ('company' => array ('id' => 1), 'manager' => array ('id' => 1))), array ('id' => true)),
	array
	(
		array ('id' => '2', 'name' => 'Bob', 'company__id' => '1', 'company__name' => 'Google', 'company__ipo' => 2004, 'manager__id' => '1', 'manager__name' => 'Alice')
	)
);

// Link with company and filter by company name and employee name
sql_assert_compare
(
	$employee->select (array ('+' => array ('company' => null)), array ('+' => array ('company' => array ('name' => true)), 'id' => false)),
	array
	(
		array ('id' => '5', 'name' => 'Eve', 'company__id' => '3', 'company__name' => 'Amazon', 'company__ipo' => 1997),
		array ('id' => '6', 'name' => 'Mallory', 'company__id' => '4', 'company__name' => 'Apple', 'company__ipo' => null),
		array ('id' => '4', 'name' => 'Dave', 'company__id' => '2', 'company__name' => 'Facebook', 'company__ipo' => 2012),
		array ('id' => '3', 'name' => 'Carol', 'company__id' => '2', 'company__name' => 'Facebook', 'company__ipo' => 2012),
		array ('id' => '2', 'name' => 'Bob', 'company__id' => '1', 'company__name' => 'Google', 'company__ipo' => 2004),
		array ('id' => '1', 'name' => 'Alice', 'company__id' => '1', 'company__name' => 'Google', 'company__ipo' => 2004)
	)
);

sql_import ('setup/select_stop.sql');

echo 'OK';

?>

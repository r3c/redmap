<?php

require ('../src/drivers/mysqli.php');
require ('../src/schema.php');

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

function compare ($schema, $filters, $expected)
{
	global $driver;

	list ($query, $params) = $schema->get ($filters, array ('id' => true));

	$returned = $driver->get_rows ($query, $params);

	assert (count ($expected) === count ($returned), 'Same number of returned rows');

	for ($i = 0; $i < count ($expected); ++$i)
	{
		$expected_row = $expected[$i];
		$returned_row = $returned[$i];

		assert (count ($expected_row) === count ($returned_row), 'Same number of fields in row #' . $i);

		foreach ($expected_row as $key => $value)
			assert (array_key_exists ($key, $returned_row) && $returned_row[$key] === $value, 'Match for key "' . $key . '" in row #' . $i);
	}
}

function import ($path)
{
	global $driver;

	assert ($driver->connection->multi_query (file_get_contents ($path)), 'Import SQL file "' . $path . '"');

	while ($driver->connection->more_results ())
		$driver->connection->next_result ();
}

// Start
$driver = new RedMap\Drivers\MySQLiDriver ('utf-8');

assert ($driver->connect ('root', '', 'redmap'), 'Connection to database');

import ('get_link_start.sql');

// Test
compare
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

compare
(
	$employee,
	array ('id' => 1, '+'  => array ('company' => null, 'manager' => null)),
	array
	(
		array ('id' => '1', 'name' => 'Alice', 'company__id' => '1', 'company__name' => 'Google', 'manager__id' => null, 'manager__name' => null)
	)
);

compare
(
	$employee,
	array ('id' => 2, '+' => array ('manager' => null)),
	array
	(
		array ('id' => '2', 'name' => 'Bob', 'manager__id' => '1', 'manager__name' => 'Alice')
	)
);

compare
(
	$employee,
	array ('+' => array ('company' => array ('name|like' => 'A%'))),
	array
	(
		array ('id' => '5', 'name' => 'Eve', 'company__id' => '3', 'company__name' => 'Amazon'),
		array ('id' => '6', 'name' => 'Mallory', 'company__id' => '4', 'company__name' => 'Apple')
	)
);

compare
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
import ('get_link_stop.sql');

?>

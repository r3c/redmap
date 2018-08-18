<?php

function sql_assert_compare ($pair, $expected)
{
	global $driver;

	list ($query, $params) = $pair;

	$returned = $driver->get_rows ($query, $params);

	assert ($returned !== null, 'Get query failed');
	assert (count ($expected) === count ($returned), 'Query returned ' . count ($returned) . ' row(s) instead of ' . count ($expected));

	for ($i = 0; $i < count ($expected); ++$i)
	{
		$expected_row = $expected[$i];
		$returned_row = $returned[$i];

		assert (count ($expected_row) === count ($returned_row), 'Row #' . $i . ' has ' . count ($returned_row) . ' field(s) instead of ' . count ($expected_row));

		foreach ($expected_row as $key => $value)
		{
			assert (array_key_exists ($key, $returned_row), 'Row #' . $i . ' is missing field "' . $key . '"');
			assert (array_key_exists ($key, $returned_row) && $returned_row[$key] === ($value !== null ? (string)$value : null), 'Field "' . $key . '" in row #' . $i . ' is ' . (isset ($returned_row[$key]) ? var_export ($returned_row[$key], true) : 'missing') . ' instead of ' . var_export ($value, true));
		}
	}
}

function sql_assert_execute ($pair)
{
	global $driver;

	list ($query, $params) = $pair;

	return $driver->execute ($query, $params);
}

function sql_connect ()
{
	global $driver;

	$driver = new RedMap\Drivers\MySQLiDriver ('utf-8', function ($driver, $query)
	{
		assert (false, 'Query execution failed: ' . $driver->error ());
	});

	assert ($driver->connect ('redmap', 'redmap', 'redmap'), 'Connection to database');
}

function sql_import ($path)
{
	global $driver;

	assert ($driver->connection->multi_query (file_get_contents ($path)), 'Import SQL file "' . $path . '"');

	while ($driver->connection->more_results ())
		$driver->connection->next_result ();
}

?>

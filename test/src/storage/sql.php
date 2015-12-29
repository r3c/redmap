<?php

function sql_assert_compare ($pair, $expected)
{
	global $driver;

	list ($query, $params) = $pair;

	$returned = $driver->get_rows ($query, $params);

	assert ($returned !== null, 'Get query failed');
	assert (count ($expected) === count ($returned), 'Same number of returned rows');

	for ($i = 0; $i < count ($expected); ++$i)
	{
		$expected_row = $expected[$i];
		$returned_row = $returned[$i];

		assert (count ($expected_row) === count ($returned_row), 'Same number of fields in row #' . $i);

		foreach ($expected_row as $key => $value)
			assert (array_key_exists ($key, $returned_row) && $returned_row[$key] === ($value !== null ? (string)$value : null), 'Match for key "' . $key . '" in row #' . $i);
	}
}

function sql_assert_execute ($pair)
{
	global $driver;

	list ($query, $params) = $pair;

	$result = $driver->execute ($query, $params);

	assert ($result !== null, 'Execute query execution');

	return $result;
}

function sql_connect ()
{
	global $driver;

	$driver = new RedMap\Drivers\MySQLiDriver ('utf-8');

	assert ($driver->connect ('root', '', 'redmap'), 'Connection to database');
}

function sql_import ($path)
{
	global $driver;

	assert ($driver->connection->multi_query (file_get_contents ($path)), 'Import SQL file "' . $path . '"');

	while ($driver->connection->more_results ())
		$driver->connection->next_result ();
}

?>

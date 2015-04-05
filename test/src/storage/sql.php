<?php

function sql_assert_get ($schema, $filters, $expected)
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

function sql_assert_set ($schema, $mode, $fields)
{
	global $driver;

	list ($query, $params) = $schema->set ($mode, $fields);

	$result = $driver->execute ($query, $params);

	assert ($result !== null, 'Set query execution');

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

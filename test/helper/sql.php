<?php

function sql_assert_compare ($pair, $expected)
{
	global $client;

	list ($query, $params) = $pair;

	$returned = $client->get_rows ($query, $params);

	assert ($returned !== null, 'Get query failed');
	assert (count ($expected) === count ($returned), 'Query returned ' . count ($returned) . ' row(s) instead of ' . count ($expected));

	for ($i = 0; $i < count ($returned); ++$i)
	{
		$expected_row = isset ($expected[$i]) ? $expected[$i] : array ();
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
	global $client;

	list ($query, $params) = $pair;

	return $client->execute ($query, $params);
}

function sql_connect ()
{
	global $client;

	$client = new RedMap\Clients\MySQLiClient ('utf-8', function ($client, $query)
	{
		assert (false, 'Query execution failed: ' . $client->error ());
	});

	assert ($client->connect ('root', '', 'redmap'), 'Connection to database');
}

function sql_import ($path)
{
	global $client;

	assert ($client->connection->multi_query (file_get_contents ($path)), 'Import SQL file "' . $path . '"');

	while ($client->connection->more_results ())
		$client->connection->next_result ();
}

?>

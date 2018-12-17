<?php

function sql_compare($returned, $expected)
{
    assert($returned !== null, 'Get query failed');
    assert(count($expected) === count($returned), 'Query returned ' . count($returned) . ' row(s) instead of ' . count($expected));

    for ($i = 0; $i < count($returned); ++$i) {
        $expected_row = isset($expected[$i]) ? $expected[$i] : array();
        $returned_row = $returned[$i];

        assert(count($expected_row) === count($returned_row), 'Row #' . $i . ' has ' . count($returned_row) . ' field(s) instead of ' . count($expected_row));

        foreach ($expected_row as $key => $value) {
            assert(array_key_exists($key, $returned_row), 'Row #' . $i . ' is missing field "' . $key . '"');
            assert(array_key_exists($key, $returned_row) && $returned_row[$key] === ($value !== null ? (string)$value : null), 'Field "' . $key . '" in row #' . $i . ' is ' . (isset($returned_row[$key]) ? var_export($returned_row[$key], true) : 'missing') . ' instead of ' . var_export($value, true));
        }
    }
}

function sql_connect($callback = null)
{
    $engine = RedMap\open('mysqli://root@127.0.0.1/redmap?charset=utf-8', $callback ?: function ($error, $query) {
        assert(false, 'Query execution failed: ' . $error);
    });

    assert($engine->connect(), 'Connection to database failed');

    return $engine;
}

function sql_import($engine, $path)
{
    $class = new ReflectionClass('RedMap\\Clients\\MySQLiClient');

    $property = $class->getProperty('connection');
    $property->setAccessible(true);

    $connection = $property->getValue($engine->client);

    assert($connection->multi_query(file_get_contents($path)), 'Import SQL file "' . $path . '"');

    while ($connection->more_results()) {
        $connection->next_result();
    }
}

<?php

require_once('../src/redmap.php');
require_once('helper/sql.php');

function test_insert($insert, $select, $expected)
{
    global $engine;

    sql_import($engine, 'setup/insert_start.sql');
    assert($insert() !== null, 'execution of "insert" statement should succeed');
    sql_compare($select(), $expected);
    sql_import($engine, 'setup/insert_stop.sql');
}

$identity = new RedMap\Schema(
    'identity',
    array(
        'id'	=> null
    )
);

$message = new RedMap\Schema(
    'message',
    array(
        'id'		=> null,
        'sender'	=> null,
        'recipient'	=> null,
        'text'		=> null,
        'time'		=> null
    )
);

$engine = sql_connect();

// Insert, append, empty (should create entry and populate auto-increment primary key)
test_insert(
    function () use ($engine, $identity) {
        return $engine->insert($identity);
    },
    function () use ($engine, $identity) {
        return $engine->select($identity, array(), array('id' => true));
    },
    array(array('id' => 1))
);

// Insert, append, constants (should create entry and populate columns)
test_insert(
    function () use ($engine, $message) {
        return $engine->insert($message, array('sender' => 3, 'recipient' => 4, 'time' => 500, 'text' => 'Hello, World!'));
    },
    function () use ($engine, $message) {
        return $engine->select($message, array('id' => 3));
    },
    array(array('id' => 3, 'sender' => 3, 'recipient' => 4, 'time' => 500, 'text' => 'Hello, World!'))
);

// Insert, append, coalesce (use value) + increment (initial) + max (use value) + min (use value)
test_insert(
    function () use ($engine, $message) {
        return $engine->insert($message, array('sender' => new RedMap\Max(3), 'recipient' => new RedMap\Min(4), 'time' => new RedMap\Increment(100, 500), 'text' => new RedMap\Coalesce('Hello, World!')));
    },
    function () use ($engine, $message) {
        return $engine->select($message, array('id' => 3));
    },
    array(array('id' => 3, 'sender' => 3, 'recipient' => 4, 'time' => 500, 'text' => 'Hello, World!'))
);

// Insert, upsert missing, constants
test_insert(
    function () use ($engine, $message) {
        return $engine->insert($message, array('id' => 3, 'sender' => 42), RedMap\Engine::INSERT_UPSERT);
    },
    function () use ($engine, $message) {
        return $engine->select($message, array('id' => 3));
    },
    array(array('id' => 3, 'sender' => 42, 'recipient' => 0, 'time' => 0, 'text' => ''))
);

// Insert, upsert missing, increment (initial)
test_insert(
    function () use ($engine, $message) {
        return $engine->insert($message, array('id' => 3, 'sender' => new RedMap\Increment(1, 42)), RedMap\Engine::INSERT_UPSERT);
    },
    function () use ($engine, $message) {
        return $engine->select($message, array('id' => 3));
    },
    array(array('id' => 3, 'sender' => 42, 'recipient' => 0, 'time' => 0, 'text' => ''))
);

// Insert, upsert existing, constant
test_insert(
    function () use ($engine, $message) {
        return $engine->insert($message, array('id' => 2, 'sender' => 53, 'text' => 'Upserted!'), RedMap\Engine::INSERT_UPSERT);
    },
    function () use ($engine, $message) {
        return $engine->select($message, array('id' => 2));
    },
    array(array('id' => 2, 'sender' => 53, 'recipient' => 1, 'time' => 1000, 'text' => 'Upserted!'))
);

// Insert, upsert existing, increment (update) + max (use value) + min (keep previous)
test_insert(
    function () use ($engine, $message) {
        return $engine->insert($message, array('id' => 2, 'sender' => new RedMap\Increment(1, 53), 'recipient' => new RedMap\Max(3), 'time' => new RedMap\Min(2000), 'text' => 'Upserted!'), RedMap\Engine::INSERT_UPSERT);
    },
    function () use ($engine, $message) {
        return $engine->select($message, array('id' => 2));
    },
    array(array('id' => 2, 'sender' => 3, 'recipient' => 3, 'time' => 1000, 'text' => 'Upserted!'))
);

// Insert, replace missing, constant
test_insert(
    function () use ($engine, $message) {
        return $engine->insert($message, array('id' => 3, 'sender' => 1, 'recipient' => 9, 'time' => 0, 'text' => 'Replaced!'), RedMap\Engine::INSERT_REPLACE);
    },
    function () use ($engine, $message) {
        return $engine->select($message, array('id' => 3));
    },
    array(array('id' => 3, 'sender' => 1, 'recipient' => 9, 'text' => 'Replaced!', 'time' => 0))
);

// Insert, replace missing, coalesce (use value)
test_insert(
    function () use ($engine, $message) {
        return $engine->insert($message, array('id' => 3, 'sender' => new RedMap\Coalesce(1), 'recipient' => 9, 'time' => 0, 'text' => 'Replaced!'), RedMap\Engine::INSERT_REPLACE);
    },
    function () use ($engine, $message) {
        return $engine->select($message, array('id' => 3));
    },
    array(array('id' => 3, 'sender' => 1, 'recipient' => 9, 'text' => 'Replaced!', 'time' => 0))
);

// Insert, replace existing, constant
test_insert(
    function () use ($engine, $message) {
        return $engine->insert($message, array('id' => 2, 'recipient' => 7), RedMap\Engine::INSERT_REPLACE);
    },
    function () use ($engine, $message) {
        return $engine->select($message, array('id' => 2));
    },
    array(array('id' => 2, 'sender' => 0, 'recipient' => 7, 'text' => '', 'time' => 0))
);

// Insert, replace existing, max (use value) + min (use value)
test_insert(
    function () use ($engine, $message) {
        return $engine->insert($message, array('id' => 2, 'sender' => new RedMap\Min(5), 'recipient' => new RedMap\Max(0)), RedMap\Engine::INSERT_REPLACE);
    },
    function () use ($engine, $message) {
        return $engine->select($message, array('id' => 2));
    },
    array(array('id' => 2, 'sender' => 5, 'recipient' => 0, 'text' => '', 'time' => 0))
);

echo 'OK';

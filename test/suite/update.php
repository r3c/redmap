<?php

$base = dirname(__FILE__);

require_once($base . '/../../src/redmap.php');
require_once($base . '/../helper/sql.php');

function test_update($update, $select, $expected)
{
    global $engine;

    sql_import($engine, 'setup/update_start.sql');
    assert($update() !== null, 'execution of "update" statement should succeed');
    sql_compare($select(), $expected);
    sql_import($engine, 'setup/update_stop.sql');
}

$player = new RedMap\Schema(
    'player',
    array(
        'id'	=> null,
        'name'	=> null
    )
);

$log = new RedMap\Schema(
    'log',
    array(
        'id'		=> null,
        'player'	=> array(RedMap\Schema::FIELD_INTERNAL),
        'score'		=> null
    ),
    '__',
    array(
        'player'	=> array($player, 0, array('player' => 'id'))
    )
);

$engine = sql_connect();

// Update, 1 table, constant
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => 2), array('id' => 1));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id' => 1));
    },
    array(array('id' => 1, 'score' => 2))
);

// Update, 1 table, increment (positive)
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => new RedMap\Increment(3)), array('id' => 2));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id' => 2));
    },
    array(array('id' => 2, 'score' => 8))
);

// Update, 1 table, increment (negative)
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => new RedMap\Increment(-3)), array('id' => 2));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id' => 2));
    },
    array(array('id' => 2, 'score' => 2))
);

// Update, 1 table, coalesce (keep previous)
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => new RedMap\Coalesce(5)), array('id' => 3));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id' => 3));
    },
    array(array('id' => 3, 'score' => 1))
);

// Update, 1 table, coalesce (use value)
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => new RedMap\Coalesce(1)), array('id' => 4));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id' => 4));
    },
    array(array('id' => 4, 'score' => 1))
);

// Update, 1 table, max (keep previous)
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => new RedMap\Max(0)), array('id' => 2));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id' => 2));
    },
    array(array('id' => 2, 'score' => 5))
);

// Update, 1 table, max (use value)
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => new RedMap\Max(7)), array('id' => 2));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id' => 2));
    },
    array(array('id' => 2, 'score' => 7))
);

// Update, 1 table, min (keep previous)
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => new RedMap\Min(7)), array('id' => 2));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id' => 2));
    },
    array(array('id' => 2, 'score' => 5))
);

// Update, 1 table, min (use value)
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => new RedMap\Min(2)), array('id' => 2));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id' => 2));
    },
    array(array('id' => 2, 'score' => 2))
);

// Update, 2 tables, constant
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => 3), array('+' => array('player' => array('id' => 1))));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id|le' => 2), array('id' => true));
    },
    array(array('id' => 1, 'score' => 3), array('id' => 2, 'score' => 3))
);

// Update, 2 tables, increment
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => new RedMap\Increment(4)), array('+' => array('player' => array('id' => 1))));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id|le' => 2), array('id' => true));
    },
    array(array('id' => 1, 'score' => 7), array('id' => 2, 'score' => 9))
);

// Update, 2 tables, coalesce
test_update(
    function () use ($engine, $log) {
        return $engine->update($log, array('score' => new RedMap\Coalesce(3)), array('+' => array('player' => array('id' => 2))));
    },
    function () use ($engine, $log) {
        return $engine->select($log, array('id|ge' => 3), array('id' => true));
    },
    array(array('id' => 3, 'score' => 1), array('id' => 4, 'score' => 3))
);

echo 'OK';

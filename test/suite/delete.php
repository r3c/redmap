<?php

$base = dirname(__FILE__);

require_once $base . '/../../src/redmap.php';
require_once $base . '/../sql.php';

function test_delete($delete, $select, $expected)
{
    global $engine;

    sql_import($engine, 'setup/delete_start.sql');
    assert($delete() !== null, 'execution of "delete" statement should succeed');
    sql_compare($select(), $expected);
    sql_import($engine, 'setup/delete_stop.sql');
}

$entry = new RedMap\Schema(
    'entry',
    array(
        'id'	=> null
    )
);

$engine = sql_connect();

// Delete single entry (should preserve other entries)
test_delete(
    function () use ($engine, $entry) {
        return $engine->delete($entry, array('id' => 1));
    },
    function () use ($engine, $entry) {
        return $engine->select($entry, array(), array('id' => true));
    },
    array(
        array('id' => 2),
        array('id' => 3)
    )
);

// Delete multiple entries with filter (should preserve other entries)
test_delete(
    function () use ($engine, $entry) {
        return $engine->delete($entry, array('id|ge' => 2));
    },
    function () use ($engine, $entry) {
        return $engine->select($entry, array(), array('id' => true));
    },
    array(
        array('id' => 1)
    )
);

// Delete all entries without filter (should preserve auto-increment primary key)
test_delete(
    function () use ($engine, $entry) {
        $success = $engine->delete($entry, array());

        $engine->insert($entry, array());

        return $success;
    },
    function () use ($engine, $entry) {
        return $engine->select($entry, array(), array('id' => true));
    },
    array(
        array('id' => 4)
    )
);

// Truncate all entries without filter (should reset auto-increment primary key)
test_delete(
    function () use ($engine, $entry) {
        $success = $engine->delete($entry);

        $engine->insert($entry, array());

        return $success;
    },
    function () use ($engine, $entry) {
        return $engine->select($entry, array(), array('id' => true));
    },
    array(
        array('id' => 1)
    )
);

echo 'OK';

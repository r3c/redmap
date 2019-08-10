<?php

$base = dirname(__FILE__);

require_once($base . '/../../src/redmap.php');
require_once($base . '/../helper/sql.php');

function test_source($source, $select, $expected)
{
    global $engine;

    sql_import($engine, 'setup/source_start.sql');
    assert($source() !== null, 'execution of "source" statement should succeed');
    sql_compare($select(), $expected);
    sql_import($engine, 'setup/source_stop.sql');
}

$category = new RedMap\Schema(
    'category',
    array(
        'id'	=> null,
        'name'	=> null
    )
);

$food = new RedMap\Schema(
    'food',
    array(
        'category'	=> null,
        'id'		=> null,
        'name'		=> null
    ),
    '__',
    array(
        'category'	=> array($category, 0, array('category' => 'id'))
    )
);

$stock = new RedMap\Schema(
    'stock',
    array(
        'id'		=> null,
        'name'		=> null,
        'price'		=> null,
        'quantity'	=> null
    )
);

$engine = sql_connect();

// Insert
test_source(
    function () use ($engine, $stock, $food) {
        return $engine->source(
            $stock,
            array(
                'id'		=> array(RedMap\Engine::SOURCE_COLUMN, 'id'),
                'name'		=> array(RedMap\Engine::SOURCE_COLUMN, 'name'),
                'price'		=> array(RedMap\Engine::SOURCE_COLUMN, 'id'),
                'quantity'	=> array(RedMap\Engine::SOURCE_VALUE, 0)
            ),
            RedMap\Engine::INSERT_APPEND,
            $food,
            array('id|le' => 2)
        );
    },
    function () use ($engine, $stock) {
        return $engine->select($stock, array(), array('id' => true));
    },
    array(
        array('id' => 1, 'name' => 'Apple', 'price' => 1, 'quantity' => 0),
        array('id' => 2, 'name' => 'Banana', 'price' => 2, 'quantity' => 0),
        array('id' => 3, 'name' => 'Foo', 'price' => 5, 'quantity' => 17),
        array('id' => 4, 'name' => 'Bar', 'price' => 7, 'quantity' => 42)
    )
);

// Insert, child reference
test_source(
    function () use ($engine, $stock, $food) {
        return $engine->source(
            $stock,
            array(
                'id'		=> array(RedMap\Engine::SOURCE_COLUMN, 'id'),
                'name'		=> array(RedMap\Engine::SOURCE_COLUMN, 'category__name'),
                'price'		=> array(RedMap\Engine::SOURCE_COLUMN, 'id'),
                'quantity'	=> array(RedMap\Engine::SOURCE_VALUE, 0)
            ),
            RedMap\Engine::INSERT_APPEND,
            $food,
            array('id' => 1, '+' => array('category' => null)) // FIXME [source-nested-implicit]: no error is raised when "category" is not linked here (and missing from selected columns)
        );
    },
    function () use ($engine, $stock) {
        return $engine->select($stock, array(), array('id' => true));
    },
    array(
        array('id' => 1, 'name' => 'Fruit', 'price' => 1, 'quantity' => 0),
        array('id' => 3, 'name' => 'Foo', 'price' => 5, 'quantity' => 17),
        array('id' => 4, 'name' => 'Bar', 'price' => 7, 'quantity' => 42)
    )
);

// Replace
test_source(
    function () use ($engine, $stock, $food) {
        return $engine->source(
            $stock,
            array(
                'id'		=> array(RedMap\Engine::SOURCE_COLUMN, 'id'),
                'name'		=> array(RedMap\Engine::SOURCE_COLUMN, 'name'),
                'price'		=> array(RedMap\Engine::SOURCE_VALUE, 0),
                'quantity'	=> array(RedMap\Engine::SOURCE_VALUE, 1),
            ),
            RedMap\Engine::INSERT_REPLACE,
            $food,
            array('id|ge' => 3)
        );
    },
    function () use ($engine, $stock) {
        return $engine->select($stock, array(), array('id' => true));
    },
    array(
        array('id' => 3, 'name' => 'Carrot', 'price' => 0, 'quantity' => 1),
        array('id' => 4, 'name' => 'Orange', 'price' => 0, 'quantity' => 1)
    )
);

// Upsert
test_source(
    function () use ($engine, $stock, $food) {
        return $engine->source(
            $stock,
            array(
                'id'		=> array(RedMap\Engine::SOURCE_COLUMN, 'id'),
                'name'		=> array(RedMap\Engine::SOURCE_COLUMN, 'name'),
                'price'		=> array(RedMap\Engine::SOURCE_VALUE, 3),
                'quantity'	=> array(RedMap\Engine::SOURCE_VALUE, 2),
            ),
            RedMap\Engine::INSERT_UPSERT,
            $food,
            array('id|le' => 3)
        );
    },
    function () use ($engine, $stock) {
        return $engine->select($stock, array(), array('id' => true));
    },
    array(
        array('id' => 1, 'name' => 'Apple', 'price' => 3, 'quantity' => 2),
        array('id' => 2, 'name' => 'Banana', 'price' => 3, 'quantity' => 2),
        array('id' => 3, 'name' => 'Carrot', 'price' => 3, 'quantity' => 2),
        array('id' => 4, 'name' => 'Bar', 'price' => 7, 'quantity' => 42)
    )
);

// Upsert, max
test_source(
    function () use ($engine, $stock, $food) {
        return $engine->source(
            $stock,
            array(
                'id'		=> array(RedMap\Engine::SOURCE_COLUMN, 'id'),
                'name'		=> array(RedMap\Engine::SOURCE_COLUMN, 'name'),
                'price'		=> array(RedMap\Engine::SOURCE_VALUE, 3),
                'quantity'	=> array(RedMap\Engine::SOURCE_VALUE, new RedMap\Max(20)),
            ),
            RedMap\Engine::INSERT_UPSERT,
            $food
        );
    },
    function () use ($engine, $stock) {
        return $engine->select($stock, array(), array('id' => true));
    },
    array(
        array('id' => 1, 'name' => 'Apple', 'price' => 3, 'quantity' => 20),
        array('id' => 2, 'name' => 'Banana', 'price' => 3, 'quantity' => 20),
        array('id' => 3, 'name' => 'Carrot', 'price' => 3, 'quantity' => 20),
        array('id' => 4, 'name' => 'Orange', 'price' => 3, 'quantity' => 42)
    )
);

echo 'OK';

<?php

$base = dirname(__FILE__);

require_once $base . '/../../src/redmap.php';
require_once $base . '/../sql.php';

// Create engine from invalid connection strings
function test_open($connection, $message)
{
    try {
        RedMap\Connection::open($connection);

        assert(false, 'invalid connection string should raise exception');
    } catch (RedMap\ConfigurationException $exception) {
        assert(strpos($exception->getMessage(), $message) !== false, 'error message must contain "' . $message . '" but was "' . $exception->getMessage() . '"');
    }
}

test_open('//', 'could not parse connection string');
test_open('unsupported://localhost/redmap', 'unknown scheme');
test_open('localhost', 'missing host name');
test_open('mysql://localhost', 'missing database name');
test_open('mysql://localhost/name?unknown=1', 'unknown option(s)');

// Execute invalid query
$failed = false;

$engine = sql_connect(function ($title, $message) use (&$failed) {
    assert($title === 'Query error', 'invalid query should raise error');
    assert(strpos($message, 'syntax') !== false, 'invalid query should report syntax error');
    assert(strpos($message, 'ERROR') !== false, 'invalid query should include snippet in message');
    assert(strpos($message, 'SELECT 1') === false, 'invalid query should not include full query in message');

    $failed = true;
});

assert($engine->client->execute('SELECT 1 WHERE THIS IS AN ERROR') === null, 'execution of invalid query should fail');
assert($failed, 'execution of invalid query should trigger error callback');

echo 'OK';

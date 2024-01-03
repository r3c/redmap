<?php

$base = dirname(__FILE__);

require_once($base . '/../../src/redmap.php');
require_once($base . '/../sql.php');

// Start
$engine = sql_connect();
$engine->client->set_reconnect(true);

// Cause connection to timeout (auto-reconnect feature should trigger)
assert($engine->client->execute('SET wait_timeout = 1') !== null, 'should set wait_timeout to 1 second');

sleep(2);

// Temporarily disable error reporting to hide "MySQL gone away" warning messages
$level = error_reporting(E_ERROR);
$rows = $engine->client->select('SELECT 17');

sql_compare($rows, array(array('17' => '17')));

// Restore previous error reporting
error_reporting($level);

echo 'OK';

<?php

require_once ('helper/sql.php');

// Start
$database = sql_connect ();

// Cause connection to timeout (auto-reconnect feature should trigger)
assert ($database->client->execute ('SET wait_timeout = 1') !== null);

sleep (2);

// Temporarily disable error reporting to hide "MySQL gone away" warning messages
$level = error_reporting (E_ERROR);

sql_compare (array ('SELECT 17', array ()), array (array ('17' => '17')));

// Restore previous error reporting
error_reporting ($level);

echo 'OK';

?>

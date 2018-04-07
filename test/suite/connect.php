<?php

require_once ('../src/drivers/mysqli.php');
require_once ('helper/sql.php');

// Start
sql_connect ();

// Cause connection to timeout (auto-reconnect feature should trigger)
sql_assert_execute (array ('SET wait_timeout = 1', array ()));

sleep (2);

// Temporarily disable error reporting to hide "MySQL gone away" warning messages
$level = error_reporting (E_ERROR);

sql_assert_compare (array ('SELECT 17', array ()), array (array ('17' => '17')));

// Restore previous error reporting
error_reporting ($level);

echo 'OK';

?>

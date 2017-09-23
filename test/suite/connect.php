<?php

require_once ('../src/drivers/mysqli.php');
require_once ('helper/sql.php');

// Start
sql_connect ();
sql_assert_execute (array ('SET wait_timeout = 1', array ()));

sleep (2);

sql_assert_compare (array ('SELECT 17', array ()), array (array ('17' => '17')));

echo 'OK';

?>

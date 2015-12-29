<?php

require ('../../main/src/drivers/mysqli.php');
require ('../../main/src/schema.php');
require ('storage/sql.php');

$score = new RedMap\Schema
(
	'score',
	array
	(
		'player'	=> array (RedMap\Schema::FIELD_PRIMARY),
		'value'		=> null
	)
);

// Start
sql_connect ();
sql_import ('../res/clean_start.sql');

// Optimize
sql_assert_execute ($score->clean (RedMap\Schema::CLEAN_OPTIMIZE));
sql_assert_compare ($score->get (), array (array ('player' => 'me', 'value' => 42)));

// Truncate
sql_assert_execute ($score->clean (RedMap\Schema::CLEAN_TRUNCATE));
sql_assert_compare ($score->get (), array ());

// Stop
sql_import ('../res/clean_stop.sql');

?>

<?php

require_once ('../src/drivers/mysqli.php');
require_once ('../src/schema.php');
require_once ('helper/sql.php');

// Start
sql_connect ();
sql_import ('setup/clean_start.sql');

foreach (array ('score_memory', 'score_myisam') as $table)
{
	$score = new RedMap\Schema
	(
		$table,
		array
		(
			'player'	=> array (RedMap\Schema::FIELD_PRIMARY),
			'value'		=> null
		)
	);

	// Optimize
	foreach ($score->clean (RedMap\Schema::CLEAN_OPTIMIZE) as $pair)
		sql_assert_execute ($pair);

	sql_assert_compare ($score->get (), array (array ('player' => 'me', 'value' => 42)));

	// Truncate
	foreach ($score->clean (RedMap\Schema::CLEAN_TRUNCATE) as $pair)
		sql_assert_execute ($pair);

	sql_assert_compare ($score->get (), array ());
}

// Stop
sql_import ('setup/clean_stop.sql');

echo 'OK';

?>

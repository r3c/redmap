<?php

require_once ('../src/drivers/mysqli.php');
require_once ('../src/schema.php');
require_once ('helper/sql.php');

// Start
sql_connect ();
sql_import ('setup/clean_start.sql');

$database = new RedMap\SQLDatabase ();

foreach (array ('score_memory', 'score_myisam') as $table)
{
	$score = new RedMap\Schema
	(
		$table,
		array
		(
			'player'	=> null,
			'value'		=> null
		)
	);

	// Optimize
	foreach ($database->clean ($score, RedMap\Database::CLEAN_OPTIMIZE) as $pair)
		sql_assert_execute ($pair);

	sql_assert_compare ($database->select ($score), array (array ('player' => 'me', 'value' => 42)));

	// Truncate
	foreach ($database->clean ($score, RedMap\Database::CLEAN_TRUNCATE) as $pair)
		sql_assert_execute ($pair);

	sql_assert_compare ($database->select ($score), array ());
}

// Stop
sql_import ('setup/clean_stop.sql');

echo 'OK';

?>

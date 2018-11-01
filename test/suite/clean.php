<?php

require_once ('../src/redmap.php');
require_once ('helper/sql.php');

// Start
$database = sql_connect ();

sql_import ('setup/clean_start.sql');

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
	assert ($database->clean ($score, RedMap\Database::CLEAN_OPTIMIZE) !== null);

	sql_compare ($database->select ($score), array (array ('player' => 'me', 'value' => 42)));

	// Truncate
	assert ($database->clean ($score, RedMap\Database::CLEAN_TRUNCATE) !== null);

	sql_compare ($database->select ($score), array ());
}

// Stop
sql_import ('setup/clean_stop.sql');

echo 'OK';

?>

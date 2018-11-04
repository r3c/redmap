<?php

require_once ('../src/redmap.php');
require_once ('helper/sql.php');

// Start
$engine = sql_connect ();

sql_import ($engine, 'setup/clean_start.sql');

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
	assert ($engine->clean ($score, RedMap\Engine::CLEAN_OPTIMIZE) !== null);

	sql_compare ($engine->select ($score), array (array ('player' => 'me', 'value' => 42)));

	// Truncate
	assert ($engine->clean ($score, RedMap\Engine::CLEAN_TRUNCATE) !== null);

	sql_compare ($engine->select ($score), array ());
}

// Stop
sql_import ($engine, 'setup/clean_stop.sql');

echo 'OK';

?>

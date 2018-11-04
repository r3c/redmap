<?php

require_once ('../src/redmap.php');
require_once ('helper/sql.php');

// Start
$engine = sql_connect ();

sql_import ($engine, 'setup/wash_start.sql');

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

	assert ($engine->wash ($score) !== null);

	sql_compare ($engine->select ($score), array (array ('player' => 'me', 'value' => 42)));
}

// Stop
sql_import ($engine, 'setup/wash_stop.sql');

echo 'OK';

?>

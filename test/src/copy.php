<?php

require ('../../main/src/drivers/mysqli.php');
require ('../../main/src/schema.php');
require ('storage/sql.php');

$source = new RedMap\Schema
(
	'source',
	array
	(
		'id'	=> array (RedMap\Schema::FIELD_PRIMARY),
		'name'	=> null
	)
);

$target = new RedMap\Schema
(
	'target',
	array
	(
		'id'		=> array (RedMap\Schema::FIELD_PRIMARY),
		'name'		=> null,
		'comment'	=> null
	)
);

// Start
sql_connect ();
sql_import ('../res/copy_start.sql');

// Insert
sql_assert_execute ($target->copy
(
	RedMap\Schema::SET_INSERT,
	array
	(
		'id'		=> array (RedMap\Schema::COPY_FIELD, 'id'),
		'name'		=> array (RedMap\Schema::COPY_FIELD, 'name'),
		'comment'	=> array (RedMap\Schema::COPY_VALUE, 'step 1'),
	),
	$source,
	array ('id|ne' => 2)
));

sql_assert_compare
(
	$target->get (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Apple', 'comment' => 'step 1'),
		array ('id' => 3, 'name' => 'Carrot', 'comment' => 'step 1')
	)
);

// Replace
sql_assert_execute ($source->set (RedMap\Schema::SET_UPDATE, array ('id' => 1, 'name' => 'Ananas')));
sql_assert_execute ($target->copy
(
	RedMap\Schema::SET_REPLACE,
	array
	(
		'id'		=> array (RedMap\Schema::COPY_FIELD, 'id'),
		'name'		=> array (RedMap\Schema::COPY_FIELD, 'name'),
		'comment'	=> array (RedMap\Schema::COPY_VALUE, 'step 2'),
	),
	$source,
	array ('id' => 1)
));

sql_assert_compare
(
	$target->get (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Ananas', 'comment' => 'step 2'),
		array ('id' => 3, 'name' => 'Carrot', 'comment' => 'step 1')
	)
);

// Upsert
sql_assert_execute ($target->copy
(
	RedMap\Schema::SET_UPSERT,
	array
	(
		'id'		=> array (RedMap\Schema::COPY_FIELD, 'id'),
		'name'		=> array (RedMap\Schema::COPY_FIELD, 'name'),
		'comment'	=> array (RedMap\Schema::COPY_VALUE, 'step 3'),
	),
	$source
));

sql_assert_compare
(
	$target->get (array (), array ('id' => true)),
	array
	(
		array ('id' => 1, 'name' => 'Ananas', 'comment' => 'step 3'),
		array ('id' => 2, 'name' => 'Banana', 'comment' => 'step 3'),
		array ('id' => 3, 'name' => 'Carrot', 'comment' => 'step 3')
	)
);

// Stop
sql_import ('../res/copy_stop.sql');

?>

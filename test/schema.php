<?php

require ('../src/drivers/mysqli.php');
require ('../src/schema.php');

$driver = new RedMap\Drivers\MySQLiDriver ('utf-8');
$driver->connect ('root', '', 'redmap') or die ('can\'t connect');

header ('Content-Type: text/plain');

$schema_member = new RedMap\Schema
(
	'member',
	array
	(
		'avatar'	=> null,
		'sex'		=> null,
		'signature'	=> null,
		'user'		=> array (RedMap\Schema::FIELD_PRIMARY)
	)
);

$schema_section = new RedMap\Schema
(
	'section',
	array
	(
		'description'	=> null,
		'flags'			=> null,
		'forum'			=> null,
		'id'			=> array (RedMap\Schema::FIELD_PRIMARY),
		'header'		=> null,
		'name'			=> null
	)
);

$schema_topic = new RedMap\Schema
(
	'topic',
	array
	(
		'create_member'	=> null,
		'create_time'	=> null,
		'flags'			=> null,
		'icon'			=> null,
		'id'			=> array (RedMap\Schema::FIELD_PRIMARY),
		'last_time'		=> null,
		'name'			=> null,
		'section'		=> null,
		'views'			=> null,
		'weight'		=> null
	),
	'__',
	array
	(
		'bookmark'		=> array (function () { global $schema_bookmark; return $schema_bookmark; }, RedMap\Schema::LINK_OPTIONAL, array ('id' => 'topic', 'member' => 'member')),
		'create_member'	=> array ($schema_member, 0, array ('create_member' => 'user')),
		'section'		=> array ($schema_section, 0, array ('section' => 'id'))
	)
);

$schema_bookmark = new RedMap\Schema
(
	'bookmark',
	array
	(
		'fresh'		=> null,
		'member'	=> array (RedMap\Schema::FIELD_PRIMARY),
		'position'	=> null,
		'time'		=> null,
		'topic'		=> array (RedMap\Schema::FIELD_PRIMARY),
		'watch'		=> null
	),
	'__',
	array
	(
		'member'	=> array ($schema_member, 0, array ('member' => 'user')),
		'topic'		=> array ($schema_topic, 0, array ('topic' => 'id'))
	)
);

// Test 1
list ($query, $params) = $schema_section->get (array ('id' => 101));

$row = $driver->get_first ($query, $params);

// Test 2
list ($query, $params) = $schema_topic->get (array
(
	'id' => 57,
	array ('~' => 'or', 'last_time|le' => 3, 'last_time|gt' => 8),
	'+' => array
	(
		'bookmark' => array ('member' => 1705),
		'create_member' => array (),
		'section' => array ('forum|ne' => 3)
	)
));

echo var_export ($query, true) . "\n\n";
echo var_export ($params, true);

?>

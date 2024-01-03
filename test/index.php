<?php

header('Content-Type: text/plain');

if (isset($argv)) {
	parse_str(implode('&', array_slice($argv, 1)), $options);
} else {
	$options = $_GET;
}

$option_client = isset($options['client']) ? $options['client'] : 'mysqli';

?>
# RedMap Tests

## Options

- client: <?php echo $option_client; ?>


## Suites

- connect: <?php require 'suite/connect.php'; ?>

- delete: <?php require 'suite/delete.php'; ?>

- error: <?php require 'suite/error.php'; ?>

- insert: <?php require 'suite/insert.php'; ?>

- select: <?php require 'suite/select.php'; ?>

- source: <?php require 'suite/source.php'; ?>

- update: <?php require 'suite/update.php'; ?>

- wash: <?php require 'suite/wash.php'; ?>
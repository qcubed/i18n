<?php

$cliOptions = [ 'phpunit'];	// first entry is the command
array_push($cliOptions, '-c', __DIR__ . '/phpunit-local.xml');	// the config file is here

require dirname(dirname(dirname(dirname(__FILE__)))) . '/autoload.php'; // Find PHPUnit_TextUI_Command

$tester = new PHPUnit_TextUI_Command();

$tester->run($cliOptions);

<?php

require dirname(dirname(dirname(__FILE__))) . '/autoload.php'; // Add the Composer autoloader if using Composer
require_once dirname(__FILE__) . '/src/Sepia/Parser.php';

$cliOptions = [ 'phpunit'];	// first entry is the command
array_push($cliOptions, '-c', dirname(__FILE__) . '/phpunit-local.xml');	// the config file is here

$tester = new PHPUnit_TextUI_Command();

$tester->run($cliOptions);

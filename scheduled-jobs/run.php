<?php

require_once(__DIR__ . "/../startup.php");


$jobby = new Jobby\Jobby();

$jobby->add('interfacing', array(
    'command' => PHP_BINARY . " " . __DIR__ . DIRECTORY_SEPARATOR . "interface.php",
    'schedule' => '* * * * *',
    //'output' => 'logs/jobs.log',
    'enabled' => true,
));

$jobby->run();

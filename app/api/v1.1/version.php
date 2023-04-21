<?php
session_unset(); // no need of session in json response

$db = \MysqliDb::getInstance();

// return VLSM Version
$payload = array('version' => VERSION);
echo json_encode($payload);

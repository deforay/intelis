<?php
session_unset(); // no need of session in json response
//header('Content-Type: application/json; charset=utf-8');
header('Content-Type: application/json');
// return VLSM Version
$payload = array('version' => VERSION);
echo json_encode($payload);

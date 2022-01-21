<?php

header('Content-Type: application/json; charset=utf-8');

// return VLSM Version
$payload = array('version' => VERSION);
echo json_encode($payload);

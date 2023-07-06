<?php

use App\Registries\ContainerRegistry;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$artNo = $_POST['artPatientNo'];

$count = 0;
$pQuery = "SELECT count(*) as 'count'
            FROM form_eid
            WHERE child_id like '%$artNo%'
            OR child_name like '%$artNo%'
            OR child_surname like '%$artNo%'
            OR caretaker_phone_number like '%$artNo%'";
$pResult = $db->rawQueryOne($pQuery);
echo $pResult['count'];

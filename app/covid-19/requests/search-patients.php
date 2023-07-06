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
$pQuery = "SELECT COUNT(*) AS count
            FROM form_covid19
            WHERE patient_id like '%$artNo%'
            OR patient_name like '%$artNo%'
            OR patient_surname like '%$artNo%'
            OR patient_phone_number like '%$artNo%'";

$pResult = $db->rawQueryOne($pQuery);
echo $pResult['count'];

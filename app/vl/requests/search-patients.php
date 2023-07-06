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
$pQuery = "SELECT count(*) as 'count' FROM form_vl
                WHERE patient_art_no like '%$artNo%'
                OR patient_first_name like '%$artNo%'
                OR patient_middle_name like '%$artNo%'
                OR patient_last_name like '%$artNo%'";
$pResult = $db->rawQueryOne($pQuery);
echo $pResult['count'];

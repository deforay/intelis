<?php


use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$artNo = $_POST['artPatientNo'];

$count = 0;
$pQuery = "SELECT COUNT(*) AS total FROM form_covid19 where (patient_id like '%$artNo%' OR patient_name like '%$artNo%' OR patient_surname like '%$artNo%' OR patient_phone_number like '%$artNo%')";

$pResult = $db->rawQueryOne($pQuery);
$count = count($pResult['total']);
echo $count;

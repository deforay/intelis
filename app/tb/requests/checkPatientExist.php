<?php


use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$artNo = $_POST['artPatientNo'];

$count = 0;
$pQuery = "SELECT * FROM form_tb WHERE (patient_id like '%$artNo%' OR patient_name like '%$artNo%' OR patient_surname like '%$artNo%')";
$pResult = $db->rawQuery($pQuery);
$count = count($pResult);
echo $count;

<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

$artNo = $_POST['artPatientNo'];

$count = 0;
$pQuery = "SELECT * FROM form_tb where (patient_id like '%" . $artNo . "%' OR patient_name like '%" . $artNo . "%' OR patient_surname like '%" . $artNo . "%') ORDER BY sample_tested_datetime DESC, sample_collection_date DESC LIMIT 25";
$pResult = $db->rawQuery($pQuery);
$count = count($pResult);
echo $count;

<?php


use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$artNo = $_POST['artPatientNo'];

$count = 0;
$searchTerm = '%' . $artNo . '%';
$pQuery = "SELECT * FROM form_tb WHERE (patient_id like ? OR patient_name like ? OR patient_surname like ?)";
$pResult = $db->rawQuery($pQuery, [$searchTerm, $searchTerm, $searchTerm]);
$count = count($pResult);
echo $count;

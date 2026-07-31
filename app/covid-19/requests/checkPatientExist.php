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
$searchTerm = '%' . $artNo . '%';
$pQuery = "SELECT COUNT(*) AS total FROM form_covid19 where (patient_id like ? OR patient_name like ? OR patient_surname like ? OR patient_phone_number like ?)";

$pResult = $db->rawQueryOne($pQuery, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
$count = count($pResult['total']);
echo $count;

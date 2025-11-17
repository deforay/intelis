<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (!empty($_GET['labId']) && !empty($_GET['version'])) {
    $labId = (int) $_GET['labId'];
    $version = $_GET['version'];
    $sql = 'UPDATE facility_details
                SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.version", ?, "$.lastHeartBeat", ?)
                WHERE facility_id = ?';
    $db->rawQuery($sql, [$version, DateUtility::getCurrentDateTime(), $labId]);
}

// return application Version
echo json_encode(['version' => VERSION]);

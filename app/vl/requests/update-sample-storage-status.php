<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Services\StorageService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var StorageService $storageService */
$storageService = ContainerRegistry::get(StorageService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$currentStorage = explode("-", (string) $_POST['currentStorage']);

$getFreezer = $storageService->getStorageByCode($currentStorage[0]);
$currentFreezerId = $getFreezer['storage_id'];

$currentStorageInfo = $storageService->getFreezerHistoryById($_POST['historyId']);
$data = [];

if (is_numeric($_POST['removalReason']) === false) {
    $reasonData = ['removal_reason_name'   => $_POST['removalReason'], 'removal_reason_status' => "active", 'updated_datetime'   => DateUtility::getCurrentDateTime()];
    $db->insert('r_reasons_for_sample_removal', $reasonData);
    $removalReasonId = $db->getInsertId();
} else {
    $removalReasonId = $_POST['removalReason'];
}
$data[] = ['test_type' => 'vl', 'sample_unique_id'  => $currentStorageInfo['sample_unique_id'], 'volume'    => $currentStorageInfo['volume'], 'freezer_id' => $currentStorageInfo['freezer_id'], 'rack' => $currentStorageInfo['rack'], 'box' => $currentStorageInfo['box'], 'position' => $currentStorageInfo['position'], 'sample_status' => "Removed", 'sample_removal_reason' => $removalReasonId, 'date_out' =>  DateUtility::isoDateFormat($currentStorageInfo['date_out']), 'comments' => $currentStorageInfo['comments'], 'updated_datetime'    => DateUtility::getCurrentDateTime(), 'updated_by' => $_SESSION['userId']];

if (isset($_POST['freezerId']) && $_POST['freezerId'] != "" && $currentFreezerId != $_POST['freezerId']) {
    //Moving samples from current freezer to another freezer
    $data[] = ['test_type' => 'vl', 'sample_unique_id'  => $_POST['uniqueId'], 'volume'    => $_POST['volume'], 'freezer_id' => $_POST['freezerId'], 'rack' => $_POST['rack'], 'box' => $_POST['box'], 'position' => $_POST['position'], 'sample_status' => "Added", 'date_out' =>  DateUtility::isoDateFormat($_POST['dateOut']), 'comments' => $_POST['comments'], 'updated_datetime'  => DateUtility::getCurrentDateTime(), 'updated_by' => $_SESSION['userId']];
}
$counter = count($data);
for ($i = 0; $i < $counter; $i++) {
    $save = $db->insert('lab_storage_history', $data[$i]);
}

echo $save;

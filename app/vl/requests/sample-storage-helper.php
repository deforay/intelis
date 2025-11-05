<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {

    $data = [];
    $cnt = count($_POST['sampleUniqueId']);
    for ($i = 0; $i < $cnt; $i++) {
        if (!empty($_POST['volume'][$i]) && (($_POST['volume'][$i]) > 0) && !empty($_POST['freezer'][$i]) && !empty($_POST['rack'][$i]) && !empty($_POST['box'][$i]) && !empty($_POST['position'][$i])) {
            if ($_POST['dateOut'][$i] != "") {
                $dateOut = DateUtility::isoDateFormat($_POST['dateOut'][$i]);
            } else {
                $dateOut = null;
            }
            $data = [
                'test_type' => 'vl',
                'sample_unique_id' => $_POST['sampleUniqueId'][$i],
                'volume' => $_POST['volume'][$i],
                'freezer_id' => $_POST['freezer'][$i],
                'rack' => $_POST['rack'][$i],
                'box' => $_POST['box'][$i],
                'position' => $_POST['position'][$i],
                'sample_status' => "Added",
                'date_out' => $dateOut,
                'comments' => $_POST['comments'][$i],
                'updated_datetime' => DateUtility::getCurrentDateTime(),
                'updated_by' => $_SESSION['userId']
            ];
            $db->insert('lab_storage_history', $data);
        }
    }

    $_SESSION['alertMsg'] = _translate("Sample added to the freezer successfully");

    header("Location:/vl/requests/sample-storage.php");
} catch (Throwable $exc) {
    LoggerUtility::logError($exc->getMessage());
    throw new SystemException(($exc->getMessage()));
}

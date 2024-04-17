<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\SystemService;
use App\Exceptions\SystemException;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


try {

    $data = array();
    $cnt = count($_POST['sampleUniqueId']);
    for ($i = 0; $i < $cnt; $i++) {
        if ($_POST['dateOut'][$i] != "") {
            $dateOut = DateUtility::isoDateFormat($_POST['dateOut'][$i]);
        } else {
            $dateOut = NULL;
        }
        $data[] = array(
            'test_type' => 'vl',
            'sample_unique_id'     => $_POST['sampleUniqueId'][$i],
            'volume'     => $_POST['volume'][$i],
            'freezer_id' => $_POST['freezer'][$i],
            'rack' => $_POST['rack'][$i],
            'box' => $_POST['box'][$i],
            'position' => $_POST['position'][$i],
            'sample_status' => "Added",
            'date_out' => $dateOut,
            'comments' => $_POST['comments'][$i],
            'updated_datetime'    => DateUtility::getCurrentDateTime(),
            'updated_by' => $_SESSION['userId']
        );
    }
    $db->insertMulti('lab_storage_history', $data);

    $_SESSION['alertMsg'] = _translate("Sample added to the freezer successfully");

    header("Location:/vl/requests/sample-storage.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());

    throw new SystemException(($exc->getMessage()));
}

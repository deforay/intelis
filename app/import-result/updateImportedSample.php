<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = "temp_sample_import";
try {
    $result = 0;
    if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
        $batchResult = $db->rawQuery("SELECT batch_code FROM batch_details WHERE batch_code= ?", [trim((string) $_POST['batchCode'])]);
        if (!$batchResult) {
            $data = array(
                'machine' => 0,
                'batch_code' => trim((string) $_POST['batchCode']),
                'request_created_datetime' => DateUtility::getCurrentDateTime()
            );
            $db->insert("batch_details", $data);
        }
        $db->where('temp_sample_id', $_POST['tempsampleId']);
        $result = $db->update($tableName, array('batch_code' => $_POST['batchCode']));
    } else if (isset($_POST['sampleCode']) && trim((string) $_POST['sampleCode']) != '') {
        $sampleResult = $db->rawQuery("SELECT sample_code FROM form_vl WHERE sample_code='" . trim((string) $_POST['sampleCode']) . "'");
        if (!empty($sampleResult)) {
            $sampleDetails = _translate('Result already exists');
        } else {
            $sampleDetails = _translate('New Sample');
        }
        $db->where('temp_sample_id', $_POST['tempsampleId']);
        $result = $db->update($tableName, array('sample_code' => $_POST['sampleCode'], 'sample_details' => $sampleDetails));
    } else if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) != '') {
        $sampleControlResult = $db->rawQuery("select r_sample_control_name from r_sample_controls where r_sample_control_name='" . trim((string) $_POST['sampleType']) . "'");
        $db->where('temp_sample_id', $_POST['tempsampleId']);
        $result = $db->update($tableName, array('sample_type' => trim((string) $_POST['sampleType'])));
    }
    echo $result;
} catch (Exception $exc) {
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $exc->getTraceAsString(),
    ]);
}

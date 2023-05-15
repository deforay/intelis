<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "temp_sample_import";
try {
    $result = 0;
    if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
        $batchResult = $db->rawQuery("select batch_code from batch_details where batch_code='" . trim($_POST['batchCode']) . "'");
        if (!$batchResult) {
            $data = array(
                'machine' => 0,
                'batch_code' => trim($_POST['batchCode']),
                'request_created_datetime' => DateUtility::getCurrentDateTime()
            );
            $db->insert("batch_details", $data);
        }
        $db = $db->where('temp_sample_id', $_POST['tempsampleId']);
        $result = $db->update($tableName, array('batch_code' => $_POST['batchCode']));
    } else if (isset($_POST['sampleCode']) && trim($_POST['sampleCode']) != '') {
        $sampleResult = $db->rawQuery("select sample_code from form_vl where sample_code='" . trim($_POST['sampleCode']) . "'");
        if ($sampleResult) {
            $sampleDetails = 'Result already exists';
        } else {
            $sampleDetails = 'New Sample';
        }
        $db = $db->where('temp_sample_id', $_POST['tempsampleId']);
        $result = $db->update($tableName, array('sample_code' => $_POST['sampleCode'], 'sample_details' => $sampleDetails));
    } else if (isset($_POST['sampleType']) && trim($_POST['sampleType']) != '') {
        $sampleControlResult = $db->rawQuery("select r_sample_control_name from r_sample_controls where r_sample_control_name='" . trim($_POST['sampleType']) . "'");
        $db = $db->where('temp_sample_id', $_POST['tempsampleId']);
        $result = $db->update($tableName, array('sample_type' => trim($_POST['sampleType'])));
    }
    echo $result;
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

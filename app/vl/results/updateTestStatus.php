<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\VlService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "form_vl";
try {

    $id = explode(",", $_POST['id']);
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'result_status'             => $_POST['status'],
            'result_approved_datetime'  =>  DateUtility::getCurrentDateTime(),
            'last_modified_datetime'     =>  DateUtility::getCurrentDateTime(),
            'data_sync'                 => 0
        );
        /* Check if already have reviewed and approved by */
        $db = $db->where('vl_sample_id', $id[$i]);
        $vlRow = $db->getOne($tableName);
        if (empty($vlRow['result_reviewed_by'])) {
            $status['result_reviewed_by'] = $_SESSION['userId'];
        }
        if (empty($vlRow['result_approved_by'])) {
            $status['result_approved_by'] = $_SESSION['userId'];
        }
        if ($_POST['status'] == '4') {
            $status['result_value_log'] = '';
            $status['result_value_absolute'] = '';
            $status['result_value_text'] = '';
            $status['result_value_absolute_decimal'] = '';
            $status['result'] = '';
            $status['is_sample_rejected'] = 'yes';
            $status['reason_for_sample_rejection'] = $_POST['rejectedReason'];
        } else {
            $status['is_sample_rejected'] = 'no';
        }




        $vlService = ContainerRegistry::get(VlService::class);
        $status['vl_result_category'] = $vlService->getVLResultCategory($status['result_status'], $vlRow['result']);
        if ($status['vl_result_category'] == 'failed' || $status['vl_result_category'] == 'invalid') {
            $status['result_status'] = 5;
        } elseif ($vldata['vl_result_category'] == 'rejected') {
            $status['result_status'] = 4;
        }

        // echo "<pre>";print_r($status);die;
        $db = $db->where('vl_sample_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];


        //Add event log
        $eventType = 'update-sample-status';
        $action = $_SESSION['userName'] . ' updated VL samples status';
        $resource = 'vl-results';
        $general->activityLog($eventType, $action, $resource);
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;

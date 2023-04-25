<?php

// echo "<pre>";print_r($_POST['hepatitisId']);die;
use App\Services\CommonService;
use App\Utilities\DateUtils;

try {
    $general = new CommonService();
    $sarr = $general->getSystemConfig();
    /* Status definition */
    $status = 6;
    if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
        $status = 9;
    }

    $query = "SELECT sample_code, remote_sample_code, facility_id, sample_batch_id, result, result_status, hepatitis_id FROM form_hepatitis";
    if ($_POST['bulkIds'] && is_array($_POST['hepatitisId'])) {
        $query .= " WHERE hepatitis_id IN (" . implode(",", $_POST['hepatitisId']) . ")";
    } else {
        $query .= " WHERE hepatitis_id = " . base64_decode($_POST['hepatitisId']);
    }
    $response = $db->rawQuery($query);


    if ($_POST['bulkIds'] && is_array($_POST['hepatitisId'])) {
        $db = $db->where("`hepatitis_id` IN (" . implode(",", $_POST['hepatitisId']) . ")");
    } else {
        $db = $db->where('hepatitis_id', base64_decode($_POST['hepatitisId']));
    }

    $db = $db->where('hepatitis_id', base64_decode($_POST['hepatitisId']));
    $db->delete('covid19_tests');

    $id = $db->update("form_hepatitis", array(
        "result"            => null,
        "sample_batch_id"   => null,
        "result_status"     => $status
    ));

    if ($id > 0 && count($response) > 0) {
        foreach ($response as $result) {
            if (isset($result['hepatitis_id']) && $result['hepatitis_id'] != "") {
                $db->insert('failed_result_retest_tracker', array(
                    'test_type_pid'         => (isset($result['hepatitis_id']) && $result['hepatitis_id'] != "") ? $result['hepatitis_id'] : null,
                    'test_type'             => 'vl',
                    'sample_code'           => (isset($result['sample_code']) && $result['sample_code'] != "") ? $result['sample_code'] : null,
                    'remote_sample_code'    => (isset($result['remote_sample_code']) && $result['remote_sample_code'] != "") ? $result['remote_sample_code'] : null,
                    'batch_id'              => (isset($result['sample_batch_id']) && $result['sample_batch_id'] != "") ? $result['sample_batch_id'] : null,
                    'facility_id'           => (isset($result['facility_id']) && $result['facility_id'] != "") ? $result['facility_id'] : null,
                    'result'                => (isset($result['result']) && $result['result'] != "") ? $result['result'] : null,
                    'result_status'         => (isset($result['result_status']) && $result['result_status'] != "") ? $result['result_status'] : null,
                    'updated_datetime'      => DateUtils::getCurrentDateTime(),
                    'update_by'             => $_SESSION['userId']
                ));
            }
        }
    }
    echo $id;
}
//catch exception
catch (Exception $e) {
    echo 'Hepatitis failed-results-retest.php: ' . $e->getMessage();
}

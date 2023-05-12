<?php

// echo "<pre>";print_r($_POST['covid19Id']);die;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

try {
    /** @var MysqliDb $db */
    $db = ContainerRegistry::get('db');

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    $sarr = $general->getSystemConfig();
    /* Status definition */
    $status = 6;
    if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
        $status = 9;
    }

    $query = "SELECT sample_code, remote_sample_code, facility_id, sample_batch_id, result, result_status, covid19_id FROM form_covid19";
    if ($_POST['bulkIds'] && is_array($_POST['covid19Id'])) {
        $query .= " WHERE covid19_id IN (" . implode(",", $_POST['covid19Id']) . ")";
    } else {
        $query .= " WHERE covid19_id = " . base64_decode($_POST['covid19Id']);
    }
    $response = $db->rawQuery($query);


    if ($_POST['bulkIds'] && is_array($_POST['covid19Id'])) {
        $db = $db->where("`covid19_id` IN (" . implode(",", $_POST['covid19Id']) . ")");
    } else {
        $db = $db->where('covid19_id', base64_decode($_POST['covid19Id']));
    }

    $db = $db->where('covid19_id', base64_decode($_POST['covid19Id']));
    $db->delete('covid19_tests');

    $id = $db->update("form_covid19", array(
        "result"            => null,
        "sample_batch_id"   => null,
        "result_status"     => $status
    ));

    if ($id > 0 && !empty($response)) {
        foreach ($response as $result) {
            if (isset($result['covid19_id']) && $result['covid19_id'] != "") {
                $db->insert('failed_result_retest_tracker', array(
                    'test_type_pid'         => (isset($result['covid19_id']) && $result['covid19_id'] != "") ? $result['covid19_id'] : null,
                    'test_type'             => 'vl',
                    'sample_code'           => (isset($result['sample_code']) && $result['sample_code'] != "") ? $result['sample_code'] : null,
                    'remote_sample_code'    => (isset($result['remote_sample_code']) && $result['remote_sample_code'] != "") ? $result['remote_sample_code'] : null,
                    'batch_id'              => (isset($result['sample_batch_id']) && $result['sample_batch_id'] != "") ? $result['sample_batch_id'] : null,
                    'facility_id'           => (isset($result['facility_id']) && $result['facility_id'] != "") ? $result['facility_id'] : null,
                    'result'                => (isset($result['result']) && $result['result'] != "") ? $result['result'] : null,
                    'result_status'         => (isset($result['result_status']) && $result['result_status'] != "") ? $result['result_status'] : null,
                    'updated_datetime'      => DateUtility::getCurrentDateTime(),
                    'update_by'             => $_SESSION['userId']
                ));
            }
        }
    }
    echo $id;
}
//catch exception
catch (Exception $e) {
    echo 'Covid-19 failed-results-retest.php: ' . $e->getMessage();
}

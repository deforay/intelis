<?php

// echo "<pre>";print_r($_POST['tbId']);die;
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

    $query = "SELECT sample_code, remote_sample_code, facility_id, sample_batch_id, result, result_status, tb_id FROM form_tb";
    if ($_POST['bulkIds'] && is_array($_POST['tbId'])) {
        $query .= " WHERE tb_id IN (" . implode(",", $_POST['tbId']) . ")";
    } else {
        $query .= " WHERE tb_id = " . base64_decode($_POST['tbId']);
    }
    $response = $db->rawQuery($query);


    if ($_POST['bulkIds'] && is_array($_POST['tbId'])) {
        $db = $db->where("`tb_id` IN (" . implode(",", $_POST['tbId']) . ")");
    } else {
        $db = $db->where('tb_id', base64_decode($_POST['tbId']));
    }
    $db->delete('tb_tests');

    $id = $db->update("form_tb", array(
        "result"            => null,
        "xpert_mtb_result"  => null,
        "sample_batch_id"   => null,
        "result_status"     => $status
    ));

    if ($id > 0 && count($response) > 0) {
        foreach ($response as $result) {
            if (isset($result['tb_id']) && $result['tb_id'] != "") {
                $db->insert('failed_result_retest_tracker', array(
                    'test_type_pid'         => (isset($result['tb_id']) && $result['tb_id'] != "") ? $result['tb_id'] : null,
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
    echo 'TB failed-results-retest.php: ' . $e->getMessage();
}

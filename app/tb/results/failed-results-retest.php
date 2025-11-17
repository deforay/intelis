<?php

// echo "<pre>";print_r($_POST['tbId']);die;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

try {
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    $sarr = $general->getSystemConfig();
    /* Status definition */
    $status = RECEIVED_AT_TESTING_LAB;
    if ($general->isSTSInstance() && $_SESSION['accessType'] == 'collection-site') {
        $status = RECEIVED_AT_CLINIC;
    }

    $query = "SELECT sample_code, remote_sample_code, facility_id, sample_batch_id, result, result_status, tb_id FROM form_tb";
    if ($_POST['bulkIds'] && is_array($_POST['tbId'])) {
        $query .= " WHERE tb_id IN (" . implode(",", $_POST['tbId']) . ")";
    } else {
        $query .= " WHERE tb_id = " . base64_decode((string) $_POST['tbId']);
    }
    $response = $db->rawQuery($query);


    if ($_POST['bulkIds'] && is_array($_POST['tbId'])) {
        $db->where("`tb_id` IN (" . implode(",", $_POST['tbId']) . ")");
        $db->delete('tb_tests');
        $db->where("`tb_id` IN (" . implode(",", $_POST['tbId']) . ")");
    } else {
        $db->where('tb_id', base64_decode((string) $_POST['tbId']));
        $db->delete('tb_tests');
        $db->where('tb_id', base64_decode((string) $_POST['tbId']));
    }

    $id = $db->update(
        "form_tb",
        ["result" => null, "xpert_mtb_result" => null, "sample_batch_id" => null, "sample_tested_datetime" => null, "result_status" => $status]
    );

    if ($id === true && !empty($response)) {
        foreach ($response as $result) {
            if (isset($result['tb_id']) && $result['tb_id'] != "") {
                $db->insert(
                    'failed_result_retest_tracker',
                    ['test_type_pid' => (isset($result['tb_id']) && $result['tb_id'] != "") ? $result['tb_id'] : null, 'test_type' => 'vl', 'sample_code' => (isset($result['sample_code']) && $result['sample_code'] != "") ? $result['sample_code'] : null, 'remote_sample_code' => (isset($result['remote_sample_code']) && $result['remote_sample_code'] != "") ? $result['remote_sample_code'] : null, 'batch_id' => (isset($result['sample_batch_id']) && $result['sample_batch_id'] != "") ? $result['sample_batch_id'] : null, 'facility_id' => (isset($result['facility_id']) && $result['facility_id'] != "") ? $result['facility_id'] : null, 'result' => (isset($result['result']) && $result['result'] != "") ? $result['result'] : null, 'result_status' => (isset($result['result_status']) && $result['result_status'] != "") ? $result['result_status'] : null, 'updated_datetime' => DateUtility::getCurrentDateTime(), 'updated_by' => $_SESSION['userId']]
                );
            }
        }
    }
    echo htmlspecialchars($id);
}
//catch exception
catch (Exception $e) {
    throw new SystemException($e->getMessage(), $e->getCode(), $e);
}

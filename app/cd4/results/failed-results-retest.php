<?php

// echo "<pre>";print_r($_POST['cd4Id']);die;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
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

    $query = "SELECT sample_code, remote_sample_code, facility_id, sample_batch_id, cd4_result, result_status, cd4_id FROM form_cd4";
    if ($_POST['bulkIds'] && is_array($_POST['cd4Id'])) {
        $query .= " WHERE cd4_id IN (" . implode(",", $_POST['cd4Id']) . ")";
    } else {
        $query .= " WHERE cd4_id = " . base64_decode((string) $_POST['cd4Id']);
    }
    $response = $db->rawQuery($query);


    if ($_POST['bulkIds'] && is_array($_POST['cd4Id'])) {
        $db->where("`cd4_id` IN (" . implode(",", $_POST['cd4Id']) . ")");
    } else {
        $db->where('cd4_id', base64_decode((string) $_POST['cd4Id']));
    }


    $id = $db->update(
        "form_cd4",
        [
            "cd4_result" => null,
            "sample_batch_id" => null,
            "sample_tested_datetime" => null,
            "result_status" => $status
        ]
    );

    if ($id > 0 && !empty($response)) {
        foreach ($response as $result) {
            if (isset($result['cd4_id']) && $result['cd4_id'] != "") {
                $db->insert(
                    'failed_result_retest_tracker',
                    ['test_type_pid' => (isset($result['cd4_id']) && $result['cd4_id'] != "") ? $result['cd4_id'] : null, 'test_type' => 'cd4', 'sample_code' => (isset($result['sample_code']) && $result['sample_code'] != "") ? $result['sample_code'] : null, 'remote_sample_code' => (isset($result['remote_sample_code']) && $result['remote_sample_code'] != "") ? $result['remote_sample_code'] : null, 'batch_id' => (isset($result['sample_batch_id']) && $result['sample_batch_id'] != "") ? $result['sample_batch_id'] : null, 'facility_id' => (isset($result['facility_id']) && $result['facility_id'] != "") ? $result['facility_id'] : null, 'result' => (isset($result['result']) && $result['cd4_result'] != "") ? $result['cd4_result'] : null, 'result_status' => (isset($result['result_status']) && $result['result_status'] != "") ? $result['result_status'] : null, 'updated_datetime' => DateUtility::getCurrentDateTime(), 'updated_by' => $_SESSION['userId']]
                );
            }
        }
    }
    echo htmlspecialchars($id);
}
//catch exception
catch (Exception $e) {
    error_log('Covid-19 failed-results-retest.php: ' . $e->getMessage());
}

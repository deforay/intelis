<?php

// echo "<pre>";print_r($_POST['covid19Id']);die;
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

    $query = "SELECT sample_code, remote_sample_code, facility_id, sample_batch_id, result, result_status, covid19_id FROM form_covid19";
    if ($_POST['bulkIds'] && is_array($_POST['covid19Id'])) {
        $query .= " WHERE covid19_id IN (" . implode(",", $_POST['covid19Id']) . ")";
    } else {
        $query .= " WHERE covid19_id = " . base64_decode((string) $_POST['covid19Id']);
    }
    $response = $db->rawQuery($query);


    if ($_POST['bulkIds'] && is_array($_POST['covid19Id'])) {
        $db->where("`covid19_id` IN (" . implode(",", $_POST['covid19Id']) . ")");
        $db->delete('covid19_tests');
        $db->where("`covid19_id` IN (" . implode(",", $_POST['covid19Id']) . ")");
    } else {
        $db->where('covid19_id', base64_decode((string) $_POST['covid19Id']));
        $db->delete('covid19_tests');
        $db->where('covid19_id', base64_decode((string) $_POST['covid19Id']));
    }

    $id = $db->update(
        "form_covid19",
        ["result" => null, "sample_batch_id" => null, "sample_tested_datetime" => null, "lot_expiration_date" => null, "lot_number" => null, "result_status" => $status]
    );

    if ($id > 0 && !empty($response)) {
        foreach ($response as $result) {
            if (isset($result['covid19_id']) && $result['covid19_id'] != "") {
                $db->insert(
                    'failed_result_retest_tracker',
                    ['test_type_pid' => (isset($result['covid19_id']) && $result['covid19_id'] != "") ? $result['covid19_id'] : null, 'test_type' => 'covid19', 'sample_code' => (isset($result['sample_code']) && $result['sample_code'] != "") ? $result['sample_code'] : null, 'remote_sample_code' => (isset($result['remote_sample_code']) && $result['remote_sample_code'] != "") ? $result['remote_sample_code'] : null, 'batch_id' => (isset($result['sample_batch_id']) && $result['sample_batch_id'] != "") ? $result['sample_batch_id'] : null, 'facility_id' => (isset($result['facility_id']) && $result['facility_id'] != "") ? $result['facility_id'] : null, 'result' => (isset($result['result']) && $result['result'] != "") ? $result['result'] : null, 'result_status' => (isset($result['result_status']) && $result['result_status'] != "") ? $result['result_status'] : null, 'updated_datetime' => DateUtility::getCurrentDateTime(), 'updated_by' => $_SESSION['userId']]
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

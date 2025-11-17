<?php

// echo "<pre>";print_r($_POST['hepatitisId']);die;
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

    $query = "SELECT sample_code, remote_sample_code, facility_id, sample_batch_id, result, result_status, hepatitis_id FROM form_hepatitis";
    if ($_POST['bulkIds'] && is_array($_POST['hepatitisId'])) {
        $query .= " WHERE hepatitis_id IN (" . implode(",", $_POST['hepatitisId']) . ")";
    } else {
        $query .= " WHERE hepatitis_id = " . base64_decode((string) $_POST['hepatitisId']);
    }
    $response = $db->rawQuery($query);


    if ($_POST['bulkIds'] && is_array($_POST['hepatitisId'])) {
        $db->where("`hepatitis_id` IN (" . implode(",", $_POST['hepatitisId']) . ")");
    } else {
        $db->where('hepatitis_id', base64_decode((string) $_POST['hepatitisId']));
    }

    //$db->where('hepatitis_id', base64_decode((string) $_POST['hepatitisId']));
    //$db->delete('covid19_tests');

    $id = $db->update(
        "form_hepatitis",
        ["result" => null, "sample_batch_id" => null, "sample_tested_datetime" => null, "lot_expiration_date" => null, "lot_number" => null, "result_status" => $status]
    );

    if ($id > 0 && !empty($response)) {
        foreach ($response as $result) {
            if (isset($result['hepatitis_id']) && $result['hepatitis_id'] != "") {
                $db->insert(
                    'failed_result_retest_tracker',
                    ['test_type_pid' => (isset($result['hepatitis_id']) && $result['hepatitis_id'] != "") ? $result['hepatitis_id'] : null, 'test_type' => 'vl', 'sample_code' => (isset($result['sample_code']) && $result['sample_code'] != "") ? $result['sample_code'] : null, 'remote_sample_code' => (isset($result['remote_sample_code']) && $result['remote_sample_code'] != "") ? $result['remote_sample_code'] : null, 'batch_id' => (isset($result['sample_batch_id']) && $result['sample_batch_id'] != "") ? $result['sample_batch_id'] : null, 'facility_id' => (isset($result['facility_id']) && $result['facility_id'] != "") ? $result['facility_id'] : null, 'result' => (isset($result['result']) && $result['result'] != "") ? $result['result'] : null, 'result_status' => (isset($result['result_status']) && $result['result_status'] != "") ? $result['result_status'] : null, 'updated_datetime' => DateUtility::getCurrentDateTime(), 'updated_by' => $_SESSION['userId']]
                );
            }
        }
    }
    echo htmlspecialchars($id);
} catch (Exception $e) {
    throw new SystemException($e->getMessage(), $e->getCode(), $e);
}

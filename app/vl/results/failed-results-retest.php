<?php

use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

try {
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get('db');

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    // Sanitized values from $request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = $GLOBALS['request'];
    $_POST = $request->getParsedBody();

    $sarr = $general->getSystemConfig();
    /* Status definition */
    $status = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
    if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
        $status = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
    }
    $query = "SELECT sample_code,
                    remote_sample_code,
                    facility_id,
                    sample_batch_id,
                    result,
                    result_status,
                    vl_sample_id FROM form_vl";
    if ($_POST['bulkIds'] && is_array($_POST['vlId'])) {
        $query .= " WHERE vl_sample_id IN (" . implode(",", $_POST['vlId']) . ")";
    } else {
        $query .= " WHERE vl_sample_id = " . base64_decode((string) $_POST['vlId']);
    }
    $response = $db->rawQuery($query);

    if ($_POST['bulkIds'] && is_array($_POST['vlId'])) {
        $db->where("`vl_sample_id` IN (" . implode(",", $_POST['vlId']) . ")");
    } else {
        $db->where('vl_sample_id', base64_decode((string) $_POST['vlId']));
    }
    $id = $db->update(
        "form_vl",
        [
            "result_value_log" => null,
            "result_value_absolute" => null,
            "result_value_text" => null,
            "result_value_absolute_decimal" => null,
            "result" => null,
            "sample_batch_id" => null,
            "result_status" => $status
        ]
    );

    if ($id === true && !empty($response)) {
        foreach ($response as $result) {
            if (isset($result['vl_sample_id']) && $result['vl_sample_id'] != "") {
                $db->insert(
                    'failed_result_retest_tracker',
                    [
                        'test_type_pid' => $result['vl_sample_id'] ?? null,
                        'test_type' => 'vl',
                        'sample_code' => $result['sample_code'] ?? null,
                        'remote_sample_code' => $result['remote_sample_code'] ?? null,
                        'batch_id' => $result['sample_batch_id'] ?? null,
                        'facility_id' => $result['facility_id'] ?? null,
                        'result' => $result['result'] ?? null,
                        'result_status' => $result['result_status'] ?? null,
                        'updated_datetime' => DateUtility::getCurrentDateTime(),
                        'updated_by' => $_SESSION['userId']
                    ]
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

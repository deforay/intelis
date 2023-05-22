<?php

// echo "<pre>";print_r($_POST['eidId']);die;

use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

try {
    /** @var MysqliDb $db */
    $db = ContainerRegistry::get('db');

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    // Sanitized values from $request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = $GLOBALS['request'];
    $_POST = $request->getParsedBody();

    $sarr = $general->getSystemConfig();
    /* Status definition */
    $status = 6;
    if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
        $status = 9;
    }

    $query = "SELECT sample_code, remote_sample_code, facility_id, sample_batch_id, result, result_status, eid_id FROM form_eid";
    if ($_POST['bulkIds'] && is_array($_POST['eidId'])) {
        $query .= " WHERE eid_id IN (" . implode(",", $_POST['eidId']) . ")";
    } else {
        $query .= " WHERE eid_id = " . base64_decode($_POST['eidId']);
    }
    $response = $db->rawQuery($query);


    if ($_POST['bulkIds'] && is_array($_POST['eidId'])) {
        $db = $db->where("`eid_id` IN (" . implode(",", $_POST['eidId']) . ")");
    } else {
        $db = $db->where('eid_id', base64_decode($_POST['eidId']));
    }
    $id = $db->update("form_eid", array(
        "result"            => null,
        "sample_batch_id"   => null,
        "result_status"     => $status
    ));

    if ($id > 0 && !empty($response)) {
        foreach ($response as $result) {
            if (isset($result['eid_id']) && $result['eid_id'] != "") {
                $db->insert('failed_result_retest_tracker', array(
                    'test_type_pid'         => (isset($result['eid_id']) && $result['eid_id'] != "") ? $result['eid_id'] : null,
                    'test_type'             => 'eid',
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
    echo htmlspecialchars($id);
} catch (Exception $e) {
    throw new SystemException($e->getMessage(), $e->getCode(), $e);
}

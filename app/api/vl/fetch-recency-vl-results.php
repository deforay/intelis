<?php

use App\Services\CommonService;
use App\Services\UserService;

session_unset(); // no need of session in json response

// PURPOSE : Fetch Results using external_sample_code field which is used to
// store the recency id from third party apps (for eg. in DRC)

// external_sample_code field in db was unused so we decided to use it to store recency id

ini_set('memory_limit', -1);

$db = \MysqliDb::getInstance();

$general = new CommonService();
$userDb = new UserService();

$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];

$transactionId = $general->generateUUID();
$auth = $general->getHeader('Authorization');
$authToken = str_replace("Bearer ", "", $auth);
$user = $userDb->getUserFromToken($authToken);
$sampleCode = !empty($_REQUEST['s']) ? explode(",", $db->escape($_REQUEST['s'])) : null;
$recencyId = !empty($_REQUEST['r']) ? explode(",", $db->escape($_REQUEST['r'])) : null;
$from = !empty($_REQUEST['f']) ? $db->escape($_REQUEST['f']) : null;
$to = !empty($_REQUEST['t']) ? $db->escape($_REQUEST['t']) : null;
$orderSortType = !empty($_REQUEST['orderSortType']) ? $db->escape($_REQUEST['orderSortType']) : null;

if (!$sampleCode && !$recencyId && (!$from || !$to)) {
    http_response_code(400);
    throw new Exception("Mandatory request params missing in request. Expected Recency ID(s) or a Date Range");
}

try {

    $sQuery = "SELECT vl.sample_code, 
                    vl.remote_sample_code,
                    vl.external_sample_code as `recency_id`,
                    vl.sample_collection_date,
                    vl.sample_received_at_vl_lab_datetime,
                    vl.sample_registered_at_lab,
                    vl.sample_tested_datetime,
                    vl.is_sample_rejected,
                    vl.result,
                    vl.result_value_log,
                    samptype.sample_name as `specimen_type`,
                    sampstatus.status_name as `sample_status`,
                    f.facility_name as `collection_facility_name`,
                    lab.facility_name as `testing_lab_name`,
                    testreason.test_reason_name as `reason_for_testing`,
                    rejreason.rejection_reason_name as `rejection_reason`

                    FROM form_vl as vl 
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                    LEFT JOIN facility_details as lab ON vl.lab_id=lab.facility_id 
                    LEFT JOIN r_vl_sample_type as samptype ON samptype.sample_id=vl.sample_type 
                    INNER JOIN r_sample_status as sampstatus ON sampstatus.status_id=vl.result_status 
                    LEFT JOIN r_vl_test_reasons as testreason ON testreason.test_reason_id=vl.reason_for_vl_testing 
                    LEFT JOIN r_vl_sample_rejection_reasons as rejreason ON rejreason.rejection_reason_id=vl.reason_for_sample_rejection
                    
                    WHERE (external_sample_code is not null)";



    if (!empty($recencyId)) {
        $recencyId = implode("','", $recencyId);
        $sQuery .= " AND external_sample_code IN ('$recencyId') ";
    }

    if (!empty($sampleCode)) {
        $sampleCode = implode("','", $sampleCode);
        $sQuery .= " AND sample_code IN ('$sampleCode') ";
    }

    if (!empty($from) && !empty($to)) {
        $sQuery .= " AND DATE(last_modified_datetime) between '$from' AND '$to' ";
    }

    if (empty($orderSortType)) {
        $orderSortType = 'ASC'; // if Order Sort Type is not defined we treat it as ASC by default
    }

    $sQuery .= " ORDER BY last_modified_datetime $orderSortType ";
    $rowData = $db->rawQuery($sQuery);

    // No data found
    if (!$rowData) {
        http_response_code(200);
        throw new Exception("No Matching Data");
    }

    $payload = array(
        'status' => 'success',
        'timestamp' => time(),
        'data' => $rowData
    );

    http_response_code(200);
} catch (Exception $exc) {

    // http_response_code(500);
    $payload = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => array()
    );

    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

echo json_encode($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($rowData), 'fetch-recency-vl-result', 'vl', $requestUrl, $_REQUEST, $payload, 'json');

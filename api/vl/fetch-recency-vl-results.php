<?php

session_unset(); // no need of session in json response

// PURPOSE : Fetch Results using serial_no field which is used to
// store the recency id from third party apps (for eg. in DRC)

// serial_no field in db was unused so we decided to use it to store recency id

ini_set('memory_limit', -1);
header('Content-Type: application/json');

$general = new \Vlsm\Models\General($db);
$userDb = new \Vlsm\Models\Users($db);
$user = null;
// The request has to send an Authorization Bearer token 
$auth = $general->getHeader('Authorization');
if (!empty($auth)) {
    $authToken = str_replace("Bearer ", "", $auth);
    /* Check if API token exists */
    $user = $userDb->getAuthToken($authToken);
}

// If authentication fails then do not proceed
if (empty($user) || empty($user['user_id'])) {
    $response = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => 'Bearer Token Invalid',
        'data' => array()
    );
    http_response_code(401);
    echo json_encode($response);
    exit(0);
}

$sampleCode = !empty($_REQUEST['s']) ? explode(",", $db->escape($_REQUEST['s'])) : null;
$recencyId = !empty($_REQUEST['r']) ? explode(",", $db->escape($_REQUEST['r'])) : null;
$from = !empty($_REQUEST['f']) ? $db->escape($_REQUEST['f']) : null;
$to = !empty($_REQUEST['t']) ? $db->escape($_REQUEST['t']) : null;;
$orderSortType = !empty($_REQUEST['orderSortType']) ? $db->escape($_REQUEST['orderSortType']) : null;;

if (!$sampleCode && !$recencyId && (!$from || !$to)) {
    $response = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => 'Mandatory request params missing in request. Expected Recency ID(s) or a Date Range',
        'data' => array()
    );
    if (isset($user['token-updated']) && $user['token-updated'] == true) {
        $response['token'] = $user['newToken'];
    }
    http_response_code(400);
    echo json_encode($response);
    exit(0);
}

try {

    $sQuery = "SELECT vl.sample_code, 
                    vl.remote_sample_code,
                    vl.serial_no as `recency_id`,
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

                    FROM vl_request_form as vl 
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                    LEFT JOIN facility_details as lab ON vl.lab_id=lab.facility_id 
                    LEFT JOIN r_vl_sample_type as samptype ON samptype.sample_id=vl.sample_type 
                    INNER JOIN r_sample_status as sampstatus ON sampstatus.status_id=vl.result_status 
                    LEFT JOIN r_vl_test_reasons as testreason ON testreason.test_reason_id=vl.reason_for_vl_testing 
                    LEFT JOIN r_vl_sample_rejection_reasons as rejreason ON rejreason.rejection_reason_id=vl.reason_for_sample_rejection
                    
                    WHERE (serial_no is not null)";



    if (!empty($recencyId)) {
        $recencyId = implode("','", $recencyId);
        $sQuery .= " AND serial_no IN ('$recencyId') ";
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
        $response = array(
            'status' => 'failed',
            'timestamp' => time(),
            'error' => 'No matching data',
            'data' => $rowData

        );
        if (isset($user['token-updated']) && $user['token-updated'] == true) {
            $response['token'] = $user['newToken'];
        }
        http_response_code(200);
        echo json_encode($response);
        exit(0);
    }

    $payload = array(
        'status' => 'success',
        'timestamp' => time(),
        'data' => $rowData
    );
    if (isset($user['token_updated']) && $user['token_updated'] == true) {
        $payload['token'] = $user['new_token'];
    } else {
        $payload['token'] = null;
    }

    http_response_code(200);
    echo json_encode($payload);
    exit(0);
} catch (Exception $exc) {

    http_response_code(500);
    $payload = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => array()
    );
    if (isset($user['token_updated']) && $user['token_updated'] == true) {
        $payload['token'] = $user['new_token'];
    } else {
        $payload['token'] = null;
    }

    echo json_encode($payload);

    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    exit(0);
}

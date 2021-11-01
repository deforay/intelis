<?php
// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}
session_unset(); // no need of session in json response

// PURPOSE : Fetch Results using serial_no field which is used to
// store the recency id from third party apps (for eg. in DRC)

// serial_no field in db was unused so we decided to use it to store recency id

ini_set('memory_limit', -1);
header('Content-Type: application/json');

$general = new \Vlsm\Models\General($db);
$userDb = new \Vlsm\Models\Users($db);
$facilityDb = new \Vlsm\Models\Facilities($db);
$c19Db = new \Vlsm\Models\Covid19($db);
$app = new \Vlsm\Models\App($db);
$user = null;
$input = json_decode(file_get_contents("php://input"), true);
/* echo "<pre>";
print_r($input);
die; */
/* For API Tracking params */
$requestUrl = $_SERVER['REQUEST_URI'];
$params = file_get_contents("php://input");

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
// print_r($user);die;

try {

    $sQuery = "SELECT 
                        vl.covid19_id as covid19Id,
                        CONCAT_WS('',vl.sample_code, vl.remote_sample_code) as sampleCode,
                        vl.facility_id as facilityId,
                        vl.patient_id as patientId,
                        CONCAT_WS(' ',vl.patient_name, vl.patient_surname) as patientFullName,
                        vl.app_sample_code as appSampleCode,
                        vl.patient_age as age,
                        vl.patient_gender as gender,
                        vl.patient_address as address,
                        vl.patient_phone_number as phone,
                        vl.sample_collection_date as sampleCollectionDate,
                        vl.sample_tested_datetime as sampleTestedDate,
                        vl.tested_by as testedById,
                        vl.lab_id as labId,
                        l_f.facility_name as labName,
                        vl.result,
                        vl.sample_received_at_vl_lab_datetime as sampleReceivedDate,
                        vl.is_sample_rejected as sampleRejected,
                        vl.is_result_authorised as isAuthorised,
                        vl.approver_comments as approverComments,
                        vl.request_created_datetime as requestedDate,
                        vl.result_printed_datetime as resultPrintedDate,
                        vl.testing_point as testingPoint,
                        vl.authorized_on as authorisedOn,
                        vl.authorized_by as authorisedBy,
                        vl.sample_condition as sampleCondition,
                        u_d.user_name as reviewedBy,
                        lt_u_d.user_name as labTechnician,
                        t_b.user_name as testedBy,
                        rs.rejection_reason_name as rejectionReason,
                        vl.rejection_on as rejectionDate,
                        ts.status_name as statusName
                        
                        FROM form_covid19 as vl 
                        
                        LEFT JOIN r_countries as c ON vl.patient_nationality=c.id
                        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                        LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
                        LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
                        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
                        LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
                        LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
                        LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.lab_technician 
                        LEFT JOIN user_details as t_b ON t_b.user_id=vl.tested_by 
                        LEFT JOIN r_covid19_test_reasons as rtr ON rtr.test_reason_id=vl.reason_for_covid19_test 
                        LEFT JOIN r_covid19_sample_type as rst ON rst.sample_id=vl.specimen_type 
                        LEFT JOIN r_covid19_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
                        LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
                        LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";


    $where = "";
    if (!empty($user)) {
        $facilityMap = $facilityDb->getFacilityMap($user['user_id'], 1);
        if (!empty($facilityMap)) {
            if (isset($where) && trim($where) != "") {
                $where .= " AND ";
            } else {
                $where .= " WHERE ";
            }
            $where .= " vl.facility_id IN (" . $facilityMap . ")";
        } else {
            $where = " WHERE request_created_by = '" . $user['user_id'] . "'";
        }
    }
    /* To check the sample code filter */
    $sampleCode = $input['sampleCode'];
    if (!empty($sampleCode)) {
        $sampleCode = implode("','", $sampleCode);

        if (isset($where) && trim($where) != "") {
            $where .= " AND ";
        } else {
            $where .= " WHERE ";
        }
        $where .= " AND (sample_code IN ('$sampleCode') OR remote_sample_code IN ('$sampleCode') )";
    }
    /* To check the facility and date range filter */
    $from = $input['sampleCollectionDate'][0];
    $to = $input['sampleCollectionDate'][1];
    $facilityId = $input['facility'];
    if (!empty($from) && !empty($to) && !empty($facilityId)) {
        if (isset($where) && trim($where) != "") {
            $where .= " AND ";
        } else {
            $where .= " WHERE ";
        }
        $where .= " AND DATE(sample_collection_date) between '$from' AND '$to' ";

        $facilityId = implode("','", $facilityId);
        $where .= " AND vl.facility_id IN ('$facilityId') ";
    }

    // $sQuery .= " ORDER BY sample_collection_date ASC ";
    $sQuery .= $where;
    // die($sQuery);
    $rowData = $db->rawQuery($sQuery);

    // No data found
    if (!$rowData) {
        // array_splice($rowData, 1, 2);
        $response = array(
            'status' => 'success',
            'timestamp' => time(),
            'error' => 'No matching data',
            'data' => $rowData

        );

        $app = new \Vlsm\Models\App($db);
        $trackId = $app->addApiTracking($user['user_id'], count($rowData), 'fetch-results', 'covid19', $requestUrl, $params, 'json');
        http_response_code(200);
        echo json_encode($response);
        exit(0);
    }

    foreach ($rowData as $key => $row) {
        $rowData[$key]['c19Tests'] = $app->getCovid19TestsCamelCaseByFormId($row['covid19Id']);
    }
    $payload = array(
        'status' => 'success',
        'timestamp' => time(),
        'data' => $rowData
    );
    // if (isset($user['token_updated']) && $user['token_updated'] == true) {
    //     $payload['token'] = $user['new_token'];
    // }
    $app = new \Vlsm\Models\App($db);
    $trackId = $app->addApiTracking($user['user_id'], count($rowData), 'fetch-results', 'covid19', $requestUrl, $params, 'json');

    http_response_code(200);
    echo json_encode($payload);
    exit(0);
} catch (Exception $exc) {

    http_response_code(500);
    $payload = array(
        'status' => 'success',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => array()
    );
    echo json_encode($payload);

    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    exit(0);
}

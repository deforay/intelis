<?php

use App\Models\App;
use App\Models\Facilities;
use App\Models\General;
use App\Models\Tb;
use App\Models\Users;

session_unset(); // no need of session in json response

ini_set('memory_limit', -1);

$db = \MysqliDb::getInstance();

$general = new General();
$userDb = new Users();
$facilityDb = new Facilities();
$tbDb = new Tb();
$app = new App();
$arr = $general->getGlobalConfig();
$user = null;
$input = json_decode(file_get_contents("php://input"), true);
/* For API Tracking params */
$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];
$params = file_get_contents("php://input");

// The request has to send an Authorization Bearer token 
$auth = $general->getHeader('Authorization');
if (!empty($auth)) {
    $authToken = str_replace("Bearer ", "", $auth);
    /* Check if API token exists */
    $user = $userDb->getAuthToken($authToken);
}
try {
    // If authentication fails then do not proceed
    if (empty($user) || empty($user['user_id'])) {
        http_response_code(401);
        throw new Exception('Bearer Token Invalid');
    }
    $transactionId = $general->generateUUID();
    $sQuery = "SELECT 
        vl.app_sample_code                      as appSampleCode,
        vl.unique_id                            as uniqueId,
        vl.tb_id                                as tbId,
        vl.sample_code                          as sampleCode,
        vl.sample_reordered                     as sampleReordered,
        vl.referring_unit                       as referringUnit,
        vl.remote_sample_code                   as remoteSampleCode,
        vl.facility_id                          as facilityId,
        f.facility_name                         as facilityName,
        vl.sample_requestor_name                as sampleRequestorName,
        vl.sample_requestor_phone               as sampleRequestorPhone,
        vl.specimen_quality                     as specimenQuality,
        vl.lab_id                               as labId,
        vl.patient_id                           as patientId,
        vl.patient_name                         as firstName,
        vl.patient_surname                      as lastName,
        vl.patient_dob                          as patientDob,
        vl.patient_gender                       as patientGender,
        vl.patient_age                          as patientAge,
        vl.patient_address                      as patientAddress,
        vl.patient_type                         as patientType,
        vl.hiv_status                           as hivStatus,
        vl.reason_for_tb_test                   as reasonFortbTest,
        vl.tests_requested                      as testTypeRequested,
        vl.specimen_type                        as specimenType,
        vl.sample_collection_date               as sampleCollectionDate,
        vl.sample_received_at_lab_datetime      as sampleReceivedDate,
        vl.sample_dispatched_datetime           as sampleDispatchedDate,
        vl.is_sample_rejected                   as isSampleRejected,
        vl.result                               as result,
        vl.rejection_on                         as rejectionDate,
        vl.result_status                        as resultStatus,
        vl.sample_tested_datetime               as sampleTestedDate,
        l_f.facility_name                       as labName,
        vl.result,
        vl.xpert_mtb_result                     as xpertMtbResult,
        vl.is_sample_rejected                   as sampleRejected,
        vl.lab_tech_comments                    as approverComments,
        vl.request_created_datetime             as requestedDate,
        vl.result_printed_datetime              as resultPrintedDate,
        vl.last_modified_datetime               as updatedOn,
        u_d.user_name                           as reviewedBy,
        vl.lab_technician                       as labTechnician,
        vl.lab_reception_person                 as labReceptionPerson,
        lt_u_d.user_name                        as labTechnicianName,
        vl.tested_by                            as testedBy,
        t_b.user_name                           as testedByName,
        rs.rejection_reason_name                as rejectionReason,
        vl.rejection_on                         as rejectionDate,
        vl.funding_source                       as fundingSource,
        r_f_s.funding_source_name               as fundingSourceName,
        vl.implementing_partner                 as implementingPartner,
        r_i_p.i_partner_name                    as implementingPartnerName,
        gdp.geo_name                            as province,
        gdp.geo_id                              as provinceId,
        ts.status_name                          as resultStatusName,
        vl.revised_by                           as revisedBy,
        r_r_b.user_name                         as revisedByName,
        vl.revised_on                           as revisedOn,
        vl.reason_for_changing                  as reasonFortbResultChanges
        
        FROM form_tb as vl 
        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
        LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
        LEFT JOIN geographical_divisions as gdd ON f.facility_district_id=gdd.geo_id
        LEFT JOIN geographical_divisions as gdp ON vl.province_id=gdp.geo_id
        LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
        LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
        LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
        LEFT JOIN user_details as r_r_b ON r_r_b.user_id=vl.revised_by 
        LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.lab_technician 
        LEFT JOIN user_details as t_b ON t_b.user_id=vl.tested_by 
        LEFT JOIN r_tb_sample_type as rst ON rst.sample_id=vl.specimen_type 
        LEFT JOIN r_tb_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
        LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
        LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";


    $where = [];
    if (!empty($user)) {
        $facilityMap = $facilityDb->getUserFacilityMap($user['user_id'], 1);
        if (!empty($facilityMap)) {
            $where[] = " vl.facility_id IN (" . $facilityMap . ")";
        } else {
            $where[] = " (request_created_by = '" . $user['user_id'] . "')";
        }
    }

    /* To check the uniqueId filter */
    $uniqueId = $input['uniqueId'] ?? [];
    if (!empty($uniqueId)) {
        $uniqueId = implode("','", $uniqueId);
        $where[] = " unique_id IN ('$uniqueId')";
    }

    /* To check the sample code filter */
    $sampleCode = $input['sampleCode'] ?? [];
    if (!empty($sampleCode)) {
        $sampleCode = implode("','", $sampleCode);
        $where[] = " (sample_code IN ('$sampleCode') OR remote_sample_code IN ('$sampleCode') )";
    }
    /* To check the facility and date range filter */
    $from = $input['sampleCollectionDate'][0];
    $to = $input['sampleCollectionDate'][1];
    if (!empty($from) && !empty($to)) {
        $where[] = " DATE(sample_collection_date) between '$from' AND '$to' ";
    }

    if (!empty($input['lastModifiedDateTime'])) {
        $where[] = " DATE(vl.request_created_datetime) >= '" . date('Y-m-d', strtotime($input['lastModifiedDateTime'])) . "'";
    }

    $facilityId = $input['facility'] ?? [];
    if (!empty($facilityId)) {
        $facilityId = implode("','", $facilityId);
        $where[] = " vl.facility_id IN ('$facilityId') ";
    }
    if (!empty($input['patientId'])) {
        $patientId = implode("','", $input['patientId']);
        $where[] = " vl.patient_id IN ('" . $patientId . "') ";
    }

    if (!empty($input['patientName'])) {
        $where[] = " (vl.patient_name like '" . $input['patientName'] . "' OR vl.patient_surname like '" . $input['patientName'] . "')";
    }

    $sampleStatus = $input['sampleStatus'];
    if (!empty($sampleStatus)) {
        $sampleStatus = implode("','", $sampleStatus);
        $where[] = " result_status IN ('$sampleStatus') ";
    }

    $where = " WHERE " . implode(" AND ", $where);
    $sQuery .= $where . " limit 100;";
    // // die($sQuery);
    $rowData = $db->rawQuery($sQuery);

    // No data found
    if (!$rowData) {
        $payload = array(
            'status' => 'success',
            'timestamp' => time(),
            'error' => 'No matching data',
            'data' => $rowData

        );
        http_response_code(200);
    } else {
        foreach ($rowData as $key => $row) {
            $rowData[$key]['tbTests'] = $tbDb->getTbTestsByFormId($row['tbId']);
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
    }
} catch (Exception $exc) {
    $payload = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => array()
    );
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
$payload = json_encode($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($rowData), 'fetch-results', 'tb', $_SERVER['REQUEST_URI'], $params, $payload, 'json');
echo $payload;
// exit(0); 

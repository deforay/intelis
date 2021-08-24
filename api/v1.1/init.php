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
header('Content-Type: application/json');

$general = new \Vlsm\Models\General($db);
$app = new \Vlsm\Models\App($db);
$userDb = new \Vlsm\Models\Users($db);
$facilitiesDb = new \Vlsm\Models\Facilities($db);
$geoLocationDb = new \Vlsm\Models\GeoLocations($db);

$input = json_decode(file_get_contents("php://input"), true);
$formId = $general->getGlobalConfig('vl_form');
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
/* Status name list */
$statusList = array();
$tsQuery = "SELECT * FROM r_sample_status where status = 'active'";
$tsResult = $db->rawQuery($tsQuery);
foreach ($tsResult as $row) {
    $statusList[$row['status_id']] = $row['status_name'];
}
// Check if covid-19 module active/inactive
$status = false;
/* Funding Source List */
$fundingSourceList = array();
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceResult = $db->query($fundingSourceQry);
foreach ($fundingSourceResult as $funding) {
    $fundingSourceList[$funding['funding_source_id']] = $funding['funding_source_name'];
}
/* Implementing Partner Details */
$implementingPartnerList = array();
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerResult = $db->query($implementingPartnerQry);
foreach ($implementingPartnerResult as $key => $ip) {
    $implementingPartnerList[$key]['value'] = strtolower(str_replace(" ", "-", $ip['i_partner_id']));
    $implementingPartnerList[$key]['show'] = $ip['i_partner_name'];
}
/* Nationality Details */
$nationalityQry = "SELECT * FROM `r_countries` ORDER BY `iso_name` ASC";
$nationalityResult = $db->query($nationalityQry);
foreach ($nationalityResult as $key => $nrow) {
    $nationalityList[$key]['show'] = ucwords($nrow['iso_name']) . ' (' . $nrow['iso3'] . ')';
    $nationalityList[$key]['value'] = $nrow['id'];
}
$commonResultsList = array();
$commonResult = array('positive', 'negative', 'unknown');
foreach ($commonResult as $key => $result) {
    $commonResultsList[$key]['value'] = $result;
    $commonResultsList[$key]['show'] = ucwords($result);
}
/* Lab Technician Details */
$labTechnicians = $userDb->getActiveUserInfo();
foreach ($labTechnicians as $labTech) {
    $labTechniciansList[$labTech['user_id']] = ucwords($labTech['user_name']);
}
$data = array();
$data['facilitiesList'] = $app->getAppHealthFacilities(null, $user['user_id']);
$data['geoGraphicalDivision'] = $geoLocationDb->fetchActiveGeolocations();
$data['healthFacilitiesList'] = $app->getAppHealthFacilities(null, $user['user_id'], true, 1, false);
$data['testingLabsList'] = $app->getTestingLabs(null, $user['user_id'], false, false);

if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
    $covid19Obj = new \Vlsm\Models\Covid19($db);

    if (isset($formId) && $formId == 1) {
        /* Source of Alert list */
        $sourceOfAlertList = array();
        $sourceOfAlert = array('Hotline', 'Community Surveillance', 'POE', 'Contact Tracing', 'Clinic', 'Sentinel Site', 'Screening', 'Others');
        foreach ($sourceOfAlert as $key => $src) {
            $sourceOfAlertList[$key]['value'] = strtolower(str_replace(" ", "-", $src));
            $sourceOfAlertList[$key]['show'] = $src;
        }
        $data['covid19']['sourceOfAlertList'] = $sourceOfAlertList;
    }
    /* Province Details */
    $data['covid19']['provinceList'] = $app->getProvinceDetails($user['user_id'], true);
    /* District Details */
    $data['covid19']['districtList'] = $app->getDistrictDetails($user['user_id'], true);
    /* Health Facility Details */
    // $data['covid19']['healthFacilitiesList'] = $app->getAppHealthFacilities('covid19', $user['user_id'], true, 1, true);
    $data['covid19']['fundingSourceList'] = $app->generateSelectOptions($fundingSourceList);
    $data['covid19']['implementingPartnerList'] = $implementingPartnerList;
    $data['covid19']['nationalityList'] = $nationalityList;

    /* Type of Test Request */
    $typeOfTestReqList = array();
    $typeOfTestReqResult = array('Real Time RT-PCR', 'RDT-Antibody', 'RDT-Antigen', 'ELISA', 'Others');
    foreach ($typeOfTestReqResult as $key => $req) {
        $typeOfTestReqList[$key]['value'] = $req;
        $typeOfTestReqList[$key]['show'] = $req;
    }
    $data['covid19']['typeOfTestRequestList'] = $typeOfTestReqList;

    $data['covid19']['covid19ReasonsForTestingList'] = $app->generateSelectOptions($covid19Obj->getCovid19ReasonsForTesting());
    $data['covid19']['specimenTypeResultList'] = $app->generateSelectOptions($covid19Obj->getCovid19SampleTypes());
    foreach (range(1, 5) as $key => $req) {
        $typeOfTestReqList[$key]['value'] = $req;
        $typeOfTestReqList[$key]['show'] = $req;
    }
    $data['covid19']['testingPoint'] = $typeOfTestReqList;
    // $data['covid19']['testingLabsList'] = $app->getTestingLabs('covid19', null, true);
    /* Type of Test Request */
    $qualityList = array();
    $qualityResults = array('Good', 'Poor');
    foreach ($qualityResults as $key => $req) {
        $qualityList[$key]['value'] = strtolower($req);
        $qualityList[$key]['show'] = $req;
    }
    $data['covid19']['qualityList'] = $qualityList;

    /* Rejected Reason*/
    $rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_covid19_sample_rejection_reasons WHERE rejection_reason_status ='active' GROUP BY rejection_type";
    $rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
    $rejectionReason = array();
    foreach ($rejectionTypeResult as $key => $type) {
        $rejectionReason[$key]['show'] = ucwords($type['rejection_type']);
        $rejectionQuery = "SELECT * FROM r_covid19_sample_rejection_reasons where rejection_reason_status = 'active' AND rejection_type LIKE '" . $type['rejection_type'] . "%'";
        $rejectionResult = $db->rawQuery($rejectionQuery);
        foreach ($rejectionResult as $subKey => $reject) {
            $rejectionReason[$key]['reasons'][$subKey]['value'] = $reject['rejection_reason_id'];
            $rejectionReason[$key]['reasons'][$subKey]['show'] = ucwords($reject['rejection_reason_name']);
        }
    }
    $data['covid19']['rejectedReasonList'] = $rejectionReason;

    /* Testing Platform Details */
    $testPlatformList = array();
    $testPlatformResult = $general->getTestingPlatforms('covid19');
    foreach ($testPlatformResult as $row) {
        $testPlatformList[$row['machine_name']] = $row['machine_name'];
    }
    $data['covid19']['testPlatformList'] = $app->generateSelectOptions($testPlatformList);

    /* Lab Technician Details */
    $labTechnicians = $userDb->getActiveUserInfo();
    foreach ($labTechnicians as $labTech) {
        $labTechniciansList[$labTech['user_id']] = ucwords($labTech['user_name']);
    }

    $data['covid19']['labTechniciansList'] = $app->generateSelectOptions($labTechniciansList);
    $data['covid19']['resultsList'] = $app->generateSelectOptions($covid19Obj->getCovid19Results());
    $data['covid19']['symptomsList'] = $app->generateSelectOptions($covid19Obj->getCovid19Symptoms());
    $data['covid19']['comorbiditiesList'] = $app->generateSelectOptions($covid19Obj->getCovid19Comorbidities());
    $data['covid19']['sampleStatusList'] = $app->generateSelectOptions($statusList);

    /* Get covid-19 tests */
    $data['covid19']['covid19Tests'] = $covid19Obj->getCovid19TestsByFormId();
    $data['covid19']['statusFilterList'] = array(
        array('value' => '7', 'show' => 'Approved'),
        array('value' => '1', 'show' => 'Pending'),
        array('value' => '4', 'show' => 'Rejected')
    );
    $status = true;
}

// Check if eid module active/inactive
if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
    $eidObj = new \Vlsm\Models\Eid($db);
    /* SITE INFORMATION SECTION */
    /* Province Details */
    $data['eid']['provinceList'] = $app->getProvinceDetails($user['user_id'], true);
    /* District Details */
    $data['eid']['districtList'] = $app->getDistrictDetails($user['user_id'], true);
    /* Health Facility Details */
    // $data['eid']['healthFacilitiesList'] = $app->getAppHealthFacilities('eid', $user['user_id'], true, 1, true);
    $data['eid']['implementingPartnerList'] = $implementingPartnerList;
    $data['eid']['fundingSourceList'] = $app->generateSelectOptions($fundingSourceList);
    $data['eid']['nationalityList'] = $nationalityList;
    // $data['eid']['testingLabsList'] = $app->getTestingLabs('eid', null, true);

    /* Infant and Mother's Health Information Section */
    $data['eid']['mothersHIVStatus'] = $commonResultsList;

    $motherTreatmentList = array();
    $motherTreatmentArray = array('No ART given', 'Pregnancy', 'Labour/Delivery', 'Postnatal', 'Unknown');
    foreach ($motherTreatmentArray as $key => $treatment) {
        $motherTreatmentList[$key]['value'] = $treatment;
        $motherTreatmentList[$key]['show'] = $treatment;
    }
    $data['eid']['motherTreatment'] = $motherTreatmentList;
    $data['eid']['rapidTestResult'] = $app->generateSelectOptions($eidObj->getEidResults());
    $data['eid']['prePcrTestResult'] = $commonResultsList;

    $pcrTestReasonList = array();
    $pcrTestReasonArray = array('Confirmation of positive first EID PCR test result', 'Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months', 'Positive HIV rapid test result at 9 months or later', 'Other');
    foreach ($pcrTestReasonArray as $key => $reason) {
        $pcrTestReasonList[$key]['value'] = $reason;
        $pcrTestReasonList[$key]['show'] = $reason;
    }
    $data['eid']['pcrTestReason'] = $pcrTestReasonList;
    $data['eid']['specimenTypeList'] = $app->generateSelectOptions($eidObj->getEidSampleTypes());

    /* Rejected Reason*/
    $rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_eid_sample_rejection_reasons WHERE rejection_reason_status ='active' GROUP BY rejection_type";
    $rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
    $rejectionReason = array();
    foreach ($rejectionTypeResult as $key => $type) {
        $rejectionReason[$key]['show'] = ucwords($type['rejection_type']);
        $rejectionQuery = "SELECT * FROM r_eid_sample_rejection_reasons where rejection_reason_status = 'active' AND rejection_type LIKE '" . $type['rejection_type'] . "%'";
        $rejectionResult = $db->rawQuery($rejectionQuery);
        foreach ($rejectionResult as $subKey => $reject) {
            $rejectionReason[$key]['reasons'][$subKey]['value'] = $reject['rejection_reason_id'];
            $rejectionReason[$key]['reasons'][$subKey]['show'] = ucwords($reject['rejection_reason_name']);
        }
    }
    $data['eid']['rejectedReasonList'] = $rejectionReason;

    /* Testing Platform Details */
    $testPlatformList = array();
    $testPlatformResult = $general->getTestingPlatforms('eid');
    foreach ($testPlatformResult as $row) {
        $testPlatformList[$row['machine_name']] = $row['machine_name'];
    }
    $data['eid']['testPlatformList'] = $app->generateSelectOptions($testPlatformList);

    $data['eid']['labTechniciansList'] = $app->generateSelectOptions($labTechniciansList);
    $data['eid']['resultsList'] = $app->generateSelectOptions($eidObj->getEidResults());
    $data['eid']['sampleStatusList'] = $app->generateSelectOptions($statusList);

    $data['eid']['statusFilterList'] = array(
        array('value' => '7', 'show' => 'Approved'),
        array('value' => '1', 'show' => 'Pending'),
        array('value' => '4', 'show' => 'Rejected')
    );
    $status = true;
}
if ($status) {
    $payload = array(
        'status' => 1,
        'message' => 'Success',
        'data' => $data,
        'timestamp' => $general->getDateTime()
    );
} else {
    $response = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => 'Some test types was inactive from the server.',
        'data' => array()
    );
    http_response_code(401);
    echo json_encode($response);
    exit(0);
}

echo json_encode($payload);

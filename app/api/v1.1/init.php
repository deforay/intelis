<?php
// Allow from any origin
use App\Models\App;
use App\Models\Covid19;
use App\Models\Eid;
use App\Models\Facilities;
use App\Models\General;
use App\Models\GeoLocations;
use App\Models\Tb;
use App\Models\Users;
use App\Models\Vl;

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

$general = new General();
$app = new App();
$userDb = new Users();
$facilitiesDb = new Facilities();
$geoLocationDb = new GeoLocations();

$transactionId = $general->generateUUID();
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
$updatedDateTime = (isset($input['latestDateTime']) && $input['latestDateTime'] != "") ? $input['latestDateTime'] : null;
/* Status name list */
$statusList = array();
$tsQuery = "SELECT status_id, status_name FROM r_sample_status where status = 'active'";
$tsResult = $db->rawQuery($tsQuery);
foreach ($tsResult as $row) {
    $statusList[$row['status_id']] = $row['status_name'];
}
// Check if covid-19 module active/inactive
$status = false;
/* Funding Source List */
$fundingSourceList = array();
$fundingSourceQry = "SELECT funding_source_id, funding_source_name FROM r_funding_sources WHERE funding_source_status='active' ";
if ($updatedDateTime) {
    $fundingSourceQry .= " AND updated_datetime >= '$updatedDateTime'";
}
$fundingSourceQry .= " ORDER BY funding_source_name ASC";

$fundingSourceResult = $db->query($fundingSourceQry);
foreach ($fundingSourceResult as $funding) {
    $fundingSourceList[$funding['funding_source_id']] = $funding['funding_source_name'];
}
/* Implementing Partner Details */
$implementingPartnerList = array();
$implementingPartnerQry = "SELECT i_partner_id, i_partner_name FROM r_implementation_partners WHERE i_partner_status='active' ";
if ($updatedDateTime) {
    $implementingPartnerQry .= " AND updated_datetime >= '$updatedDateTime'";
}
$implementingPartnerQry .= " ORDER BY i_partner_name ASC";

$implementingPartnerResult = $db->query($implementingPartnerQry);
foreach ($implementingPartnerResult as $key => $ip) {
    $implementingPartnerList[$key]['value'] = strtolower(str_replace(" ", "-", $ip['i_partner_id']));
    $implementingPartnerList[$key]['show'] = $ip['i_partner_name'];
}
/* Nationality Details */
$nationalityQry = "SELECT iso_name, iso3, id FROM `r_countries` ORDER BY `iso_name` ASC";
$nationalityResult = $db->query($nationalityQry);
foreach ($nationalityResult as $key => $nrow) {
    $nationalityList[$key]['show'] = ($nrow['iso_name']) . ' (' . $nrow['iso3'] . ')';
    $nationalityList[$key]['value'] = $nrow['id'];
}
$commonResultsList = array();
$commonResult = array('positive', 'negative', 'unknown');
foreach ($commonResult as $key => $result) {
    $commonResultsList[$key]['value'] = $result;
    $commonResultsList[$key]['show'] = ($result);
}
/* Lab Technician Details */
$facilityMap = $facilitiesDb->getUserFacilityMap($user['user_id']);
$userResult = $userDb->getActiveUsers($facilityMap, $updatedDateTime);
$labTechniciansList = array();
foreach ($userResult as $row) {
    $labTechniciansList[$row['user_id']] = ($row['user_name']);
}
$activeModule = array();
if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
    $activeModule[] = '"vl"';
}
if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
    $activeModule[] = '"eid"';
}
if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
    $activeModule[] = '"covid19"';
}
if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) {
    $activeModule[] = '"hepatitis"';
}
if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {
    $activeModule[] = '"tb"';
}

$data = array();
$data['formId'] = $formId;
$data['activeModule'] = implode(",", $activeModule);
$data['facilitiesList'] = $app->getAppHealthFacilities(null, $user['user_id'], false, 0, false, null, $updatedDateTime);
$data['geoGraphicalDivision'] = $geoLocationDb->fetchActiveGeolocations("", "", "no", true, null, $updatedDateTime);
$data['healthFacilitiesList'] = $app->getAppHealthFacilities(null, $user['user_id'], true, 1, false, implode(",", $activeModule), $updatedDateTime);
$data['testingLabsList'] = $app->getTestingLabs(null, $user['user_id'], false, false, implode(",", $activeModule), $updatedDateTime);
/* Province Details */
$data['provinceList'] = $app->getProvinceDetails($user['user_id'], true, $updatedDateTime);
/* District Details */
$data['districtList'] = $app->getDistrictDetails($user['user_id'], true, $updatedDateTime);
$data['implementingPartnerList'] = $implementingPartnerList;
$data['fundingSourceList'] = $app->generateSelectOptions($fundingSourceList);
$data['nationalityList'] = $nationalityList;
$data['labTechniciansList'] = $app->generateSelectOptions($labTechniciansList);
$data['sampleStatusList'] = $app->generateSelectOptions($statusList);

if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
    $covid19Obj = new Covid19();

    // if (isset($formId) && $formId == 1) {
    /* Source of Alert list */
    $sourceOfAlertList = array();
    $sourceOfAlert = array('Hotline', 'Community Surveillance', 'POE', 'Contact Tracing', 'Clinic', 'Sentinel Site', 'Screening', 'Others');
    foreach ($sourceOfAlert as $key => $src) {
        $sourceOfAlertList[$key]['value'] = strtolower(str_replace(" ", "-", $src));
        $sourceOfAlertList[$key]['show'] = $src;
    }
    $data['covid19']['sourceOfAlertList'] = $sourceOfAlertList;
    // }
    /* Province Details */
    $data['covid19']['provinceList'] = $app->getProvinceDetails($user['user_id'], true, $updatedDateTime);
    /* District Details */
    $data['covid19']['districtList'] = $app->getDistrictDetails($user['user_id'], true, $updatedDateTime);
    /* Health Facility Details */
    // $data['covid19']['healthFacilitiesList'] = $app->getAppHealthFacilities('covid19', $user['user_id'], true, 1, true);
    $data['covid19']['fundingSourceList'] = $app->generateSelectOptions($fundingSourceList);
    $data['covid19']['implementingPartnerList'] = $implementingPartnerList;
    $data['covid19']['nationalityList'] = $nationalityList;

    /* Type of Test Request */
    $typeOfTestReqList = array();
    if ($formId == 3) {
        $typeOfTestReqResult = array("PCR/RT-PCR", "RdRp-SARS Cov-2", "GeneXpert", "Rapid Antigen Test", "Other");
    } else {
        $typeOfTestReqResult = array('Real Time RT-PCR', 'RDT-Antibody', 'RDT-Antigen', 'ELISA', 'Others');
    }
    foreach ($typeOfTestReqResult as $key => $req) {
        $typeOfTestReqList[$key]['value'] = $req;
        $typeOfTestReqList[$key]['show'] = $req;
    }
    $data['covid19']['typeOfTestRequestList'] = $typeOfTestReqList;
    $platformTestKits = array();
    $platformTestKitsResult = array('Abbott Panbio™ COVID-19 Ag Test', 'STANDARD™ Q COVID-19 Ag Test', 'LumiraDx ™ SARS-CoV-2 Ag Test', 'Sure Status® COVID-19 Antigen Card Test');
    foreach ($platformTestKitsResult as $key => $req) {
        $platformTestKits[$key]['value'] = $req;
        $platformTestKits[$key]['show'] = $req;
    }
    $data['covid19']['rdtAntigenOptions'] = $platformTestKits;

    $data['covid19']['covid19ReasonsForTestingList'] = $app->generateSelectOptions($covid19Obj->getCovid19ReasonsForTesting($updatedDateTime));
    $data['covid19']['specimenTypeResultList'] = $app->generateSelectOptions($covid19Obj->getCovid19SampleTypes($updatedDateTime));
    foreach (range(1, 5) as $key => $req) {
        $testingPoint[$key]['value'] = $req;
        $testingPoint[$key]['show'] = $req;
    }
    $data['covid19']['testingPoint'] = $testingPoint;
    $data['covid19']['testingLabsList'] = $app->getTestingLabs('covid19', null, true, false, $updatedDateTime);
    /* Type of Test Request */
    $qualityList = array();
    $qualityResults = array('Good', 'Poor');
    foreach ($qualityResults as $key => $req) {
        $qualityList[$key]['value'] = strtolower($req);
        $qualityList[$key]['show'] = $req;
    }
    $data['covid19']['qualityList'] = $qualityList;

    /* Rejected Reason*/
    $rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_covid19_sample_rejection_reasons WHERE rejection_reason_status ='active' ";
    if ($updatedDateTime) {
        $rejectionTypeQuery .= " AND updated_datetime >= '$updatedDateTime'";
    }
    $rejectionTypeQuery .= " GROUP BY rejection_type";
    $rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
    $rejectionReason = array();
    foreach ($rejectionTypeResult as $key => $type) {
        $rejectionReason[$key]['show'] = ($type['rejection_type']);
        $rejectionQuery = "SELECT rejection_reason_id, rejection_reason_name FROM r_covid19_sample_rejection_reasons where rejection_reason_status = 'active' AND rejection_type LIKE '" . $type['rejection_type'] . "%' ";
        if ($updatedDateTime) {
            $rejectionQuery .= " AND updated_datetime >= '$updatedDateTime'";
        }
        $rejectionResult = $db->rawQuery($rejectionQuery);
        foreach ($rejectionResult as $subKey => $reject) {
            $rejectionReason[$key]['reasons'][$subKey]['value'] = $reject['rejection_reason_id'];
            $rejectionReason[$key]['reasons'][$subKey]['show'] = ($reject['rejection_reason_name']);
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

    $data['covid19']['resultsList'] = $app->generateSelectOptions($covid19Obj->getCovid19Results($updatedDateTime));
    $data['covid19']['symptomsList'] = $app->generateSelectOptions($covid19Obj->getCovid19Symptoms($updatedDateTime));
    $data['covid19']['comorbiditiesList'] = $app->generateSelectOptions($covid19Obj->getCovid19Comorbidities($updatedDateTime));
    // $data['covid19']['sampleStatusList'] = $app->generateSelectOptions($statusList);

    $data['covid19']['statusFilterList'] = array(
        array('value' => '7', 'show' => 'Approved'),
        array('value' => '1', 'show' => 'Pending'),
        array('value' => '4', 'show' => 'Rejected')
    );
    $status = true;
}

// Check if eid module active/inactive
if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
    $eidObj = new Eid();
    /* SITE INFORMATION SECTION */
    /* Province Details */
    $data['eid']['provinceList'] = $app->getProvinceDetails($user['user_id'], true, $updatedDateTime);
    /* District Details */
    $data['eid']['districtList'] = $app->getDistrictDetails($user['user_id'], true, $updatedDateTime);
    /* Health Facility Details */
    // $data['eid']['healthFacilitiesList'] = $app->getAppHealthFacilities('eid', $user['user_id'], true, 1, true);
    // $data['eid']['implementingPartnerList'] = $implementingPartnerList;
    // $data['eid']['fundingSourceList'] = $app->generateSelectOptions($fundingSourceList);
    // $data['eid']['nationalityList'] = $nationalityList;
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
    $data['eid']['rapidTestResult'] = $app->generateSelectOptions($eidObj->getEidResults($updatedDateTime));
    $data['eid']['prePcrTestResult'] = $commonResultsList;

    $pcrTestReasonList = array();
    $pcrTestReasonArray = array('Confirmation of positive first EID PCR test result', 'Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months', 'Positive HIV rapid test result at 9 months or later', 'Other');
    foreach ($pcrTestReasonArray as $key => $reason) {
        $pcrTestReasonList[$key]['value'] = $reason;
        $pcrTestReasonList[$key]['show'] = $reason;
    }
    $data['eid']['pcrTestReason'] = $pcrTestReasonList;
    $data['eid']['specimenTypeList'] = $app->generateSelectOptions($eidObj->getEidSampleTypes($updatedDateTime));

    /* Rejected Reason*/
    $rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_eid_sample_rejection_reasons WHERE rejection_reason_status ='active' GROUP BY rejection_type";
    if ($updatedDateTime) {
        $rejectionQuery .= " AND updated_datetime >= '$updatedDateTime'";
    }
    $rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
    $rejectionReason = array();
    foreach ($rejectionTypeResult as $key => $type) {
        $rejectionReason[$key]['show'] = ($type['rejection_type']);
        $rejectionQuery = "SELECT rejection_reason_id, rejection_reason_name FROM r_eid_sample_rejection_reasons where rejection_reason_status = 'active' AND rejection_type LIKE '" . $type['rejection_type'] . "%' ";
        if ($updatedDateTime) {
            $rejectionQuery .= " AND updated_datetime >= '$updatedDateTime'";
        }
        $rejectionResult = $db->rawQuery($rejectionQuery);
        foreach ($rejectionResult as $subKey => $reject) {
            $rejectionReason[$key]['reasons'][$subKey]['value'] = $reject['rejection_reason_id'];
            $rejectionReason[$key]['reasons'][$subKey]['show'] = ($reject['rejection_reason_name']);
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

    $data['eid']['resultsList'] = $app->generateSelectOptions($eidObj->getEidResults());
    // $data['eid']['sampleStatusList'] = $app->generateSelectOptions($statusList);

    $data['eid']['statusFilterList'] = array(
        array('value' => '7', 'show' => 'Approved'),
        array('value' => '1', 'show' => 'Pending'),
        array('value' => '4', 'show' => 'Rejected')
    );
    $status = true;
}

// Check if vl module active/inactive
if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
    $vlObj = new Vl();
    /* SAMPLE INFORMATION SECTION */
    $data['vl']['specimenTypeList'] = $app->generateSelectOptions($vlObj->getVlSampleTypes($updatedDateTime));
    /* Current regimen */
    $aQuery = "SELECT art_code FROM r_vl_art_regimen where art_status ='active' ";
    if ($updatedDateTime) {
        $aQuery .= " AND updated_datetime >= '$updatedDateTime'";
    }
    $aResult = $db->query($aQuery);

    $regimenResult = array();
    foreach ($aResult as $subKey => $regimen) {
        $regimenResult[$subKey]['value'] = $regimen['art_code'];
        $regimenResult[$subKey]['show'] = $regimen['art_code'];
    }
    $data['vl']['currentRegimenList'] = $regimenResult;
    /* ARV Adherence */
    $data['vl']['arvAdherence'] = array(
        array('value' => 'good', 'show' => 'Good >= 95%'),
        array('value' => 'fair', 'show' => 'Fair (85-94%)'),
        array('value' => 'poor', 'show' => 'Poor < 85%')
    );

    /* Rejected Reason*/
    $rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_vl_sample_rejection_reasons WHERE rejection_reason_status ='active' ";
    if ($updatedDateTime) {
        $rejectionQuery .= " AND updated_datetime >= '$updatedDateTime'";
    }
    $rejectionQuery .= " GROUP BY rejection_type";
    $rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
    $rejectionReason = array();
    foreach ($rejectionTypeResult as $key => $type) {
        $rejectionReason[$key]['show'] = ($type['rejection_type']);
        $rejectionQuery = "SELECT rejection_reason_id, rejection_reason_name FROM r_vl_sample_rejection_reasons where rejection_reason_status = 'active' AND rejection_type LIKE '" . $type['rejection_type'] . "%' ";
        if ($updatedDateTime) {
            $rejectionQuery .= " AND updated_datetime >= '$updatedDateTime'";
        }
        $rejectionResult = $db->rawQuery($rejectionQuery);
        foreach ($rejectionResult as $subKey => $reject) {
            $rejectionReason[$key]['reasons'][$subKey]['value'] = $reject['rejection_reason_id'];
            $rejectionReason[$key]['reasons'][$subKey]['show'] = ($reject['rejection_reason_name']);
        }
    }
    $data['vl']['rejectedReasonList'] = $rejectionReason;

    /* Testing Platform Details */
    $testPlatformList = array();
    $testPlatformResult = $general->getTestingPlatforms('vl');
    foreach ($testPlatformResult as $row) {
        $testPlatformList[$row['machine_name']] = $row['machine_name'];
    }
    $data['vl']['testPlatformList'] = $app->generateSelectOptions($testPlatformList);
    $data['vl']['reasonForFailure'] = $vlObj->getReasonForFailure(false, $updatedDateTime);

    $data['vl']['statusFilterList'] = array(
        array('value' => '7', 'show' => 'Approved'),
        array('value' => '1', 'show' => 'Pending'),
        array('value' => '4', 'show' => 'Rejected')
    );
    $data['vl']['vlResults'] = $general->fetchDataFromTable('r_vl_results');
    $status = true;
}

// Check if tb module active/inactive
if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {
    $tbObj = new Tb();
    /* SITE INFORMATION SECTION */

    /* Infant and Mother's Health Information Section */
    // $data['eid']['mothersHIVStatus'] = $commonResultsList;

    // $motherTreatmentList = array();
    // $motherTreatmentArray = array('No ART given', 'Pregnancy', 'Labour/Delivery', 'Postnatal', 'Unknown');
    // foreach ($motherTreatmentArray as $key => $treatment) {
    //     $motherTreatmentList[$key]['value'] = $treatment;
    //     $motherTreatmentList[$key]['show'] = $treatment;
    // }
    // $data['eid']['motherTreatment'] = $motherTreatmentList;
    $data['tb']['rapidTestResult'] = $app->generateSelectOptions($tbObj->getTbResults(null, $updatedDateTime));
    // $data['eid']['prePcrTestResult'] = $commonResultsList;

    // $pcrTestReasonList = array();
    // $pcrTestReasonArray = array('Confirmation of positive first EID PCR test result', 'Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months', 'Positive HIV rapid test result at 9 months or later', 'Other');
    // foreach ($pcrTestReasonArray as $key => $reason) {
    //     $pcrTestReasonList[$key]['value'] = $reason;
    //     $pcrTestReasonList[$key]['show'] = $reason;
    // }
    // $data['eid']['pcrTestReason'] = $pcrTestReasonList;
    $data['tb']['specimenTypeList'] = $app->generateSelectOptions($tbObj->getTbSampleTypes($updatedDateTime));

    /* Rejected Reason*/
    $rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_tb_sample_rejection_reasons WHERE rejection_reason_status ='active' ";
    if ($updatedDateTime) {
        $rejectionTypeQuery .= " AND updated_datetime >= '$updatedDateTime'";
    }
    $rejectionTypeQuery .= " GROUP BY rejection_type";
    $rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
    $rejectionReason = array();
    foreach ($rejectionTypeResult as $key => $type) {
        $rejectionReason[$key]['show'] = ($type['rejection_type']);
        $rejectionQuery = "SELECT rejection_reason_id, rejection_reason_name FROM r_tb_sample_rejection_reasons where rejection_reason_status = 'active' AND rejection_type LIKE '" . $type['rejection_type'] . "%' ";
        if ($updatedDateTime) {
            $rejectionQuery .= " AND updated_datetime >= '$updatedDateTime'";
        }
        $rejectionResult = $db->rawQuery($rejectionQuery);
        foreach ($rejectionResult as $subKey => $reject) {
            $rejectionReason[$key]['reasons'][$subKey]['value'] = $reject['rejection_reason_id'];
            $rejectionReason[$key]['reasons'][$subKey]['show'] = ($reject['rejection_reason_name']);
        }
    }
    $data['tb']['rejectedReasonList'] = $rejectionReason;

    /* Testing Platform Details */
    $testPlatformList = array();
    $testPlatformResult = $general->getTestingPlatforms('tb');
    foreach ($testPlatformResult as $row) {
        $testPlatformList[$row['machine_name']] = $row['machine_name'];
    }
    $data['tb']['testPlatformList'] = $app->generateSelectOptions($testPlatformList);

    $data['tb']['resultsList'] = $app->generateSelectOptions($tbObj->getTbResults(null, $updatedDateTime));
    // $data['eid']['sampleStatusList'] = $app->generateSelectOptions($statusList);

    $data['tb']['statusFilterList'] = array(
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
        'timestamp' => time(),
    );
} else {
    $payload = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => 'Please contact system administrator.',
        'data' => array()
    );
    http_response_code(401);
    // exit(0);
}

if (isset($user['token_updated']) && $user['token_updated'] === true) {
    $payload['token'] = $user['new_token'];
} else {
    $payload['token'] = null;
}
$trackId = $general->addApiTracking(
    $transactionId,
    $user['user_id'],
    1,
    'init',
    'common',
    $_SERVER['REQUEST_URI'],
    $input,
    $payload,
    'json'
);
echo json_encode($payload);

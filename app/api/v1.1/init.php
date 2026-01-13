<?php
// Allow from any origin
use Slim\Psr7\Request;
use const COUNTRY\DRC;
use App\Services\TbService;
use App\Services\VlService;
use App\Services\ApiService;
use App\Services\EidService;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\Covid19Service;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$applicationConfig = ContainerRegistry::get('applicationConfig');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);


/** @var Request $request */
$request = AppRegistry::get('request');
$origJson = $apiService->getJsonFromRequest($request);
if (JsonUtility::isJSON($origJson) === false) {
    throw new SystemException("Invalid JSON Payload", 400);
}
$input = JsonUtility::decodeJson($origJson, true);


try {
    $transactionId = MiscUtility::generateULID();
    $formId = (int) $general->getGlobalConfig('vl_form');
    $authToken = ApiService::extractBearerToken($request);
    $user = $usersService->findUserByApiToken($authToken);
    /* To save the user attributes from API */
    $userAttributes = [];
    foreach (['deviceId', 'osVersion', 'ipAddress'] as $header) {
        $userAttributes[$header] = $apiService->getHeader($request, $header);
    }
    $userAttributes = JsonUtility::jsonToSetString(json_encode($userAttributes), 'user_attributes');
    $usersService->saveUserAttributes($userAttributes, $user['user_id']);

    $updatedDateTime = $input['latestDateTime'] ?? null;
    /* Status name list */
    $statusList = [];
    $tsQuery = "SELECT status_id, status_name
                FROM r_sample_status
                WHERE status = 'active'";
    $tsResult = $db->rawQuery($tsQuery);
    foreach ($tsResult as $row) {
        $statusList[$row['status_id']] = $row['status_name'];
    }

    // Check if covid-19 module active/inactive
    $status = false;
    // Funding Sources List
    $fundingSourceList = [];
    $fundingSourceQry = "SELECT funding_source_id, funding_source_name
                        FROM r_funding_sources
                        WHERE funding_source_status='active' ";
    if ($updatedDateTime) {
        $fundingSourceQry .= " AND updated_datetime >= '$updatedDateTime'";
    }
    $fundingSourceQry .= " ORDER BY funding_source_name ASC";

    $fundingSourceResult = $db->query($fundingSourceQry);
    foreach ($fundingSourceResult as $funding) {
        $fundingSourceList[$funding['funding_source_id']] = $funding['funding_source_name'];
    }
    // Implementing Partners List
    $implementingPartnerList = [];
    $implementingPartnerQry = "SELECT i_partner_id, i_partner_name
                            FROM r_implementation_partners
                            WHERE i_partner_status='active' ";
    if ($updatedDateTime) {
        $implementingPartnerQry .= " AND updated_datetime >= '$updatedDateTime'";
    }
    $implementingPartnerQry .= " ORDER BY i_partner_name ASC";

    $implementingPartnerResult = $db->query($implementingPartnerQry);
    foreach ($implementingPartnerResult as $key => $ip) {
        $implementingPartnerList[$key]['value'] = strtolower(str_replace(" ", "-", (string) $ip['i_partner_id']));
        $implementingPartnerList[$key]['show'] = $ip['i_partner_name'];
    }
    // Countries List
    $nationalityQry = "SELECT iso_name, iso3, id FROM `r_countries` ORDER BY `iso_name` ASC";
    $nationalityResult = $db->query($nationalityQry);
    foreach ($nationalityResult as $key => $nrow) {
        $nationalityList[$key]['show'] = ($nrow['iso_name']) . ' (' . $nrow['iso3'] . ')';
        $nationalityList[$key]['value'] = $nrow['id'];
    }
    $commonResultsList = [];
    $commonResult = ['positive', 'negative', 'unknown'];
    foreach ($commonResult as $key => $result) {
        $commonResultsList[$key]['value'] = $result;
        $commonResultsList[$key]['show'] = ($result);
    }
    /* Lab Technician Details */
    $facilityMap = $facilitiesService->getUserFacilityMap($user['user_id']);
    $userResult = $usersService->getActiveUsers($facilityMap, $updatedDateTime);
    $labTechniciansList = [];
    $userList = [];
    foreach ($userResult as $row) {
        $userList[] = [
            'user_id' => $row['user_id'],
            'user_name' => $row['user_name'],
            'email' => $row['email'],
            'phone_number' => $row['phone_number'],
        ];
        $labTechniciansList[$row['user_id']] = ($row['user_name']);
    }

    $activeTests = TestsService::getActiveTests();
    $data = [];
    $data['formId'] = $formId;
    $data['activeModule'] = implode(",", $activeTests);
    $data['facilitiesList'] = $general->getAppHealthFacilitiesAPI(null, $user['user_id'], false, 0, false, null, $updatedDateTime);
    $data['geoGraphicalDivision'] = $geolocationService->fetchActiveGeolocations("", "", "no", true, null, $updatedDateTime);
    $data['healthFacilitiesList'] = $general->getAppHealthFacilitiesAPI(null, $user['user_id'], true, 1, false, implode(",", $activeTests), $updatedDateTime);
    $data['testingLabsList'] = $general->getTestingLabsAPI(testType: null, user: $user['user_id'], onlyActive: false, module: false, activeModule: implode(",", $activeTests), updatedDateTime: $updatedDateTime);
    /* Province Details */
    $data['provinceList'] = $general->getProvinceDetailsApi($user['user_id'], true, $updatedDateTime);
    /* District Details */
    $data['districtList'] = $general->getDistrictDetailsApi($user['user_id'], true, $updatedDateTime);
    $data['implementingPartnerList'] = $implementingPartnerList;
    $data['fundingSourceList'] = $general->generateSelectOptionsAPI($fundingSourceList);
    $data['nationalityList'] = $nationalityList;
    $data['labTechniciansList'] = $general->generateSelectOptionsAPI($labTechniciansList);
    $data['userList'] = $userList;
    $data['sampleStatusList'] = $general->generateSelectOptionsAPI($statusList);

    $statusFilterList = $general->generateSelectOptionsAPI($general->getSampleStatus(true));
    $modules = SYSTEM_CONFIG['modules'];

    $rejectionReason = [];
    $testReason = [];
    foreach ($modules as $module => $status) {
        $rejectionResult = [];
        $rejectionTypeResult = [];
        $reasons[$module] = [];
        if ($status) {
            if ($module == 'common') {
                continue;
            }
            $module = ($module == 'generic-tests') ? 'generic' : $module;

            $condition = " rejection_reason_status ='active' ";
            if ($updatedDateTime) {
                $condition .= " AND updated_datetime >= '$updatedDateTime'";
            }
            $rejectionTypeResult = $general->getDataByTableAndFields('r_' . $module . '_sample_rejection_reasons', ['rejection_type'], false, $condition, 'rejection_type');

            foreach ($rejectionTypeResult as $key => $type) {
                $reasons[$module][$key]['show'] = ucwords((string) $type['rejection_type']);
                $condition = " rejection_reason_status ='active' AND rejection_type LIKE '" . $type['rejection_type'] . "'";
                if ($updatedDateTime) {
                    $condition .= " AND updated_datetime >= '$updatedDateTime'";
                }
                $rejectionResult = $general->getDataByTableAndFields('r_' . $module . '_sample_rejection_reasons', ['rejection_reason_id', 'rejection_reason_name'], false, $condition);
                foreach ($rejectionResult as $subKey => $reject) {
                    $reasons[$module][$key]['reasons'][$subKey]['value'] = $reject['rejection_reason_id'];
                    $reasons[$module][$key]['reasons'][$subKey]['show'] = ($reject['rejection_reason_name']);
                }
            }
            $rejectionReason[$module] = $reasons[$module];
            $testReasonName = "test_reason_name";
            $testReasonTable = 'r_' . $module . '_test_reasons';
            if ($module == 'genericTests' || $module == 'generic-tests' || $module == 'generic') {
                $testReasonTable = 'r_generic_test_reasons';
                $testReasonName = "test_reason";
            }
            $testReasonsResult = $general->getDataByTableAndFields($testReasonTable, ['test_reason_id', $testReasonName, 'parent_reason'], false, " test_reason_status like 'active' ", $testReasonName);
            foreach ($testReasonsResult as $subKey => $reject) {
                $testReasons[$module]['testReasons'][$subKey]['parent'] = $reject['parent_reason'] ?? 0;
                $testReasons[$module]['testReasons'][$subKey]['value'] = $reject['test_reason_id'];
                $testReasons[$module]['testReasons'][$subKey]['show'] = ($reject[$testReasonName]);
            }
            $testReason[$module] = $testReasons[$module];
        }
    }
    // print_r($responseRejections);die;

    if (
        isset($applicationConfig['modules']['covid19'])
        && $applicationConfig['modules']['covid19'] === true
    ) {

        /** @var Covid19Service $covid19Service */
        $covid19Service = ContainerRegistry::get(Covid19Service::class);


        // Sources of Alerts List
        $sourceOfAlertList = [];
        $sourceOfAlert = [
            'Hotline',
            'Community Surveillance',
            'POE',
            'Contact Tracing',
            'Clinic',
            'Sentinel Site',
            'Screening',
            'Others'
        ];
        foreach ($sourceOfAlert as $key => $src) {
            $sourceOfAlertList[$key]['value'] = strtolower(str_replace(" ", "-", $src));
            $sourceOfAlertList[$key]['show'] = $src;
        }
        $data['covid19']['sourceOfAlertList'] = $sourceOfAlertList;

        /* Province Details */
        $data['covid19']['provinceList'] = $data['provinceList'] ?? [];
        /* District Details */
        $data['covid19']['districtList'] = $data['districtList'] ?? [];
        /* Health Facility Details */
        $data['covid19']['fundingSourceList'] = $data['fundingSourceList'] ?? [];
        $data['covid19']['implementingPartnerList'] = $implementingPartnerList;
        $data['covid19']['nationalityList'] = $nationalityList;

        /* Type of Test Request */
        $typeOfTestReqList = [];
        if ($formId == DRC) {
            $typeOfTestReqResult = [
                "PCR/RT-PCR",
                "RdRp-SARS Cov-2",
                "GeneXpert",
                "Rapid Antigen Test",
                "Other"
            ];
        } else {
            $typeOfTestReqResult = [
                'Real Time RT-PCR',
                'RDT-Antibody',
                'RDT-Antigen',
                'ELISA',
                'Others'
            ];
        }
        foreach ($typeOfTestReqResult as $key => $req) {
            $typeOfTestReqList[$key]['value'] = $req;
            $typeOfTestReqList[$key]['show'] = $req;
        }
        $data['covid19']['typeOfTestRequestList'] = $typeOfTestReqList;
        $platformTestKits = [];
        $platformTestKitsResult = ['Abbott Panbio™ COVID-19 Ag Test', 'STANDARD™ Q COVID-19 Ag Test', 'LumiraDx ™ SARS-CoV-2 Ag Test', 'Sure Status® COVID-19 Antigen Card Test'];
        foreach ($platformTestKitsResult as $key => $req) {
            $platformTestKits[$key]['value'] = $req;
            $platformTestKits[$key]['show'] = $req;
        }
        $data['covid19']['rdtAntigenOptions'] = $platformTestKits;

        $data['covid19']['covid19ReasonsForTestingList'] = $general->generateSelectOptionsAPI($covid19Service->getCovid19ReasonsForTesting($updatedDateTime));
        $data['covid19']['specimenTypeResultList'] = $general->generateSelectOptionsAPI($covid19Service->getCovid19SampleTypes($updatedDateTime));
        foreach (range(1, 5) as $key => $req) {
            $testingPoint[$key]['value'] = $req;
            $testingPoint[$key]['show'] = $req;
        }
        $data['covid19']['testingPoint'] = $testingPoint;
        $data['covid19']['testingLabsList'] = $general->getTestingLabsAPI('covid19', null, true, false, $updatedDateTime);
        /* Type of Test Request */
        $qualityList = [];
        $qualityResults = ['Good', 'Poor'];
        foreach ($qualityResults as $key => $req) {
            $qualityList[$key]['value'] = strtolower($req);
            $qualityList[$key]['show'] = $req;
        }
        $data['covid19']['qualityList'] = $qualityList;

        /* Rejected Reason*/
        $data['covid19']['rejectedReasonList'] = $rejectionReason['covid19'];
        $data['covid19']['testReasonList'] = $testReason['covid19']['testReasons'];

        /* Testing Platform Details */
        $testPlatformList = [];
        $testPlatformResult = $general->getTestingPlatforms('covid19');
        foreach ($testPlatformResult as $row) {
            $testPlatformList[$row['machine_name']] = $row['machine_name'];
        }
        $data['covid19']['testPlatformList'] = $general->generateSelectOptionsAPI($testPlatformList);

        $data['covid19']['resultsList'] = $general->generateSelectOptionsAPI($covid19Service->getCovid19Results($updatedDateTime));
        $data['covid19']['symptomsList'] = $general->generateSelectOptionsAPI($covid19Service->getCovid19Symptoms($updatedDateTime));
        $data['covid19']['comorbiditiesList'] = $general->generateSelectOptionsAPI($covid19Service->getCovid19Comorbidities($updatedDateTime));

        $data['covid19']['statusFilterList'] = $statusFilterList;
        $status = true;
    }

    // Check if eid module active/inactive
    if (isset($applicationConfig['modules']['eid']) && $applicationConfig['modules']['eid'] === true) {

        /** @var EidService $eidService */
        $eidService = ContainerRegistry::get(EidService::class);

        // SITE INFORMATION SECTION
        // Province Details
        $data['eid']['provinceList'] = $data['provinceList'] ?? [];
        // District Details
        $data['eid']['districtList'] = $data['districtList'] ?? [];

        // Infant and Mother's Health Information Section
        $data['eid']['mothersHIVStatus'] = $commonResultsList;

        $motherTreatmentList = [];
        $motherTreatmentArray = ['No ART given', 'Pregnancy', 'Labour/Delivery', 'Postnatal', 'Unknown'];
        foreach ($motherTreatmentArray as $key => $treatment) {
            $motherTreatmentList[$key]['value'] = $treatment;
            $motherTreatmentList[$key]['show'] = $treatment;
        }
        $data['eid']['motherTreatment'] = $motherTreatmentList;
        $data['eid']['rapidTestResult'] = $general->generateSelectOptionsAPI($eidService->getEidResults($updatedDateTime));
        $data['eid']['prePcrTestResult'] = $commonResultsList;

        $pcrTestReasonList = [];
        $pcrTestReasonArray = [
            'Confirmation of positive first EID PCR test result',
            'Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months',
            'Positive HIV rapid test result at 9 months or later',
            'Other'
        ];
        foreach ($pcrTestReasonArray as $key => $reason) {
            $pcrTestReasonList[$key]['value'] = $reason;
            $pcrTestReasonList[$key]['show'] = $reason;
        }
        $data['eid']['pcrTestReason'] = $pcrTestReasonList;
        $data['eid']['specimenTypeList'] = $general->generateSelectOptionsAPI($eidService->getEidSampleTypes($updatedDateTime));

        /* Rejected Reason*/
        $data['eid']['rejectedReasonList'] = $rejectionReason['eid'];
        $data['eid']['testReasonList'] = $testReason['eid']['testReasons'];

        /* Testing Platform Details */
        $testPlatformList = [];
        $testPlatformResult = $general->getTestingPlatforms('eid');
        foreach ($testPlatformResult as $row) {
            $testPlatformList[$row['machine_name']] = $row['machine_name'];
        }
        $data['eid']['testPlatformList'] = $general->generateSelectOptionsAPI($testPlatformList);

        $data['eid']['resultsList'] = $general->generateSelectOptionsAPI($eidService->getEidResults());

        $data['eid']['statusFilterList'] = $statusFilterList;
        $status = true;
    }

    // Check if vl module active/inactive
    if (isset($applicationConfig['modules']['vl']) && $applicationConfig['modules']['vl'] === true) {
        /** @var VlService $vlService */
        $vlService = ContainerRegistry::get(VlService::class);
        /* SAMPLE INFORMATION SECTION */
        $data['vl']['specimenTypeList'] = $general->generateSelectOptionsAPI($vlService->getVlSampleTypes($updatedDateTime));
        /* Current regimen */
        $aQuery = "SELECT art_code FROM r_vl_art_regimen where art_status ='active' ";
        if ($updatedDateTime) {
            $aQuery .= " AND updated_datetime >= '$updatedDateTime'";
        }
        $aResult = $db->query($aQuery);

        $regimenResult = [];
        foreach ($aResult as $subKey => $regimen) {
            $regimenResult[$subKey]['value'] = $regimen['art_code'];
            $regimenResult[$subKey]['show'] = $regimen['art_code'];
        }
        $data['vl']['currentRegimenList'] = $regimenResult;
        /* ARV Adherence */
        $data['vl']['arvAdherence'] = [
            ['value' => 'good', 'show' => 'Good >= 95%'],
            ['value' => 'fair', 'show' => 'Fair (85-94%)'],
            ['value' => 'poor', 'show' => 'Poor < 85%']
        ];

        /* Rejected Reason*/
        $data['vl']['rejectedReasonList'] = $rejectionReason['vl'];
        $data['vl']['testReasonList'] = $testReason['vl']['testReasons'];

        /* Testing Platform Details */
        $testPlatformList = [];
        $testPlatformResult = $general->getTestingPlatforms('vl');
        foreach ($testPlatformResult as $row) {
            $testPlatformList[$row['machine_name']] = $row['machine_name'];
        }
        $data['vl']['testPlatformList'] = $general->generateSelectOptionsAPI($testPlatformList);
        $data['vl']['reasonForFailure'] = $vlService->getReasonForFailure(false, $updatedDateTime);

        $data['vl']['statusFilterList'] = $statusFilterList;
        $data['vl']['vlResults'] = $general->fetchDataFromTable('r_vl_results');
        $status = true;
    }

    // Check if tb module active/inactive
    if (isset($applicationConfig['modules']['tb']) && $applicationConfig['modules']['tb'] === true) {

        /** @var TbService $tbService */
        $tbService = ContainerRegistry::get(TbService::class);
        $data['tb']['rapidTestResult'] = $general->generateSelectOptionsAPI($tbService->getTbResults(null, $updatedDateTime));

        $data['tb']['specimenTypeList'] = $general->generateSelectOptionsAPI($tbService->getTbSampleTypes($updatedDateTime));

        /* Rejected Reason*/
        $data['tb']['rejectedReasonList'] = $rejectionReason['tb'];
        $data['tb']['testReasonList'] = $testReason['tb']['testReasons'];
        /* Testing Platform Details */
        $testPlatformList = [];
        $testPlatformResult = $general->getTestingPlatforms('tb');
        foreach ($testPlatformResult as $row) {
            $testPlatformList[$row['machine_name']] = $row['machine_name'];
        }
        $data['tb']['testPlatformList'] = $general->generateSelectOptionsAPI($testPlatformList);

        $data['tb']['resultsList'] = $general->generateSelectOptionsAPI($tbService->getTbResults(null, $updatedDateTime));

        $data['tb']['statusFilterList'] = $statusFilterList;
        $status = true;
    }

    // Check if lab tests module active/inactive
    // if (isset($applicationConfig['modules']['generic-tests']) && $applicationConfig['modules']['generic-tests'] === true) {

    //     /** @var GenericTestsService $genericService */
    //     $genericService = ContainerRegistry::get(GenericTestsService::class);
    //     $data['genericTests']['specimenTypeList'] = $general->generateSelectOptionsAPI($genericService->getGenericSampleTypes($updatedDateTime));
    //     $data['genericTests']['resultsList'] = $general->generateSelectOptionsAPI($genericService->getGenericResults(null, $updatedDateTime));
    //     /* Rejected Reason*/
    //     $data['genericTests']['rejectedReasonList'] = $rejectionReason['generic-tests'];
    //     $data['genericTests']['testReasonList'] = $testReason['generic-tests']['testReasons'];

    //     /* Testing Platform Details */
    //     $testPlatformList = [];
    //     $testPlatformResult = $general->getTestingPlatforms('generic-tests');
    //     foreach ($testPlatformResult as $row) {
    //         $testPlatformList[$row['machine_name']] = $row['machine_name'];
    //     }
    //     $data['genericTests']['testPlatformList'] = $general->generateSelectOptionsAPI($testPlatformList);
    //     $data['genericTests']['statusFilterList'] = $statusFilterList;
    //     $status = true;
    // }

    $payload = [
        'status' => 1,
        'message' => 'success',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'data' => $data,
    ];
} catch (Throwable $e) {
    $db->rollbackTransaction();
    http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'data' => []
    ];
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

$payload = JsonUtility::encodeUtf8Json($payload);
$trackId = $general->addApiTracking($transactionId, $user['user_id'], 1, 'init', 'common', $_SERVER['REQUEST_URI'], $input, $payload, 'json', null, null, $authToken);

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);

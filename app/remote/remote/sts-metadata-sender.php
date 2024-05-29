<?php
//get data from STS send to requesting LIS instance
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$payload = [];

/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$data = $apiService->getJsonFromRequest($request, decode: true);

$counter = 0;

$transactionId = $general->generateUUID();

if (isset($data['Key']) && $data['Key'] == 'vlsm-get-remote') {

    $labId = $data['labId'] ?: null;

    $response = [];

    if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) {

        $toSyncTables = [
            "r_test_types",
            "r_generic_test_methods",
            "r_generic_test_categories",
            "r_generic_sample_types",
            "r_generic_test_reasons",
            "r_generic_test_result_units",
            "r_generic_test_failure_reasons",
            "r_generic_sample_rejection_reasons",
            "r_generic_symptoms",
            "generic_test_methods_map",
            "generic_test_sample_type_map",
            "generic_test_reason_map",
            "generic_test_failure_reason_map",
            "generic_sample_rejection_reason_map",
            "generic_test_symptoms_map",
            "generic_test_result_units_map"
        ];
        foreach ($toSyncTables as $table) {
            $condition = [];
            if (!empty($data[$general->stringToCamelCase($table) . 'LastModified'])) {
                $condition = "updated_datetime > '" . $data[$general->stringToCamelCase($table) . 'LastModified'] . "'";
            }
            $response[$general->stringToCamelCase($table)] = $general->fetchDataFromTable($table, $condition);
            $counter += count($response[$general->stringToCamelCase($table)]);
        }
    }

    if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {


        $condition = [];
        if (!empty($data['vlRejectionReasonsLastModified'])) {
            $condition = "updated_datetime > '" . $data['vlRejectionReasonsLastModified'] . "'";
        }
        $response['vlRejectionReasons'] = $general->fetchDataFromTable('r_vl_sample_rejection_reasons', $condition);

        $condition = [];
        if (!empty($data['vlTestReasonsLastModified'])) {
            $condition = "updated_datetime > '" . $data['vlTestReasonsLastModified'] . "'";
        }
        $response['vlTestReasons'] = $general->fetchDataFromTable('r_vl_test_reasons', $condition);


        $condition = [];
        if (!empty($data['vlSampleTypesLastModified'])) {
            $condition = "updated_datetime > '" . $data['vlSampleTypesLastModified'] . "'";
        }
        $response['vlSampleTypes'] = $general->fetchDataFromTable('r_vl_sample_type', $condition);

        $condition = [];
        if (!empty($data['vlArtCodesLastModified'])) {
            $condition = "updated_datetime > '" . $data['vlArtCodesLastModified'] . "'";
        }
        $response['vlArtCodes'] = $general->fetchDataFromTable('r_vl_art_regimen', $condition);

        $condition = [];
        if (!empty($data['vlFailureReasonsLastModified'])) {
            $condition = "updated_datetime > '" . $data['vlFailureReasonsLastModified'] . "'";
        }
        $response['vlFailureReasons'] = $general->fetchDataFromTable('r_vl_test_failure_reasons', $condition);

        // $condition = [];
        //$response['vlResults'] = [];
        if (!empty($data['vlResultsLastModified'])) {
            $condition = "updated_datetime > '" . $data['vlResultsLastModified'] . "'";
        }
        $response['vlResults'] = $general->fetchDataFromTable('r_vl_results', $condition);

        $counter += (count($response['vlRejectionReasons']) + count($response['vlSampleTypes']) + count($response['vlArtCodes']) + count($response['vlFailureReasons']) + count($response['vlResults']));
    }


    if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {

        $condition = [];
        if (!empty($data['eidRejectionReasonsLastModified'])) {
            $condition = "updated_datetime > '" . $data['eidRejectionReasonsLastModified'] . "'";
        }
        $response['eidRejectionReasons'] = $general->fetchDataFromTable('r_eid_sample_rejection_reasons', $condition);


        $condition = [];
        if (!empty($data['eidSampleTypesLastModified'])) {
            $condition = "updated_datetime > '" . $data['eidSampleTypesLastModified'] . "'";
        }
        $response['eidSampleTypes'] = $general->fetchDataFromTable('r_eid_sample_type', $condition);

        $condition = [];
        if (!empty($data['eidResultsLastModified'])) {
            $condition = "updated_datetime > '" . $data['eidResultsLastModified'] . "'";
        }
        $response['eidResults'] = $general->fetchDataFromTable('r_eid_results', $condition);

        $condition = [];
        if (!empty($data['eidReasonForTestingLastModified'])) {
            $condition = "updated_datetime > '" . $data['eidReasonForTestingLastModified'] . "'";
        }
        $response['eidReasonForTesting'] = $general->fetchDataFromTable('r_eid_test_reasons', $condition);

        $counter += (count($response['eidRejectionReasons']) + count($response['eidSampleTypes']) + count($response['eidResults']) + count($response['eidReasonForTesting']));
    }

    if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {

        $condition = [];
        if (!empty($data['covid19RejectionReasonsLastModified'])) {
            $condition = "updated_datetime > '" . $data['covid19RejectionReasonsLastModified'] . "'";
        }
        $response['covid19RejectionReasons'] = $general->fetchDataFromTable('r_covid19_sample_rejection_reasons', $condition);


        $condition = [];
        if (!empty($data['covid19SampleTypesLastModified'])) {
            $condition = "updated_datetime > '" . $data['covid19SampleTypesLastModified'] . "'";
        }
        $response['covid19SampleTypes'] = $general->fetchDataFromTable('r_covid19_sample_type', $condition);

        $condition = [];
        if (!empty($data['covid19ComorbiditiesLastModified'])) {
            $condition = "updated_datetime > '" . $data['covid19ComorbiditiesLastModified'] . "'";
        }
        $response['covid19Comorbidities'] = $general->fetchDataFromTable('r_covid19_comorbidities', $condition);

        $condition = [];
        if (!empty($data['covid19ResultsLastModified'])) {
            $condition = "updated_datetime > '" . $data['covid19ResultsLastModified'] . "'";
        }
        $response['covid19Results'] = $general->fetchDataFromTable('r_covid19_results', $condition);

        $condition = [];
        if (!empty($data['covid19SymptomsLastModified'])) {
            $condition = "updated_datetime > '" . $data['covid19SymptomsLastModified'] . "'";
        }
        $response['covid19Symptoms'] = $general->fetchDataFromTable('r_covid19_symptoms', $condition);

        $condition = [];
        if (!empty($data['covid19ReasonForTestingLastModified'])) {
            $condition = "updated_datetime > '" . $data['covid19ReasonForTestingLastModified'] . "'";
        }
        $response['covid19ReasonForTesting'] = $general->fetchDataFromTable('r_covid19_test_reasons', $condition);

        $condition = [];
        if (!empty($data['covid19QCTestKitsLastModified'])) {
            $condition = "updated_datetime > '" . $data['covid19QCTestKitsLastModified'] . "'";
        }
        $response['covid19QCTestKits'] = $general->fetchDataFromTable('r_covid19_qc_testkits', $condition);

        $counter += (count($response['covid19RejectionReasons']) + count($response['covid19SampleTypes']) + count($response['covid19Comorbidities']) + count($response['covid19Results']) + count($response['covid19Symptoms']) + count($response['covid19ReasonForTesting']) + count($response['covid19QCTestKits']));
    }

    if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) {

        $condition = [];
        if (!empty($data['hepatitisRejectionReasonsLastModified'])) {
            $condition = "updated_datetime > '" . $data['hepatitisRejectionReasonsLastModified'] . "'";
        }
        $response['hepatitisRejectionReasons'] = $general->fetchDataFromTable('r_hepatitis_sample_rejection_reasons', $condition);


        $condition = [];
        if (!empty($data['hepatitisSampleTypesLastModified'])) {
            $condition = "updated_datetime > '" . $data['hepatitisSampleTypesLastModified'] . "'";
        }
        $response['hepatitisSampleTypes'] = $general->fetchDataFromTable('r_hepatitis_sample_type', $condition);

        $condition = [];
        if (!empty($data['hepatitisComorbiditiesLastModified'])) {
            $condition = "updated_datetime > '" . $data['hepatitisComorbiditiesLastModified'] . "'";
        }
        $response['hepatitisComorbidities'] = $general->fetchDataFromTable('r_hepatitis_comorbidities', $condition);

        $condition = [];
        if (!empty($data['hepatitisResultsLastModified'])) {
            $condition = "updated_datetime > '" . $data['hepatitisResultsLastModified'] . "'";
        }
        $response['hepatitisResults'] = $general->fetchDataFromTable('r_hepatitis_results', $condition);

        $condition = [];
        if (!empty($data['hepatitisReasonForTestingLastModified'])) {
            $condition = "updated_datetime > '" . $data['hepatitisReasonForTestingLastModified'] . "'";
        }
        $response['hepatitisReasonForTesting'] = $general->fetchDataFromTable('r_hepatitis_test_reasons', $condition);

        $counter += (count($response['hepatitisRejectionReasons']) + count($response['hepatitisSampleTypes']) + count($response['hepatitisComorbidities']) + count($response['hepatitisResults']) + count($response['hepatitisReasonForTesting']));
    }

    if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {

        $condition = [];
        if (!empty($data['tbRejectionReasonsLastModified'])) {
            $condition = "updated_datetime > '" . $data['tbRejectionReasonsLastModified'] . "'";
        }
        $response['tbRejectionReasons'] = $general->fetchDataFromTable('r_tb_sample_rejection_reasons', $condition);

        $condition = [];
        if (!empty($data['tbSampleTypesLastModified'])) {
            $condition = "updated_datetime > '" . $data['tbSampleTypesLastModified'] . "'";
        }
        $response['tbSampleTypes'] = $general->fetchDataFromTable('r_tb_sample_type', $condition);

        $condition = [];
        if (!empty($data['tbResultsLastModified'])) {
            $condition = "updated_datetime > '" . $data['tbResultsLastModified'] . "'";
        }
        $response['tbResults'] = $general->fetchDataFromTable('r_tb_results', $condition);

        $condition = [];
        if (!empty($data['tbReasonForTestingLastModified'])) {
            $condition = "updated_datetime > '" . $data['tbReasonForTestingLastModified'] . "'";
        }
        $response['tbReasonForTesting'] = $general->fetchDataFromTable('r_tb_test_reasons', $condition);

        $counter += (count($response['tbRejectionReasons']) + count($response['tbSampleTypes']) + count($response['tbResults']) + count($response['tbReasonForTesting']));
    }

    if (isset(SYSTEM_CONFIG['modules']['cd4']) && SYSTEM_CONFIG['modules']['cd4'] === true) {

        $condition = [];
        if (!empty($data['cd4RejectionReasonsLastModified'])) {
            $condition = "updated_datetime > '" . $data['cd4RejectionReasonsLastModified'] . "'";
        }
        $response['cd4RejectionReasons'] = $general->fetchDataFromTable('r_cd4_sample_rejection_reasons', $condition);

        $condition = [];
        if (!empty($data['cd4SampleTypesLastModified'])) {
            $condition = "updated_datetime > '" . $data['cd4SampleTypesLastModified'] . "'";
        }
        $response['cd4SampleTypes'] = $general->fetchDataFromTable('r_cd4_sample_types', $condition);

        $condition = [];
        if (!empty($data['cd4ReasonForTestingLastModified'])) {
            $condition = "updated_datetime > '" . $data['cd4ReasonForTestingLastModified'] . "'";
        }
        $response['cd4ReasonForTesting'] = $general->fetchDataFromTable('r_cd4_test_reasons', $condition);

        $counter += (count($response['cd4RejectionReasons']) + count($response['cd4SampleTypes']) + count($response['cd4ReasonForTesting']));
    }

    $condition = [];
    if (!empty($data['globalConfigLastModified'])) {
        $condition = "updated_datetime > '" . $data['globalConfigLastModified'] . "' AND remote_sync_needed = 'yes'";
    }
    $response['globalConfig'] = $general->fetchDataFromTable('global_config', $condition);

    $condition = [];
    $signatureCondition = [];
    // Using same facilityLastModified to check if any signatures were added
    if (!empty($data['facilityLastModified'])) {
        $condition = "updated_datetime > '" . $data['facilityLastModified'] . "'";
        $signatureCondition = "added_on > '" . $data['facilityLastModified'] . "'";
    }
    $response['facilities'] = $general->fetchDataFromTable('facility_details', $condition);

    $response['users'] = [];
    $userIds = array_column($response['facilities'], 'contact_person');

    foreach ($userIds as $userId) {
        if (!empty($userId)) {
            $userInfo = $general->fetchDataFromTable('user_details', "user_id = '$userId'");
            if (!empty($userInfo)) {
                $response['users'][] = $userInfo[0];
            }
        }
    }

    $response['labReportSignatories'] = $general->fetchDataFromTable('lab_report_signatories', $signatureCondition);


    $condition = [];
    if (!empty($data['healthFacilityLastModified'])) {
        $condition = "updated_datetime > '" . $data['healthFacilityLastModified'] . "'";
    }

    $response['healthFacilities'] = $general->fetchDataFromTable('health_facilities', $condition);

    $condition = [];
    if (!empty($data['testingLabsLastModified'])) {
        $condition = "updated_datetime > '" . $data['testingLabsLastModified'] . "'";
    }
    $response['testingLabs'] = $general->fetchDataFromTable('testing_labs', $condition);

    $condition = [];
    if (!empty($data['fundingSourcesLastModified'])) {
        $condition = "updated_datetime > '" . $data['fundingSourcesLastModified'] . "'";
    }
    $response['fundingSources'] = $general->fetchDataFromTable('r_funding_sources', $condition);

    $condition = [];
    if (!empty($data['partnersLastModified'])) {
        $condition = "updated_datetime > '" . $data['partnersLastModified'] . "'";
    }
    $response['partners'] = $general->fetchDataFromTable('r_implementation_partners', $condition);

    $condition = [];
    if (!empty($data['geoDivisionsLastModified'])) {
        $condition = "updated_datetime > '" . $data['geoDivisionsLastModified'] . "'";
    }
    $response['geoDivisions'] = $general->fetchDataFromTable('geographical_divisions', $condition);

    if (!empty($data['patientsLastModified'])) {
        $condition = "updated_datetime > '" . $data['patientsLastModified'] . "'";
    }
    $response['patients'] = $general->fetchDataFromTable('patients', $condition);


    if (!empty($response)) {
        // using array_filter without callback will remove keys with empty values
        $payload = json_encode(array_filter($response));
    } else {
        $payload = json_encode([]);
    }
} else {
    $payload =  json_encode(['status' => 'error', 'message' => 'Invalid request']);
}

$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'common-data-sync', 'common', $_SERVER['REQUEST_URI'], json_encode($data), $payload, 'json', $labId);

$sql = 'UPDATE facility_details
            SET facility_attributes
                = JSON_SET(COALESCE(facility_attributes, "{}"), "$.lastHeartBeat", ?)
            WHERE facility_id = ?';
$db->rawQuery($sql, [DateUtility::getCurrentDateTime(), $labId]);

echo $apiService->sendJsonResponse($payload);

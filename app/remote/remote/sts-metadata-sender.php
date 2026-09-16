<?php
//get data from STS send to requesting LIS instance
use Psr\Http\Message\ServerRequestInterface;
use function iter\toArray;
use function iter\map;
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\STS\MetadataSyncScope;

require_once(__DIR__ . "/../../../bootstrap.php");

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

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$data = $apiService->getJsonFromRequest($request, decode: true);
$data = is_array($data) ? $data : [];



$apiRequestId = $apiService->getHeader($request, 'X-Request-ID');
$transactionId = $apiRequestId ?? MiscUtility::generateULID();



$labId = !empty($data['labId']) ? (int) $data['labId'] : null;

// What the lab asked for: see MetadataSyncScope for why both of these matter.
$sinceCondition = static fn(string $key, string $column = 'updated_datetime'): string|array
    => MetadataSyncScope::sinceCondition($data, $key, $column);
$shouldSend = static fn(string $module): bool
    => MetadataSyncScope::sendsModule($data, $module, SYSTEM_CONFIG['modules'] ?? []);

$response = [];

if ($shouldSend('generic-tests')) {

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
        $condition = $sinceCondition($general->stringToCamelCase($table) . 'LastModified');
        $response[$general->stringToCamelCase($table)] = $general->fetchDataFromTable($table, $condition);
    }
}

if ($shouldSend('vl')) {


    $condition = $sinceCondition('vlRejectionReasonsLastModified');
    $response['vlRejectionReasons'] = $general->fetchDataFromTable('r_vl_sample_rejection_reasons', $condition);

    $condition = $sinceCondition('vlTestReasonsLastModified');
    $response['vlTestReasons'] = $general->fetchDataFromTable('r_vl_test_reasons', $condition);


    $condition = $sinceCondition('vlSampleTypesLastModified');
    $response['vlSampleTypes'] = $general->fetchDataFromTable('r_vl_sample_type', $condition);

    $condition = $sinceCondition('vlArtCodesLastModified');
    $response['vlArtCodes'] = $general->fetchDataFromTable('r_vl_art_regimen', $condition);

    $condition = $sinceCondition('vlFailureReasonsLastModified');
    $response['vlFailureReasons'] = $general->fetchDataFromTable('r_vl_test_failure_reasons', $condition);

    $condition = $sinceCondition('vlResultsLastModified');
    $response['vlResults'] = $general->fetchDataFromTable('r_vl_results', $condition);

}


if ($shouldSend('eid')) {

    $condition = $sinceCondition('eidRejectionReasonsLastModified');
    $response['eidRejectionReasons'] = $general->fetchDataFromTable('r_eid_sample_rejection_reasons', $condition);


    $condition = $sinceCondition('eidSampleTypesLastModified');
    $response['eidSampleTypes'] = $general->fetchDataFromTable('r_eid_sample_type', $condition);

    $condition = $sinceCondition('eidResultsLastModified');
    $response['eidResults'] = $general->fetchDataFromTable('r_eid_results', $condition);

    $condition = $sinceCondition('eidReasonForTestingLastModified');
    $response['eidReasonForTesting'] = $general->fetchDataFromTable('r_eid_test_reasons', $condition);

}

if ($shouldSend('covid19')) {

    $condition = $sinceCondition('covid19RejectionReasonsLastModified');
    $response['covid19RejectionReasons'] = $general->fetchDataFromTable('r_covid19_sample_rejection_reasons', $condition);


    $condition = $sinceCondition('covid19SampleTypesLastModified');
    $response['covid19SampleTypes'] = $general->fetchDataFromTable('r_covid19_sample_type', $condition);

    $condition = $sinceCondition('covid19ComorbiditiesLastModified');
    $response['covid19Comorbidities'] = $general->fetchDataFromTable('r_covid19_comorbidities', $condition);

    $condition = $sinceCondition('covid19ResultsLastModified');
    $response['covid19Results'] = $general->fetchDataFromTable('r_covid19_results', $condition);

    $condition = $sinceCondition('covid19SymptomsLastModified');
    $response['covid19Symptoms'] = $general->fetchDataFromTable('r_covid19_symptoms', $condition);

    $condition = $sinceCondition('covid19ReasonForTestingLastModified');
    $response['covid19ReasonForTesting'] = $general->fetchDataFromTable('r_covid19_test_reasons', $condition);

    $condition = $sinceCondition('covid19QCTestKitsLastModified');
    $response['covid19QCTestKits'] = $general->fetchDataFromTable('r_covid19_qc_testkits', $condition);

}

if ($shouldSend('hepatitis')) {

    $condition = $sinceCondition('hepatitisRejectionReasonsLastModified');
    $response['hepatitisRejectionReasons'] = $general->fetchDataFromTable('r_hepatitis_sample_rejection_reasons', $condition);


    $condition = $sinceCondition('hepatitisSampleTypesLastModified');
    $response['hepatitisSampleTypes'] = $general->fetchDataFromTable('r_hepatitis_sample_type', $condition);

    $condition = $sinceCondition('hepatitisComorbiditiesLastModified');
    $response['hepatitisComorbidities'] = $general->fetchDataFromTable('r_hepatitis_comorbidities', $condition);

    $condition = $sinceCondition('hepatitisResultsLastModified');
    $response['hepatitisResults'] = $general->fetchDataFromTable('r_hepatitis_results', $condition);

    $condition = $sinceCondition('hepatitisReasonForTestingLastModified');
    $response['hepatitisReasonForTesting'] = $general->fetchDataFromTable('r_hepatitis_test_reasons', $condition);

}

if ($shouldSend('tb')) {

    $condition = $sinceCondition('tbRejectionReasonsLastModified');
    $response['tbRejectionReasons'] = $general->fetchDataFromTable('r_tb_sample_rejection_reasons', $condition);

    $condition = $sinceCondition('tbSampleTypesLastModified');
    $response['tbSampleTypes'] = $general->fetchDataFromTable('r_tb_sample_type', $condition);

    $condition = $sinceCondition('tbResultsLastModified');
    $response['tbResults'] = $general->fetchDataFromTable('r_tb_results', $condition);

    $condition = $sinceCondition('tbReasonForTestingLastModified');
    $response['tbReasonForTesting'] = $general->fetchDataFromTable('r_tb_test_reasons', $condition);

}

if ($shouldSend('cd4')) {

    $condition = $sinceCondition('cd4RejectionReasonsLastModified');
    $response['cd4RejectionReasons'] = $general->fetchDataFromTable('r_cd4_sample_rejection_reasons', $condition);

    $condition = $sinceCondition('cd4SampleTypesLastModified');
    $response['cd4SampleTypes'] = $general->fetchDataFromTable('r_cd4_sample_types', $condition);

    $condition = $sinceCondition('cd4ReasonForTestingLastModified');
    $response['cd4ReasonForTesting'] = $general->fetchDataFromTable('r_cd4_test_reasons', $condition);

}

// Global Config
$condition = $sinceCondition('globalConfigLastModified') ?: "updated_datetime > '1970-01-01 00:00:00'";
$condition = "COALESCE(remote_sync_needed, 'no') = 'yes' AND $condition";

$response['globalConfig'] = $general->fetchDataFromTable('global_config', $condition);

$condition = [];
$signatureCondition = [];
// Using same facilityLastModified to check if any signatures were added
if ($sinceCondition('facilityLastModified') !== []) {
    $condition = "(" . $sinceCondition('facilityLastModified') . ")";
    if (!empty($labId)) {
        $condition .= " OR (facility_id = $labId)";
    }
    $signatureCondition = $sinceCondition('facilityLastModified', 'added_on');
}

// Facilities
$response['facilities'] = toArray(map(function (array $facility): array {
    unset($facility['sts_token'], $facility['sts_token_expiry']);
    return $facility;
}, $general->fetchDataFromTable('facility_details', $condition)));


$updatedFacilities = [];
if (isset($response['facilities']) && $response['facilities'] !== []) {
    $updatedFacilities = array_unique(array_column($response['facilities'], 'facility_id'));
}

// Lab Users
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


// Health Facilities
$condition = [];
if ($updatedFacilities !== []) {
    $condition[] = "facility_id IN (" . implode(',', $updatedFacilities) . ")";
}
if ($sinceCondition('healthFacilityLastModified') !== []) {
    $condition[] = $sinceCondition('healthFacilityLastModified');
}
$condition = implode(' OR ', $condition);
$response['healthFacilities'] = $general->fetchDataFromTable('health_facilities', $condition);


// Testing Labs
$condition = [];
if ($updatedFacilities !== []) {
    $condition[] = "facility_id IN (" . implode(',', $updatedFacilities) . ")";
}
if ($sinceCondition('testingLabsLastModified') !== []) {
    $condition[] = $sinceCondition('testingLabsLastModified');
}
$condition = implode(' OR ', $condition);
$response['testingLabs'] = $general->fetchDataFromTable('testing_labs', $condition);


// Funding Sources
$condition = $sinceCondition('fundingSourcesLastModified');
$response['fundingSources'] = $general->fetchDataFromTable('r_funding_sources', $condition);


// Implementation Partners
$condition = $sinceCondition('partnersLastModified');
$response['partners'] = $general->fetchDataFromTable('r_implementation_partners', $condition);


// Geographical Divisions

// Set all geo_parent to 0 where geo_parent is NULL or empty
$db->where("geo_parent is NULL OR geo_parent like ''");
$db->update('geographical_divisions', ['geo_parent' => 0]);

$condition = $sinceCondition('geoDivisionsLastModified');

$response['geoDivisions'] = $general->fetchDataFromTable('geographical_divisions', $condition);

// Patients
// $condition = [];
// if (!empty($data['patientsLastModified'])) {
//     $condition = "updated_datetime > '" . $data['patientsLastModified'] . "'";
// }
// $response['patients'] = $general->fetchDataFromTable('patients', $condition);


$payload = $response === [] ? json_encode([]) : JsonUtility::encodeUtf8Json(array_filter($response));

// Rows this lab is actually being handed, across every table in the response.
// This used to count only the per-test-type tables (and not all of those), so a
// changed facility or global setting recorded 0. The lab's own facility,
// health-facility and testing-lab rows are left out: they go back on every call
// whether or not they changed, so counting them would make every call look like
// it moved data. Users are left out for the same reason; they are the contact
// persons of the facilities returned.
$alwaysSentForLab = ['facilities', 'healthFacilities', 'testingLabs'];
$counter = 0;
foreach ($response as $key => $rows) {
    if ($key === 'users' || !is_array($rows)) {
        continue;
    }
    foreach ($rows as $row) {
        if (
            in_array($key, $alwaysSentForLab, true)
            && !empty($labId)
            && (int) ($row['facility_id'] ?? 0) === (int) $labId
        ) {
            continue;
        }
        $counter++;
    }
}

$general->addApiTracking($transactionId, 'intelis-system', $counter, 'common-data-sync', 'common', $_SERVER['REQUEST_URI'], JsonUtility::encodeUtf8Json($data), $payload, 'json', $labId, emptyPoll: $counter === 0);

$sql = 'UPDATE facility_details
            SET facility_attributes
                = JSON_SET(COALESCE(facility_attributes, "{}"), "$.lastHeartBeat", ?)
            WHERE facility_id = ?';
$db->rawQuery($sql, [DateUtility::getCurrentDateTime(), $labId]);

echo ApiService::generateJsonResponse($payload, $request);

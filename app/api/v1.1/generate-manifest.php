<?php

// app/api/v1.1/generate-manifest.php
use Slim\Psr7\Request;
use App\Services\ApiService;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var Request $request */
$request = AppRegistry::get('request');
$origJson = $apiService->getJsonFromRequest($request);
if (JsonUtility::isJSON($origJson) === false) {
    throw new SystemException("Invalid JSON Payload", 400);
}
$input = JsonUtility::decodeJson($origJson, true);
if (
    empty($input) ||
    empty($input['testType']) ||
    empty($input['labId']) ||
    (empty($input['uniqueId']) && empty($input['sampleCode']))
) {
    http_response_code(400);
    throw new SystemException('Invalid request', 400);
}

$transactionId = MiscUtility::generateULID();

/* For API Tracking params */
$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];
$authToken = ApiService::extractBearerToken($request);
$user = $usersService->findUserByApiToken($authToken);

$testTable = TestsService::getTestTableName($input['testType']);
$testPrimaryKey = TestsService::getPrimaryColumn($input['testType']);

$sampleManifestCode = strtoupper(str_replace("-", "", $input['testType']) . date('ymd') . MiscUtility::generateRandomString(4));

try {
    // Modified query to include sample_package_id and sample_package_code
    $sQuery = "SELECT unique_id, sample_code, remote_sample_code, app_sample_code, sample_package_id, sample_package_code, $testPrimaryKey FROM $testTable as vl";

    $where = [];
    /* To check the sample id filter */
    $sampleCode = $input['sampleCode'] ?? [];
    if (!empty($sampleCode)) {
        $sampleCode = implode("','", $sampleCode);
        $where[] = " (vl.sample_code IN ('$sampleCode') OR vl.remote_sample_code IN ('$sampleCode') OR vl.app_sample_code IN ('$sampleCode') ) ";
    }

    // REMOVED: The condition that excludes samples already in manifests
    // $where[] = " ((vl.sample_package_id IS NULL OR vl.sample_package_id = '') AND (vl.sample_package_code IS NULL  OR vl.sample_package_code = ''))";

    $facilityMap = $facilitiesService->getUserFacilityMap($user['user_id']);
    $response = [];

    if (!empty($facilityMap)) {
        $arrFacility = explode(",", (string) $facilityMap);
        if (in_array($input['labId'], $arrFacility) == false) {
            $response = [
                'status' => 'Failed',
                'timestamp' => time(),
                'message' => 'Requested Facilities not mapped. Failed to add samples to manifest'
            ];
        }
        $where[] = " vl.facility_id IN ($facilityMap)";
    }

    $whereString = '';
    if ($where !== []) {
        $whereString = " WHERE " . implode(" AND ", $where);
    }
    $sQuery .= $whereString;

    $rowData = $db->rawQuery($sQuery);

    // Separate samples into different categories
    $availableSamples = [];
    $alreadyInManifest = [];
    $addedToManifest = [];
    $selectedSamplesIds = [];
    $missedSamples = [];
    $manifestHash = '';

    if (isset($rowData) && !empty($rowData)) {
        // Separate samples based on their current manifest status
        $samplesToAdd = [];

        foreach ($rowData as $row) {
            $sampleId = $row['sample_code'] ?? $row['remote_sample_code'];
            $availableSamples[] = $sampleId;

            // Check if sample is already in a manifest
            if (!empty($row['sample_package_id']) && !empty($row['sample_package_code'])) {
                // Sample is already in a manifest
                $manifestCode = $row['sample_package_code'];
                if (!isset($alreadyInManifest[$manifestCode])) {
                    $alreadyInManifest[$manifestCode] = [];
                }
                $alreadyInManifest[$manifestCode][] = [
                    'sampleCode' => $row['sample_code'],
                    'remoteSampleCode' => $row['remote_sample_code'],
                    'appSampleCode' => $row['app_sample_code'],
                    'uniqueId' => $row['unique_id']
                ];
            } else {
                // Sample is available to be added to new manifest
                $samplesToAdd[] = $row;
                $selectedSamplesIds[] = $row[$testPrimaryKey];
            }
        }

        // Create new manifest only if there are samples to add
        if ($samplesToAdd !== []) {

            $manifestHash = $testRequestsService->getManifestHash($selectedSamples, $input['testType']);
            $data = [
                'manifest_code' => $sampleManifestCode,
                'module' => $input['testType'],
                'added_by' => $user['user_id'],
                'lab_id' => $input['labId'],
                'number_of_samples' => count($samplesToAdd),
                'manifest_status' => 'pending',
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            ];
            $db->insert('specimen_manifests', $data);
            $lastId = $db->getInsertId();

            foreach ($samplesToAdd as $key => $row) {
                $tData = [
                    'sample_package_id' => $lastId,
                    'sample_package_code' => $sampleManifestCode,
                    'lab_id' => $input['labId'],
                    'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                    'data_sync' => 0
                ];
                $db->where($testPrimaryKey, $row[$testPrimaryKey]);
                $status = $db->update($testTable, $tData);
                if ($status) {
                    $addedToManifest[] = [
                        'sampleCode' => $row['sample_code'],
                        'remoteSampleCode' => $row['remote_sample_code'],
                        'appSampleCode' => $row['app_sample_code'],
                        'uniqueId' => $row['unique_id']
                    ];
                }
            }
        }

        // Find samples that were requested but not found
        $missedSamples = array_values(array_diff($input['sampleCode'], $availableSamples));

        // Prepare response maintaining backward compatibility
        if ($addedToManifest !== [] || $alreadyInManifest !== []) {
            // Prepare the main data structure (maintaining old format)
            $responseData = [
                'alreadyInManifest' => $missedSamples, // Keep old key name for backward compatibility
                'addedToManifest' => $addedToManifest
            ];

            // Add new existingManifests key for samples already in manifests
            if ($alreadyInManifest !== []) {
                $responseData['existingManifests'] = [];
                foreach ($alreadyInManifest as $manifestCode => $samples) {
                    $responseData['existingManifests'][] = [
                        'manifestCode' => $manifestCode,
                        'samples' => $samples
                    ];
                }
            }

            $payload = [
                'status' => 'success',
                'timestamp' => time(),
                'data' => $responseData
            ];

            // Add manifestCode only if new manifest was created (maintaining old structure)
            if ($addedToManifest !== []) {
                $payload['manifestCode'] = $sampleManifestCode;
                $payload['manifestHash'] = $manifestHash;
            }
        } else {
            // No samples were processed - maintain old error format
            $payload = [
                'status' => 'Failed',
                'timestamp' => time(),
                'message' => 'Possibly incorrect lab. Failed to add samples to manifest',
                'data' => []
            ];
        }
    } else {
        $payload = [
            'status' => 'Failed',
            'timestamp' => time(),
            'message' => 'No samples found matching the criteria',
            'data' => []
        ];
    }
} catch (Throwable $exc) {
    http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'data' => []
    ];
    LoggerUtility::logError($exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'requestUrl' => $requestUrl,
        'stacktrace' => $exc->getTraceAsString()
    ]);
} finally {
    $payload = JsonUtility::encodeUtf8Json($payload);
    $general->addApiTracking($transactionId, $user['user_id'], count($rowData ?? []), 'manifest', $input['testType'], $requestUrl, $origJson, $payload, 'json', null, null, $authToken);
    echo ApiService::generateJsonResponse($payload, $request);
}

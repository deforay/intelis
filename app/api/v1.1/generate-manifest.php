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
    throw new SystemException(_translate('Invalid request'), 400);
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
    // The request names its samples by code, by unique id, or both. Without
    // either list the query would select every sample and put all of them in
    // this manifest, so a request whose lists are empty selects nothing.
    $identifiers = [];
    $sampleCode = array_values(array_filter((array) ($input['sampleCode'] ?? []), is_scalar(...)));
    if ($sampleCode !== []) {
        $sampleCodeList = implode("','", array_map($db->escape(...), $sampleCode));
        $identifiers[] = "vl.sample_code IN ('$sampleCodeList') OR vl.remote_sample_code IN ('$sampleCodeList') OR vl.app_sample_code IN ('$sampleCodeList')";
    }
    $uniqueId = array_values(array_filter((array) ($input['uniqueId'] ?? []), is_scalar(...)));
    if ($uniqueId !== []) {
        $uniqueIdList = implode("','", array_map($db->escape(...), $uniqueId));
        $identifiers[] = "vl.unique_id IN ('$uniqueIdList')";
    }
    $where[] = $identifiers === [] ? ' 1 = 0 ' : ' (' . implode(' OR ', $identifiers) . ') ';

    // REMOVED: The condition that excludes samples already in manifests
    // $where[] = " ((vl.sample_package_id IS NULL OR vl.sample_package_id = '') AND (vl.sample_package_code IS NULL  OR vl.sample_package_code = ''))";

    $facilityMap = $facilitiesService->getUserFacilityMap($user['user_id']);
    // A lab the user is not mapped to gets no manifest. This used to build the
    // failure answer and then make the manifest anyway.
    $labNotMapped = false;

    if (!empty($facilityMap)) {
        $arrFacility = explode(",", (string) $facilityMap);
        if (in_array($input['labId'], $arrFacility) == false) {
            $labNotMapped = true;
            $where[] = ' 1 = 0 ';
        }
        $where[] = " vl.facility_id IN ($facilityMap)";
    }

    $whereString = '';
    if ($where !== []) {
        $whereString = " WHERE " . implode(" AND ", $where);
    }
    $sQuery .= $whereString;
    $sampleOrder = " ORDER BY COALESCE(NULLIF(vl.remote_sample_code, ''), NULLIF(vl.sample_code, ''), NULLIF(vl.app_sample_code, ''), vl.$testPrimaryKey)";
    $sQuery .= $sampleOrder;

    // Choosing the samples and linking them is one step. A client that sends the
    // same request twice used to have both requests read the samples as free,
    // and each linked them to its own manifest: a 1183-sample manifest that
    // listed 320, the rest on a second manifest nobody asked for.
    $db->beginTransaction();

    $rowData = $db->rawQuery($sQuery);
    if (!empty($rowData)) {
        // Lock the matched rows by primary key and read their manifest again: a
        // request for the same samples waits here until this one commits, then
        // finds them taken. By key, so the lock holds these rows and no others.
        // The locking read is the re-read: it sees the latest commit, where a
        // plain read would answer from the snapshot taken before the wait.
        $idList = $db->inIntList(array_column($rowData, $testPrimaryKey));
        $rowData = $db->rawQuery(
            "SELECT unique_id, sample_code, remote_sample_code, app_sample_code, sample_package_id, sample_package_code, $testPrimaryKey
                FROM $testTable as vl WHERE vl.$testPrimaryKey IN ($idList) $sampleOrder FOR UPDATE"
        );
    }

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

            $manifestHash = $testRequestsService->getManifestHash($selectedSamplesIds, $input['testType']);
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

            // Only a sample still free is taken; the lock above already holds
            // them, this keeps the update honest on its own.
            foreach (array_chunk($selectedSamplesIds, 500) as $batch) {
                $db->where($testPrimaryKey, $batch, 'IN');
                $db->where("(sample_package_id IS NULL OR sample_package_id = 0 OR sample_package_code IS NULL OR sample_package_code = '')");
                $db->update($testTable, [
                    'sample_package_id' => $lastId,
                    'sample_package_code' => $sampleManifestCode,
                    'lab_id' => $input['labId'],
                    'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                    'data_sync' => 0
                ]);
            }

            // The manifest lists what was linked, and says so in its count.
            $linked = $db->rawQuery(
                "SELECT unique_id, sample_code, remote_sample_code, app_sample_code
                    FROM $testTable as vl WHERE vl.sample_package_id = ? $sampleOrder",
                [$lastId]
            ) ?: [];
            foreach ($linked as $row) {
                $addedToManifest[] = [
                    'sampleCode' => $row['sample_code'],
                    'remoteSampleCode' => $row['remote_sample_code'],
                    'appSampleCode' => $row['app_sample_code'],
                    'uniqueId' => $row['unique_id']
                ];
            }
            if (count($linked) !== count($samplesToAdd)) {
                $db->where('manifest_id', $lastId);
                $db->update('specimen_manifests', ['number_of_samples' => count($linked)]);
            }
        }

        // Find samples that were requested but not found
        $missedSamples = array_values(array_diff($sampleCode, $availableSamples));

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
            'message' => $labNotMapped
                ? 'Requested Facilities not mapped. Failed to add samples to manifest'
                : 'No samples found matching the criteria',
            'data' => []
        ];
    }
    $db->commitTransaction();
} catch (Throwable $exc) {
    $db->rollbackTransaction();
    http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'data' => []
    ];
    LoggerUtility::logError($exc->getMessage(), [
        'transactionId' => $transactionId,
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

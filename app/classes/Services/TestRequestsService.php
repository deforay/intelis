<?php

namespace App\Services;

use InvalidArgumentException;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const COUNTRY\PNG;
use const SAMPLE_STATUS\REFERRED;
use COUNTRY;
use DateTime;
use Throwable;
use SAMPLE_STATUS;
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Services\GeoLocationsService;

final class TestRequestsService
{
    public function __construct(protected DatabaseService $db, protected CommonService $commonService)
    {
    }

    public function addToSampleCodeQueue(?string $uniqueId, string $testType, string $sampleCollectionDate, ?string $provinceCode = null, ?string $sampleCodeFormat = null, ?string $prefix = null, ?string $accessType = null): bool
    {
        return $this->db->insert("queue_sample_code_generation", [
            'unique_id' => $uniqueId,
            'test_type' => $testType,
            'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
            'province_code' => $provinceCode,
            'sample_code_format' => $sampleCodeFormat,
            'prefix' => $prefix,
            'access_type' => $accessType
        ]);
    }

    /**
     * @return mixed[]
     */
    public function processSampleCodeQueue($uniqueIds = [], $parallelProcess = false, $maxTries = 5, $interval = 5): array
    {
        $response = [];
        $lockFile = null;

        try {
            $isCli = CommonService::isCliRequest();

            // Handle process locking
            if (!$parallelProcess) {
                try {
                    $lockFile = MiscUtility::getLockFile(strtolower(__CLASS__ . '-' . __FUNCTION__));

                    if (!MiscUtility::isLockFileExpired($lockFile, 1800)) {
                        if ($isCli) {
                            echo 'Another instance of the sample code generation script is already running' . PHP_EOL;
                        }
                        return $response;
                    }

                    MiscUtility::touchLockFile($lockFile);
                } catch (Throwable $e) {
                    LoggerUtility::logError("Error initializing lock file: " . $e->getMessage(), ['exception' => $e]);
                    return $response;
                }
            }

            // Get queue items to process
            $queueItems = [];
            $priorityStatuses = [0, 2, 3]; // 0 = new, 2 = retryable failure, 3 = permanent failure (only if explicitly requested)

            foreach ($priorityStatuses as $status) {
                try {
                    $this->db->reset();

                    if (!empty($uniqueIds)) {
                        $uniqueIds = is_array($uniqueIds) ? $uniqueIds : [$uniqueIds];
                        $this->db->where('unique_id', $uniqueIds, 'IN');
                    }

                    $this->db->where('processed', $status);
                    if ($status === 3 && empty($uniqueIds)) {
                        continue;
                    }

                    $queueItems = $this->db->get('queue_sample_code_generation', 100);
                    if (!empty($queueItems)) {
                        break;
                    }
                } catch (Throwable $e) {
                    LoggerUtility::logError("Error fetching queue items (status {$status}): " . $e->getMessage(), [
                        'exception' => $e,
                        'last_db_query' => $this->db->getLastQuery(),
                        'last_db_error' => $this->db->getLastError()
                    ]);
                    if ($status === 0) {
                        return $response;
                    }
                }
            }

            if (empty($queueItems)) {
                return $response;
            }

            // Process queue items
            $counter = 0;
            $sampleCodeColumn = $this->commonService->isSTSInstance() ? 'remote_sample_code' : 'sample_code';

            foreach ($queueItems as $item) {
                $counter++;

                try {
                    // Refresh lock file periodically
                    if (!$parallelProcess && $counter % 10 === 0) {
                        try {
                            if ($lockFile !== '' && $lockFile !== '0') {
                                MiscUtility::touchLockFile($lockFile);
                            }
                        } catch (Throwable $e) {
                            LoggerUtility::logError("Error refreshing lock file: " . $e->getMessage(), ['exception' => $e]);
                        }
                    }

                    // Validate required fields
                    if (empty($item['test_type']) || empty($item['sample_collection_date']) || empty($item['unique_id'])) {
                        $this->updateQueueItem($item['id'], 2, 'Missing required fields');
                        continue;
                    }

                    // Get test configuration
                    try {
                        $formTable = TestsService::getTestTableName($item['test_type']);
                        $primaryKey = TestsService::getPrimaryColumn($item['test_type']);
                        $serviceClass = TestsService::getTestServiceClass($item['test_type']);
                        $testTypeService = ContainerRegistry::get($serviceClass);
                    } catch (Throwable $e) {
                        throw new SystemException("Invalid test type configuration: " . $e->getMessage(), 0, $e);
                    }

                    // Check if sample code already exists
                    try {
                        $sQuery = "SELECT `result_status`, {$sampleCodeColumn} FROM {$formTable} WHERE unique_id = ?";
                        $rowData = $this->db->rawQueryOne($sQuery, [$item['unique_id']]);

                        if (!empty($rowData) && !empty($rowData[$sampleCodeColumn])) {
                            if ($isCli) {
                                echo "Sample ID {$rowData[$sampleCodeColumn]} exists for {$item['unique_id']}" . PHP_EOL;
                            }
                            $this->updateQueueItem($item['id'], 1);
                            continue;
                        }
                    } catch (Throwable $e) {
                        throw new SystemException("Error checking sample code existence: " . $e->getMessage(), 0, $e);
                    }

                    // Get preset status for excluded statuses
                    $presetStatus = null;
                    try {
                        $excludedStatuses = [
                            REJECTED,
                            ACCEPTED,
                            PENDING_APPROVAL
                        ];

                        if (isset($rowData['result_status']) && in_array($rowData['result_status'], $excludedStatuses)) {
                            $presetStatus = $rowData['result_status'];
                        }
                    } catch (Throwable $e) {
                        LoggerUtility::logError("Error getting preset status: " . $e->getMessage(), [
                            'unique_id' => $item['unique_id'],
                            'exception' => $e
                        ]);
                    }

                    // Generate sample code
                    try {
                        $sampleCodeParams = [
                            'sampleCollectionDate' => $item['sample_collection_date'],
                            'provinceCode' => $item['province_code'] ?? null,
                            'testType' => $item['test_type'],
                            'sampleCodeFormat' => $item['sample_code_format'] ?? 'MMYY',
                            'prefix' => $item['prefix'] ?? $testTypeService->shortCode ?? 'T',
                            'insertOperation' => true,
                        ];

                        $sampleJson = $testTypeService->getSampleCode($sampleCodeParams);
                        $sampleData = json_decode((string) $sampleJson, true);

                        if (empty($sampleData) || empty($sampleData['sampleCode'])) {
                            throw new SystemException("Sample code generation returned empty result");
                        }
                    } catch (Throwable $e) {
                        throw new SystemException("Sample code generation failed: " . $e->getMessage(), 0, $e);
                    }

                    // Build test request data
                    try {
                        $accessType = $item['access_type'] ?? null;
                        $tesRequestData = [];

                        if ($this->commonService->isSTSInstance()) {
                            $tesRequestData = [
                                'remote_sample' => 'yes',
                                'remote_sample_code' => $sampleData['sampleCode'],
                                'remote_sample_code_format' => $sampleData['sampleCodeFormat'],
                                'remote_sample_code_key' => $sampleData['sampleCodeKey'],
                                'result_status' => $presetStatus ?? RECEIVED_AT_CLINIC
                            ];

                            if ($accessType === 'testing-lab') {
                                $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                            }
                        } else {
                            $tesRequestData = [
                                'remote_sample' => 'no',
                                'result_status' => $presetStatus ?? RECEIVED_AT_TESTING_LAB,
                                'sample_code' => $sampleData['sampleCode'],
                                'sample_code_format' => $sampleData['sampleCodeFormat'],
                                'sample_code_key' => $sampleData['sampleCodeKey']
                            ];
                        }
                    } catch (Throwable $e) {
                        throw new SystemException("Error building test request data: " . $e->getMessage(), 0, $e);
                    }

                    // Update test record with race condition handling
                    try {
                        $this->db->reset();
                        $this->db->where('unique_id', $item['unique_id']);
                        $this->db->where("({$sampleCodeColumn} IS NULL OR {$sampleCodeColumn} = '' OR {$sampleCodeColumn} = 'null')");

                        $success = $this->db->update($formTable, $tesRequestData);

                        if ($success && $this->db->count > 0) {
                            $response[$item['unique_id']] = $tesRequestData;
                            $this->updateQueueItem($item['id'], 1);
                        } else {
                            // Check if another process updated the record
                            try {
                                $checkQuery = "SELECT {$sampleCodeColumn} FROM {$formTable} WHERE unique_id = ?";
                                $checkData = $this->db->rawQueryOne($checkQuery, [$item['unique_id']]);

                                if (!empty($checkData) && !empty($checkData[$sampleCodeColumn])) {
                                    LoggerUtility::logInfo("Sample ID for {$item['unique_id']} was set by another process: {$checkData[$sampleCodeColumn]}");
                                    $this->updateQueueItem($item['id'], 1);
                                } else {
                                    throw new SystemException("Failed to update record and no concurrent update detected");
                                }
                            } catch (Throwable $e) {
                                throw new SystemException("Error handling concurrent update: " . $e->getMessage(), 0, $e);
                            }
                        }
                    } catch (Throwable $e) {
                        throw new SystemException("Database update failed: " . $e->getMessage(), 0, $e);
                    }
                } catch (Throwable $e) {
                    // Handle individual item errors
                    try {
                        $newStatus = ($item['processed'] ?? 0) >= $maxTries ? 3 : 2;
                        $this->updateQueueItem($item['id'], $newStatus, $e->getMessage());

                        LoggerUtility::logError("Error processing queue item {$item['id']}: " . $e->getMessage(), [
                            'exception' => $e,
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'item_id' => $item['id'],
                            'unique_id' => $item['unique_id'] ?? 'unknown',
                            'last_db_query' => $this->db->getLastQuery(),
                            'last_db_error' => $this->db->getLastError(),
                            'stacktrace' => $e->getTraceAsString()
                        ]);
                    } catch (Throwable $updateError) {
                        LoggerUtility::logError("Error handling item error: " . $updateError->getMessage(), [
                            'exception' => $updateError,
                            'original_exception' => $e
                        ]);
                    }

                    continue;
                }
            }

            return $response;
        } catch (Throwable $e) {
            LoggerUtility::logError("Critical error in processSampleCodeQueue: " . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stacktrace' => $e->getTraceAsString()
            ]);

            return $response;
        } finally {
            // Cleanup lock file
            try {
                if (!$parallelProcess && $lockFile) {
                    MiscUtility::deleteLockFile($lockFile);
                }
            } catch (Throwable $e) {
                LoggerUtility::logError("Error cleaning up lock file: " . $e->getMessage(), [
                    'exception' => $e
                ]);
            }
        }
    }

    private function updateQueueItem($id, int $processed, $error = null)
    {
        $data = [
            'processed' => $processed,
            'updated_datetime' => DateUtility::getCurrentDateTime()
        ];

        if ($error !== null) {
            $data['processing_error'] = $error;
        }

        $this->db->reset();
        $this->db->where('id', $id);
        return $this->db->update('queue_sample_code_generation', $data);
    }

    /**
     * Generate a SHA-256 hash representing the set of sample IDs in a manifest.
     *  $selectedSamples - Array of sample IDs included in the manifest. If empty, will be fetched based on manifestCode.
     *  $testType - The type of test (e.g., 'vl', 'eid', 'covid19', etc.)
     *  $manifestCode - The manifest code associated with the samples. Required if $selectedSamples is empty.
     */
    public function getManifestHash($selectedSamples = [], $testType = null, $manifestCode = null)
    {

        /** @var FileCacheUtility $fileCache */
        $fileCache = ContainerRegistry::get(FileCacheUtility::class);
        $keyHash = hash('sha256', json_encode([
            'selectedSamples' => $selectedSamples,
            'testType' => $testType,
            'manifestCode' => $manifestCode
        ], JSON_UNESCAPED_UNICODE));

        return $fileCache->get($keyHash, function () use ($selectedSamples, $testType, $manifestCode): string {

            $selectedSamples = is_array($selectedSamples) ? $selectedSamples : [];

            $tableName = TestsService::getTestTableName($testType);
            $primaryKey = TestsService::getPrimaryColumn($testType);
            $uniqueIds = [];

            if ($testType !== null && $selectedSamples !== []) {
                $this->db->reset();
                $this->db->where($primaryKey, $selectedSamples, 'IN');
                $uniqueIds = $this->db->getValue($tableName, 'unique_id', null);
            } elseif ($testType !== null && $manifestCode !== null) {
                $this->db->reset();
                $this->db->where('sample_package_code', trim((string) $manifestCode));
                if ($testType == 'tb') {
                    $this->db->orWhere('referral_manifest_code', trim((string) $manifestCode));
                }
                $uniqueIds = $this->db->getValue($tableName, 'unique_id', null);
            }

            if (empty($uniqueIds)) {
                return '';
            }

            sort($uniqueIds, SORT_STRING);

            return hash('sha256', json_encode($uniqueIds, JSON_UNESCAPED_UNICODE));
        });
    }

    /**
     * Compare the locally computed manifest hash with the remote STS hash via verify-manifest API.
     * Returns verification status, http response info, and raw payload for further handling.
     */
    public function verifyManifestHashWithRemote(string $manifestCode, string $testType): array
    {
        $manifestCode = trim($manifestCode);
        $testType = trim($testType);

        $result = [
            'status' => null,
            'manifestCode' => $manifestCode,
            'testType' => $testType,
            'message' => null,
        ];

        if ($manifestCode === '' || $testType === '') {
            $result['message'] = 'Manifest code and test type are required.';
            return $result;
        }

        $remoteURL = rtrim((string) $this->commonService->getRemoteURL(), '/');
        if ($remoteURL === '') {
            $result['message'] = 'Remote STS URL is not configured.';
            return $result;
        }

        $selectedSamples = [];
        if ($testType !== null && $manifestCode !== null) {
            $tableName = TestsService::getTestTableName($testType);
            $primaryKey = TestsService::getPrimaryColumn($testType);
            $this->db->reset();
            $this->db->where('sample_package_code', trim((string) $manifestCode));
            if ($testType === 'tb') {
                $this->db->orWhere('referral_manifest_code', trim((string) $manifestCode));
            }
            $selectedSamples = $this->db->getValue($tableName, $primaryKey, null);
        }

        $localHash = $this->getManifestHash($selectedSamples, $testType, $manifestCode);

        if ($localHash === '') {
            $result['status'] = 'mismatch';
            $result['message'] = 'Unable to compute local manifest hash.';
            return $result;
        }

        $result['localHash'] = $localHash;
        $labId = $this->commonService->getSystemConfig('sc_testing_lab_id') ?? null;

        $apiURL = "$remoteURL/remote/v2/verify-manifest.php";
        $payload = [
            'testType' => $testType,
            'labId' => $labId,
            'manifestCode' => $manifestCode,
            'manifestHash' => $localHash,
        ];

        $transactionId = MiscUtility::generateULID();

        /** @var ApiService $apiService */
        $apiService = ContainerRegistry::get(ApiService::class);
        $stsToken = $this->commonService->getSTSToken();
        if ($stsToken !== null && $stsToken !== '' && $stsToken !== '0') {
            $apiService->setBearerToken($stsToken);
        }

        $httpStatus = null;
        $responseBody = null;

        try {
            $apiResponse = $apiService->post($apiURL, $payload, gzip: false, returnWithStatusCode: true);

            if (is_array($apiResponse)) {
                $httpStatus = $apiResponse['httpStatusCode'] ?? null;
                $responseBody = $apiResponse['body'] ?? '';
            } else {
                $responseBody = $apiResponse;
            }

            $decodedResponse = null;
            if (is_string($responseBody) && JsonUtility::isJSON($responseBody)) {
                $decodedResponse = JsonUtility::decodeJson($responseBody, true);
            }

            if (is_array($decodedResponse)) {
                $result['status'] = $decodedResponse['status'] ?? null;
                $result['remoteResponse'] = $decodedResponse;
                if (!empty($decodedResponse['message'])) {
                    $result['message'] = (string) $decodedResponse['message'];
                }
            } else {
                $result['status'] = 'error';
                $result['remoteResponse'] = $responseBody;
                $result['message'] ??= _translate('Incorrect response when verifying manifest');
            }

            $result['httpStatus'] = $httpStatus;

            $this->commonService->addApiTracking(
                $transactionId,
                $_SESSION['userId'] ?? 'system',
                1,
                'manifest-verify',
                $testType,
                $apiURL,
                $payload,
                $decodedResponse ?? $responseBody,
                'json',
                $labId
            );
        } catch (Throwable $e) {
            $result['status'] = 'error';
            $result['message'] = 'Failed to contact remote verify-manifest endpoint.';
            LoggerUtility::logError('Remote manifest hash verification failed: ' . $e->getMessage(), [
                'manifestCode' => $manifestCode,
                'testType' => $testType,
                'apiURL' => $apiURL,
            ]);
        }

        return $result;
    }

    public function activateSamplesFromManifest($testType, $manifestCode, $sampleCodeFormat = 'MMYY', $prefix = null): int
    {
        try {
            if (empty($manifestCode)) {
                return 0;
            }
            $tableName = TestsService::getTestTableName($testType);

            $sampleQuery = "SELECT * FROM $tableName WHERE sample_package_code = '$manifestCode' ORDER BY request_created_datetime";

            $sampleResult = $this->db->rawQuery($sampleQuery);

            $status = 0;

            $formId = (int) $this->commonService->getGlobalConfig('vl_form');

            $uniqueIdsForSampleCodeGeneration = [];
            foreach ($sampleResult as $sampleRow) {

                $_POST['sampleReceivedOn'] = DateUtility::isoDateFormat($_POST['sampleReceivedOn'] ?? '', true);

                // ONLY IF SAMPLE ID IS NOT ALREADY GENERATED
                if (empty($sampleRow['sample_code']) || $sampleRow['sample_code'] == 'null') {

                    if ($testType == 'hepatitis') {
                        $prefix = $sampleRow['hepatitis_test_type'] ?? $prefix;
                    } elseif ($testType == 'generic-tests') {
                        /** @var GenericTestsService $genericTestsService */
                        $genericTestsService = ContainerRegistry::get(GenericTestsService::class);
                        $testTypeFields = $genericTestsService->getDynamicFields($sampleRow['sample_id']);
                        $prefix = "T";
                        if (!empty($testTypeFields['testDetails']['test_short_code'])) {
                            $prefix = $testTypeFields['testDetails']['test_short_code'];
                        }
                    }

                    $provinceCode = null;
                    // For PNG, we need to get the province code
                    if ($formId == PNG) {
                        /** @var GeoLocationsService $geoService */
                        $geoService = ContainerRegistry::get(GeoLocationsService::class);

                        if (!empty($sampleRow['province_id'])) {
                            $provinceCode = $geoService->getProvinceCodeFromId($sampleRow['province_id']);
                        }
                    }

                    $this->addToSampleCodeQueue(
                        $sampleRow['unique_id'],
                        $testType,
                        DateUtility::isoDateFormat($sampleRow['sample_collection_date'], true),
                        $provinceCode,
                        $sampleCodeFormat ?? 'MMYY',
                        $prefix,
                        'testing-lab'
                    );

                    $uniqueIdsForSampleCodeGeneration[] = $sampleRow['unique_id'];
                }
            }

            if ($uniqueIdsForSampleCodeGeneration !== []) {
                $sampleCodeData = $this->processSampleCodeQueue(uniqueIds: $uniqueIdsForSampleCodeGeneration, parallelProcess: true);
                if ($sampleCodeData !== false && !empty($sampleCodeData)) {

                    //$uniqueIds = array_keys($sampleCodeData);
                    $status = 1;
                }
            }
        } catch (Throwable $e) {
            $status = 0;
            LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), [
                'exception' => $e,
                'last_db_query' => $this->db->getLastQuery(),
                'last_db_error' => $this->db->getLastError(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stacktrace' => $e->getTraceAsString()
            ]);
        } finally {
            $userId = $_SESSION['userId'] ?? null;
            $sampleReceivedOn = $_POST['sampleReceivedOn'] ?? null;
            $timestamp = DateUtility::getCurrentDateTime();

            // Common logic builder
            $buildUpdateData = function (bool $updateStatusAlso) use ($userId, $sampleReceivedOn, $timestamp): array {
                $data = [
                    'last_modified_datetime' => $timestamp
                ];

                if ($updateStatusAlso) {
                    $data['result_status'] = RECEIVED_AT_TESTING_LAB;
                    $data['data_sync'] = 0;
                    $data['last_modified_by'] = $userId;
                }

                if (!empty($sampleReceivedOn)) {
                    $data['sample_tested_datetime'] = null;
                    $data['sample_received_at_lab_datetime'] = $sampleReceivedOn;
                }

                return $data;
            };

            // Case 1: When result_status == RECEIVED_AT_CLINIC or REFERRED
            $this->db->reset();
            $this->db->where('result_status IN (' . RECEIVED_AT_CLINIC . ', ' . REFERRED . ')');
            $this->db->where('sample_code IS NOT NULL');
            $this->db->where('sample_package_code', $manifestCode);
            $this->db->update($tableName, $buildUpdateData(true));

            // This is to allow users to just update the SAMPLE RECEIVED AT LAB DATETIME in bulk
            // Case 2: When result_status != RECEIVED_AT_CLINIC and != REFERRED
            $this->db->reset();
            $this->db->where('result_status NOT IN (' . RECEIVED_AT_CLINIC . ', ' . REFERRED . ')');
            $this->db->where('sample_code IS NOT NULL');
            $this->db->where('sample_package_code', $manifestCode);
            $this->db->update($tableName, $buildUpdateData(false));

            return $status;
        }
    }

    /**
     * Find a matching local record based on the provided remotely received data
     * From CLOUD Sample Tracking System (STS) to Local LIS or vice versa.
     * @param array $recordFromOtherSystem The request data from the other system.
     * @param string $tableName The name of the table to search in.
     * @param string $primaryKeyName The name of the primary key column.
     * @return array The matching record, or an empty array if not found.
     */
    public function findMatchingLocalRecord(array $recordFromOtherSystem, string $tableName, string $primaryKeyName): array
    {
        // validate identifiers to avoid injection
        $idPattern = '/^\w+$/';
        if (!preg_match($idPattern, $tableName) || !preg_match($idPattern, $primaryKeyName)) {
            throw new InvalidArgumentException('Invalid table or primary key name');
        }
        $quote = fn($ident): string => "`$ident`";
        $quotedTable = $quote(ident: $tableName);

        // Build select fields (exclude primary key)
        $columns = array_diff(array_keys($recordFromOtherSystem), [$primaryKeyName]);
        $safeCols = array_filter($columns, fn($c): int|false => preg_match($idPattern, (string) $c));
        if ($safeCols !== []) {
            $select = implode(', ', array_map($quote, $safeCols));
            $fields = $quote($primaryKeyName) . ', ' . $select;
        } else {
            $fields = '*';
        }

        // Normalize incoming values
        $remoteSampleCode = trim((string) ($recordFromOtherSystem['remote_sample_code'] ?? ''));
        $sampleCode = trim((string) ($recordFromOtherSystem['sample_code'] ?? ''));
        $labId = $recordFromOtherSystem['lab_id'] ?? null;
        $facilityId = $recordFromOtherSystem['facility_id'] ?? null;
        $uniqueId = trim((string) ($recordFromOtherSystem['unique_id'] ?? ''));

        // Candidate matching conditions in priority order
        $candidates = [];

        if ($remoteSampleCode !== '') {
            $candidates[] = [
                'where' => 'remote_sample_code = ?',
                'params' => [$remoteSampleCode],
                //'match_criteria' => 'remote_sample_code',
            ];
        }

        if ($sampleCode !== '' && $labId !== null && $labId !== '') {
            $candidates[] = [
                'where' => 'sample_code = ? AND lab_id = ?',
                'params' => [$sampleCode, $labId],
                //'match_criteria' => 'sample_code_and_lab_id',
            ];
        }

        if ($uniqueId !== '') {
            $candidates[] = [
                'where' => 'unique_id = ?',
                'params' => [$uniqueId],
                //'match_criteria' => 'unique_id',
            ];
        }

        if ($sampleCode !== '' && $facilityId !== null && $facilityId !== '') {
            $candidates[] = [
                'where' => 'sample_code = ? AND facility_id = ?',
                'params' => [$sampleCode, $facilityId],
                //'match_criteria' => 'sample_code_and_facility_id',
            ];
        }

        if ($candidates === []) {
            return [];
        }

        $found = [];
        //$matchedByCriteria = null;

        foreach ($candidates as $cand) {
            $selectPart = $fields;
            $sql = "SELECT {$selectPart} FROM {$quotedTable} WHERE {$cand['where']} FOR UPDATE";
            $res = $this->db->rawQueryOne($sql, $cand['params']);
            if (!empty($res)) {
                $found = $res;
                //$matchedByCriteria = $cand['match_criteria'];
                break;
            }
        }

        if (empty($found)) {
            return [];
        }

        return $found;
    }



    /**
     * Duplicate detection
     *
     * @param array $vlFulldata Complete sample data array with actual column names
     * @param string $testType The type of test (vl, cd4, eid, etc.)
     * @param int $withinDays Number of days to check for duplicates (default: 7)
     * @param bool $requireApiSource Whether to only consider duplicates from API sources (default: true)
     * @return array Returns duplicate detection result with details
     */
    public function detectDuplicateSample(array $vlFulldata, string $testType = 'vl', int $withinDays = 7, bool $requireApiSource = true): array
    {
        try {
            // Get test type configuration
            $table = TestsService::getTestTableName($testType);
            $primaryColumn = TestsService::getPrimaryColumn($testType);
            $patientIdColumn = TestsService::getPatientIdColumn($testType);
            $patientFirstNameColumn = TestsService::getPatientFirstNameColumn($testType);
            $patientLastNameColumn = TestsService::getPatientLastNameColumn($testType);

            // Extract and normalize parameters from the full data array
            $patientId = $this->normalizeString($vlFulldata[$patientIdColumn] ?? $vlFulldata['patient_art_no'] ?? null);
            $patientFirstName = $this->normalizeString($vlFulldata[$patientFirstNameColumn] ?? $vlFulldata['patient_first_name'] ?? null);
            $patientLastName = $this->normalizeString($vlFulldata[$patientLastNameColumn] ?? $vlFulldata['patient_last_name'] ?? null);
            $facilityId = $vlFulldata['facility_id'] ?? null;
            $labId = $vlFulldata['lab_id'] ?? null;
            $collectionDate = $vlFulldata['sample_collection_date'] ?? null;
            $excludeSampleId = $vlFulldata['excludeSampleId'] ?? null;

            // Validate minimum required data
            if (empty($collectionDate)) {
                return [
                    'isDuplicate' => false,
                    'error' => 'Collection date is required for duplicate detection'
                ];
            }

            // Must have either patientId OR patient name (after normalization)
            $hasPatientId = $patientId !== null && $patientId !== '' && $patientId !== '0';
            $hasPatientName = $patientFirstName !== null && $patientFirstName !== '' && $patientFirstName !== '0' || $patientLastName !== null && $patientLastName !== '' && $patientLastName !== '0';

            if (!$hasPatientId && !$hasPatientName) {
                return [
                    'isDuplicate' => false,
                    'error' => 'Either patient ID or patient name is required'
                ];
            }

            // Ensure collection date is properly formatted
            if ($collectionDate instanceof DateTime) {
                $collectionDate = $collectionDate->format('Y-m-d H:i:s');
            }

            // Build the duplicate detection query with proper parameter handling
            $whereConditions = [];
            $queryParams = [];

            // Patient identification with multiple matching strategies
            $patientMatchConditions = [];

            if ($hasPatientId) {
                $patientMatchConditions[] = "COALESCE(TRIM(t1.$patientIdColumn), '') = ?";
                $queryParams[] = $patientId;
            }

            if ($hasPatientName) {
                if ($testType === 'vl') {
                    // For VL, full name is stored in first_name field
                    $patientMatchConditions[] = "COALESCE(TRIM(t1.$patientFirstNameColumn), '') = ?";
                    $queryParams[] = $patientFirstName;
                } elseif ($patientFirstName !== null && $patientFirstName !== '' && $patientFirstName !== '0' && ($patientLastName !== null && $patientLastName !== '' && $patientLastName !== '0')) {
                    // For other tests, use separate first/last name fields
                    $patientMatchConditions[] = "(COALESCE(TRIM(t1.$patientFirstNameColumn), '') = ? AND COALESCE(TRIM(t1.$patientLastNameColumn), '') = ?)";
                    $queryParams[] = $patientFirstName;
                    $queryParams[] = $patientLastName;
                } elseif ($patientFirstName !== null && $patientFirstName !== '' && $patientFirstName !== '0') {
                    $patientMatchConditions[] = "COALESCE(TRIM(t1.$patientFirstNameColumn), '') = ?";
                    $queryParams[] = $patientFirstName;
                } elseif ($patientLastName !== null && $patientLastName !== '' && $patientLastName !== '0') {
                    $patientMatchConditions[] = "COALESCE(TRIM(t1.$patientLastNameColumn), '') = ?";
                    $queryParams[] = $patientLastName;
                }
            }

            if ($patientMatchConditions === []) {
                return [
                    'isDuplicate' => false,
                    'error' => 'No valid patient identifiers found'
                ];
            }

            $whereConditions[] = "(" . implode(' OR ', $patientMatchConditions) . ")";

            // Date range check
            $startDate = date('Y-m-d H:i:s', strtotime("$collectionDate -$withinDays days"));
            $endDate = date('Y-m-d H:i:s', strtotime("$collectionDate +$withinDays days"));

            $whereConditions[] = "t1.sample_collection_date BETWEEN ? AND ?";
            $queryParams[] = $startDate;
            $queryParams[] = $endDate;

            // Facility filter (if provided)
            if (!empty($facilityId)) {
                $whereConditions[] = "t1.facility_id = ?";
                $queryParams[] = $facilityId;
            }

            // Lab filter (if provided)
            if (!empty($labId)) {
                $whereConditions[] = "t1.lab_id = ?";
                $queryParams[] = $labId;
            }

            // Exclude current sample (for updates)
            if (!empty($excludeSampleId)) {
                $whereConditions[] = "t1.$primaryColumn != ?";
                $queryParams[] = $excludeSampleId;
            }

            // Only consider samples that have collection dates
            $whereConditions[] = "t1.sample_collection_date IS NOT NULL";

            // API source filter
            if ($requireApiSource) {
                $whereConditions[] = "(COALESCE(t1.source_of_request, '') LIKE '%api%' OR COALESCE(t1.source_of_request, '') = 'api')";
            }

            // Data quality filters using COALESCE
            $whereConditions[] = "(
                COALESCE(TRIM(t1.$patientIdColumn), '') != ''
                OR
                (COALESCE(TRIM(t1.$patientFirstNameColumn), '') != '' OR COALESCE(TRIM(t1.$patientLastNameColumn), '') != '')
            )";

            $whereClause = implode(' AND ', $whereConditions);

            // Build the complete query
            $query = "
                SELECT
                    t1.$primaryColumn,
                    COALESCE(TRIM(t1.$patientIdColumn), '') as patient_id,
                    COALESCE(TRIM(t1.$patientFirstNameColumn), '') as patient_first_name,
                    COALESCE(TRIM(t1.$patientLastNameColumn), '') as patient_last_name,
                    t1.sample_collection_date,
                    t1.facility_id,
                    t1.lab_id,
                    COALESCE(t1.source_of_request, 'manual') as source_of_request,
                    t1.sample_code,
                    t1.remote_sample_code,
                    t1.app_sample_code,
                    t1.result_status,
                    f.facility_name,
                    l.facility_name as lab_name,
                    DATEDIFF(?, t1.sample_collection_date) as days_difference,
                    ABS(DATEDIFF(?, t1.sample_collection_date)) as days_abs_difference,
                    TRIM(CONCAT(
                        COALESCE(t1.$patientFirstNameColumn, ''),
                        CASE
                            WHEN COALESCE(t1.$patientFirstNameColumn, '') != '' AND COALESCE(t1.$patientLastNameColumn, '') != ''
                            THEN ' '
                            ELSE ''
                        END,
                        COALESCE(t1.$patientLastNameColumn, '')
                    )) as full_name,
                    CASE
                        WHEN COALESCE(TRIM(t1.$patientIdColumn), '') = ? THEN 100
                        WHEN COALESCE(TRIM(t1.$patientFirstNameColumn), '') = ? THEN 90
                        WHEN COALESCE(TRIM(t1.$patientLastNameColumn), '') = ? THEN 85
                        WHEN COALESCE(TRIM(t1.$patientFirstNameColumn), '') = ? AND COALESCE(TRIM(t1.$patientLastNameColumn), '') = ? THEN 95
                        ELSE 0
                    END as match_score
                FROM $table as t1
                LEFT JOIN facility_details as f ON t1.facility_id = f.facility_id
                LEFT JOIN facility_details as l ON t1.lab_id = l.facility_id
                WHERE $whereClause
                ORDER BY match_score DESC, ABS(DATEDIFF(?, t1.sample_collection_date)) ASC, t1.sample_collection_date DESC
                LIMIT 10
            ";

            // Prepare parameters for DATEDIFF and match score calculations - FIXED ORDER
            $scoringParams = [
                $collectionDate,     // for first DATEDIFF in SELECT
                $collectionDate,     // for second DATEDIFF in SELECT
                $patientId ?? '',    // for patient ID match score
                $patientFirstName ?? '', // for first name match score
                $patientLastName ?? '',  // for last name match score
                $patientFirstName ?? '', // for combined first name in score
                $patientLastName ?? '',  // for combined last name in score
                $collectionDate      // for final ORDER BY DATEDIFF
            ];

            // Combine all parameters in correct order
            $finalParams = array_merge($scoringParams, $queryParams);

            // Execute the query with error handling
            $duplicates = $this->db->rawQuery($query, $finalParams);

            if (empty($duplicates)) {
                return [
                    'isDuplicate' => false,
                    'duplicates' => [],
                    'message' => 'No duplicate samples found'
                ];
            }

            // Analyze duplicates and determine risk level
            $riskLevel = 'low';
            $highRiskCount = 0;
            $mediumRiskCount = 0;

            foreach ($duplicates as &$duplicate) {
                $daysDiff = abs($duplicate['days_abs_difference']);

                if ($daysDiff <= 1) {
                    $duplicate['risk_level'] = 'high';
                    $highRiskCount++;
                } elseif ($daysDiff <= 3) {
                    $duplicate['risk_level'] = 'medium';
                    $mediumRiskCount++;
                } else {
                    $duplicate['risk_level'] = 'low';
                }

                // Format dates for display
                $duplicate['sample_collection_date_formatted'] = DateUtility::humanReadableDateFormat($duplicate['sample_collection_date'], true);

                // Add match confidence based on score
                if ($duplicate['match_score'] >= 100) {
                    $duplicate['match_confidence'] = 'exact_id';
                } elseif ($duplicate['match_score'] >= 95) {
                    $duplicate['match_confidence'] = 'exact_full_name';
                } elseif ($duplicate['match_score'] >= 90) {
                    $duplicate['match_confidence'] = 'exact_first_name';
                } elseif ($duplicate['match_score'] >= 85) {
                    $duplicate['match_confidence'] = 'exact_last_name';
                } else {
                    $duplicate['match_confidence'] = 'partial';
                }
            }

            // Overall risk assessment
            if ($highRiskCount > 0) {
                $riskLevel = 'high';
            } elseif ($mediumRiskCount > 0) {
                $riskLevel = 'medium';
            }

            return [
                'isDuplicate' => true,
                'duplicates' => $duplicates,
                'duplicateCount' => count($duplicates),
                'riskLevel' => $riskLevel,
                'highRiskCount' => $highRiskCount,
                'mediumRiskCount' => $mediumRiskCount,
                'lowRiskCount' => count($duplicates) - $highRiskCount - $mediumRiskCount,
                'message' => "Found " . count($duplicates) . " potential duplicate(s) within $withinDays days",
                'withinDays' => $withinDays,
                'searchCriteria' => [
                    'patientId' => $patientId,
                    'patientFirstName' => $patientFirstName,
                    'patientLastName' => $patientLastName,
                    'facilityId' => $facilityId,
                    'labId' => $labId,
                    'collectionDate' => $collectionDate,
                    'testType' => $testType
                ]
            ];
        } catch (Throwable $e) {
            LoggerUtility::logError("Duplicate detection error: " . $e->getMessage(), [
                'vlFulldata' => $vlFulldata,
                'testType' => $testType,
                'query' => $query ?? 'Query not built',
                'params' => $finalParams ?? 'Params not built',
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'isDuplicate' => false,
                'error' => 'Error during duplicate detection: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Normalize string values for consistent comparison
     *
     * @param string|null $value
     * @return string|null
     */
    private function normalizeString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Trim whitespace and convert to uppercase for consistent comparison
        $normalized = trim(strtoupper($value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Patient duplicate analysis
     */
    public function getPatientDuplicateAnalysis(string $patientIdentifier, string $testType = 'vl', ?int $facilityId = null, int $dayRange = 30): array
    {
        try {
            $table = TestsService::getTestTableName($testType);
            $primaryColumn = TestsService::getPrimaryColumn($testType);
            $patientIdColumn = TestsService::getPatientIdColumn($testType);
            $patientFirstNameColumn = TestsService::getPatientFirstNameColumn($testType);
            $patientLastNameColumn = TestsService::getPatientLastNameColumn($testType);

            $whereConditions = [];
            $queryParams = [];

            $normalizedIdentifier = $this->normalizeString($patientIdentifier);

            // Patient identification
            $whereConditions[] = "(
                COALESCE(UPPER(TRIM($patientIdColumn)), '') = ?
                OR COALESCE(UPPER(TRIM($patientFirstNameColumn)), '') LIKE ?
                OR COALESCE(UPPER(TRIM($patientLastNameColumn)), '') LIKE ?
                OR UPPER(TRIM(CONCAT(COALESCE($patientFirstNameColumn, ''), ' ', COALESCE($patientLastNameColumn, '')))) LIKE ?
            )";
            $queryParams[] = $normalizedIdentifier;
            $queryParams[] = "%$normalizedIdentifier%";
            $queryParams[] = "%$normalizedIdentifier%";
            $queryParams[] = "%$normalizedIdentifier%";

            // Date range
            $startDate = date('Y-m-d H:i:s', strtotime("-$dayRange days"));
            $whereConditions[] = "sample_collection_date >= ?";
            $queryParams[] = $startDate;

            // Facility filter
            if ($facilityId) {
                $whereConditions[] = "facility_id = ?";
                $queryParams[] = $facilityId;
            }

            $whereConditions[] = "sample_collection_date IS NOT NULL";
            $whereConditions[] = "(
                COALESCE(TRIM($patientIdColumn), '') != ''
                OR
                (COALESCE(TRIM($patientFirstNameColumn), '') != '' AND COALESCE(TRIM($patientLastNameColumn), '') != '')
            )";

            $whereClause = implode(' AND ', $whereConditions);

            $query = "
                SELECT
                    $primaryColumn,
                    COALESCE(TRIM($patientIdColumn), '') as patient_id,
                    COALESCE(TRIM($patientFirstNameColumn), '') as patient_first_name,
                    COALESCE(TRIM($patientLastNameColumn), '') as patient_last_name,
                    sample_collection_date,
                    facility_id,
                    lab_id,
                    COALESCE(source_of_request, 'manual') as source_of_request,
                    sample_code,
                    result_status,
                    request_created_datetime,
                    DATE(sample_collection_date) as collection_date_only
                FROM $table
                WHERE $whereClause
                ORDER BY sample_collection_date DESC
            ";

            /** @var DatabaseService $db */
            $db = $this->db ?? ContainerRegistry::get(DatabaseService::class);
            $samples = $db->rawQuery($query, $queryParams);

            // Group samples by date to identify clusters
            $sampleGroups = [];
            foreach ($samples as $sample) {
                $dateKey = $sample['collection_date_only'];
                $sampleGroups[$dateKey][] = $sample;
            }

            // Identify duplicate groups (same day collections)
            $duplicateGroups = array_filter($sampleGroups, fn($group): bool => count($group) > 1);

            return [
                'totalSamples' => count($samples),
                'duplicateGroups' => count($duplicateGroups),
                'duplicateSamples' => array_sum(array_map('count', $duplicateGroups)),
                'samples' => $samples,
                'groupedByDate' => $sampleGroups,
                'duplicateGroupsData' => $duplicateGroups,
                'searchTerm' => $normalizedIdentifier
            ];
        } catch (Throwable $e) {
            LoggerUtility::logError("Patient duplicate analysis error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}

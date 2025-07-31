<?php

namespace App\Services;

use COUNTRY;
use Throwable;
use SAMPLE_STATUS;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Services\GeoLocationsService;

final class TestRequestsService
{
    protected DatabaseService $db;
    protected CommonService $commonService;

    public function __construct(DatabaseService $db, CommonService $commonService)
    {
        $this->db = $db;
        $this->commonService = $commonService;
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

    public function processSampleCodeQueue($uniqueIds = [], $parallelProcess = false, $maxTries = 5, $interval = 5)
    {
        $response = [];
        $lockFile = null;

        try {
            $isCli = CommonService::isCliRequest();

            // Handle process locking
            if (!$parallelProcess) {
                try {
                    $lockFile = MiscUtility::getLockFile(__CLASS__ . '-' . __FUNCTION__);

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
            try {
                $this->db->reset();

                if (!empty($uniqueIds)) {
                    $uniqueIds = is_array($uniqueIds) ? $uniqueIds : [$uniqueIds];
                    $this->db->where('unique_id', $uniqueIds, 'IN');
                }

                $this->db->where('processed = 0');
                $queueItems = $this->db->get('queue_sample_code_generation', 100);
            } catch (Throwable $e) {
                LoggerUtility::logError("Error fetching queue items: " . $e->getMessage(), [
                    'exception' => $e,
                    'last_db_query' => $this->db->getLastQuery(),
                    'last_db_error' => $this->db->getLastError()
                ]);
                return $response;
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
                            if ($lockFile) {
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
                        $primaryKey = TestsService::getTestPrimaryKeyColumn($item['test_type']);
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
                            SAMPLE_STATUS\REJECTED,
                            SAMPLE_STATUS\ACCEPTED,
                            SAMPLE_STATUS\PENDING_APPROVAL
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
                        $sampleData = json_decode((string)$sampleJson, true);

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
                                'result_status' => $presetStatus ?? SAMPLE_STATUS\RECEIVED_AT_CLINIC
                            ];

                            if ($accessType === 'testing-lab') {
                                $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                            }
                        } else {
                            $tesRequestData = [
                                'remote_sample' => 'no',
                                'result_status' => $presetStatus ?? SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB,
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
                        $this->updateQueueItem($item['id'], 2, $e->getMessage());

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

    private function updateQueueItem($id, $processed, $error = null)
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

    public function activateSamplesFromManifest($testType, $manifestCode, $sampleCodeFormat = 'MMYY', $prefix = null)
    {
        try {
            if (empty($manifestCode)) {
                return 0;
            }
            $tableName = TestsService::getTestTableName($testType);

            $sampleQuery = "SELECT * FROM $tableName WHERE sample_package_code = '$manifestCode'";

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
                    if ($formId == COUNTRY\PNG) {
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

            if (!empty($uniqueIdsForSampleCodeGeneration)) {
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
            $buildUpdateData = function (bool $isClinic) use ($userId, $sampleReceivedOn, $timestamp) {
                $data = [
                    'last_modified_datetime' => $timestamp
                ];

                if ($isClinic) {
                    $data['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
                    $data['data_sync'] = 0;
                    $data['last_modified_by'] = $userId;
                }

                if (!empty($sampleReceivedOn)) {
                    $data['sample_tested_datetime'] = null;
                    $data['sample_received_at_lab_datetime'] = $sampleReceivedOn;
                }

                return $data;
            };

            // Case 1: When result_status == RECEIVED_AT_CLINIC
            $this->db->reset();
            $this->db->where('result_status = ' . SAMPLE_STATUS\RECEIVED_AT_CLINIC);
            $this->db->where('sample_code IS NOT NULL');
            $this->db->where('sample_package_code', $manifestCode);
            $this->db->update($tableName, $buildUpdateData(true));

            // This is to allow users to just update the SAMPLE RECEIVED AT LAB DATETIME in bulk
            // Case 2: When result_status != RECEIVED_AT_CLINIC
            $this->db->reset();
            $this->db->where('result_status != ' . SAMPLE_STATUS\RECEIVED_AT_CLINIC);
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
        $idPattern = '/^[A-Za-z0-9_]+$/';
        if (!preg_match($idPattern, $tableName) || !preg_match($idPattern, $primaryKeyName)) {
            throw new \InvalidArgumentException('Invalid table or primary key name');
        }
        $quote = fn($ident) => "`$ident`";
        $quotedTable = $quote(ident: $tableName);

        // Build select fields (exclude primary key)
        $columns = array_diff(array_keys($recordFromOtherSystem), [$primaryKeyName]);
        $safeCols = array_filter($columns, fn($c) => preg_match($idPattern, $c));
        if ($safeCols) {
            $select = implode(', ', array_map($quote, $safeCols));
            $fields = $quote($primaryKeyName) . ', ' . $select;
        } else {
            $fields = '*';
        }

        // Normalize incoming values
        $remoteSampleCode = trim((string)($recordFromOtherSystem['remote_sample_code'] ?? ''));
        $sampleCode = trim((string)($recordFromOtherSystem['sample_code'] ?? ''));
        $labId = $recordFromOtherSystem['lab_id'] ?? null;
        $facilityId = $recordFromOtherSystem['facility_id'] ?? null;
        $uniqueId = trim((string)($recordFromOtherSystem['unique_id'] ?? ''));

        // Candidate matching conditions in priority order
        $candidates = [];

        if ($remoteSampleCode !== '') {
            $candidates[] = [
                'where' => 'remote_sample_code = ?',
                'params' => [$remoteSampleCode],
                'match_criteria' => 'remote_sample_code',
            ];
        }

        if ($sampleCode !== '' && $labId !== null && $labId !== '') {
            $candidates[] = [
                'where' => 'sample_code = ? AND lab_id = ?',
                'params' => [$sampleCode, $labId],
                'match_criteria' => 'sample_code_and_lab_id',
            ];
        }

        if ($uniqueId !== '') {
            $candidates[] = [
                'where' => 'unique_id = ?',
                'params' => [$uniqueId],
                'match_criteria' => 'unique_id',
            ];
        }

        if ($sampleCode !== '' && $facilityId !== null && $facilityId !== '') {
            $candidates[] = [
                'where' => 'sample_code = ? AND facility_id = ?',
                'params' => [$sampleCode, $facilityId],
                'match_criteria' => 'sample_code_and_facility_id',
            ];
        }

        if (empty($candidates)) {
            return [];
        }

        $found = [];
        $matchedByCriteria = null;

        foreach ($candidates as $cand) {
            $selectPart = $fields === '*' ? '*' : $fields;
            $sql = "SELECT {$selectPart} FROM {$quotedTable} WHERE {$cand['where']} FOR UPDATE";
            $res = $this->db->rawQueryOne($sql, $cand['params']);
            if (!empty($res)) {
                $found = $res;
                $matchedByCriteria = $cand['match_criteria'];
                break;
            }
        }

        if (empty($found)) {
            return [];
        }

        // // Backfill remote_sample_code if we matched on a fallback and incoming has it
        // if (
        //     $remoteSampleCode !== '' &&
        //     $matchedByCriteria !== 'remote_sample_code' &&
        //     empty($found['remote_sample_code'] ?? null)
        // ) {
        //     $updateSql = sprintf(
        //         "UPDATE %s SET remote_sample_code = ? WHERE %s = ?",
        //         $quote($tableName),
        //         $quote($primaryKeyName)
        //     );
        //     $this->db->rawQuery($updateSql, [$remoteSampleCode, $found[$primaryKeyName]]);
        //     $found['remote_sample_code'] = $remoteSampleCode;
        // }

        return $found;
    }
}

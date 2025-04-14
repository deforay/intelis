<?php

namespace App\Services;

use COUNTRY;
use Throwable;
use SAMPLE_STATUS;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Services\GeoLocationsService;
use App\Abstracts\AbstractTestService;

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
        try {
            $isCli = CommonService::isCliRequest();
            $lockFile = TEMP_PATH . '/sample_code_generation.lock';

            if ($parallelProcess === false) {
                // Check if another instance is already running (with timeout protection)
                if (
                    file_exists($lockFile) &&
                    (filemtime($lockFile) > (time() - $interval * 2)) &&
                    (filemtime($lockFile) > (time() - 1800))
                ) {
                    if ($isCli) {
                        echo 'Another instance of the sample code generation script is already running' . PHP_EOL;
                    }
                    return $response;
                }

                // Create or update the lock file
                touch($lockFile);
            }

            $sampleCodeColumn = $this->commonService->isSTSInstance() ? 'remote_sample_code' : 'sample_code';

            $this->db->reset();
            if (!empty($uniqueIds)) {
                $uniqueIds = is_array($uniqueIds) ? $uniqueIds : [$uniqueIds];
                $this->db->where('unique_id', $uniqueIds, 'IN');
            }
            $this->db->where('processed = 0');
            $queueItems = $this->db->get('queue_sample_code_generation', 100);

            if (!empty($queueItems)) {
                foreach ($queueItems as $item) {
                    if ($parallelProcess === false) {
                        // Touch the lock file to keep it live
                        touch($lockFile);
                    }

                    if (empty($item['test_type']) || empty($item['sample_collection_date']) || empty($item['unique_id'])) {
                        // Skip invalid items and mark them as processed with error
                        $this->updateQueueItem($item['id'], 2, 'Missing required fields');
                        continue;
                    }

                    try {
                        $formTable = TestsService::getTestTableName($item['test_type']);
                        $primaryKey = TestsService::getTestPrimaryKeyColumn($item['test_type']);
                        $serviceClass = TestsService::getTestServiceClass($item['test_type']);

                        /** @var AbstractTestService $testTypeService */
                        $testTypeService = ContainerRegistry::get($serviceClass);

                        // Check if sample code already exists
                        $sQuery = "SELECT `result_status`, $sampleCodeColumn FROM $formTable WHERE unique_id = ?";
                        $rowData = $this->db->rawQueryOne($sQuery, [$item['unique_id']]);

                        if (!empty($rowData) && !empty($rowData[$sampleCodeColumn])) {
                            if ($isCli) {
                                echo "Sample Code {$rowData[$sampleCodeColumn]} exists for {$item['unique_id']}" . PHP_EOL;
                            }
                            $this->updateQueueItem($item['id'], 1);
                            continue;
                        }

                        $excludedStatuses = [
                            SAMPLE_STATUS\REJECTED,
                            SAMPLE_STATUS\ACCEPTED,
                            SAMPLE_STATUS\PENDING_APPROVAL
                        ];

                        $presetStatus = null;
                        if (isset($rowData['result_status']) && in_array($rowData['result_status'], $excludedStatuses)) {
                            $presetStatus = $rowData['result_status'];
                        }

                        $sampleCodeParams = [
                            'sampleCollectionDate' => $item['sample_collection_date'],
                            'provinceCode' => $item['province_code'] ?? null,
                            'testType' => $item['test_type'],
                            'sampleCodeFormat' => $item['sample_code_format'] ?? 'MMYY',
                            'prefix' => $item['prefix'] ?? $testTypeService->shortCode ?? 'T',
                            'insertOperation' => true,
                        ];

                        // Generate the sample code using the improved method
                        $sampleJson = $testTypeService->getSampleCode($sampleCodeParams);
                        $sampleData = json_decode((string)$sampleJson, true);

                        if (empty($sampleData) || empty($sampleData['sampleCode'])) {
                            throw new SystemException("Failed to generate sample code for {$item['unique_id']}");
                        }

                        $accessType = $item['access_type'] ?? null;
                        $tesRequestData = [];

                        if ($this->commonService->isSTSInstance()) {
                            $tesRequestData['remote_sample'] = 'yes';
                            $tesRequestData['remote_sample_code'] = $sampleData['sampleCode'];
                            $tesRequestData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                            $tesRequestData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                            $tesRequestData['result_status'] = (null !== $presetStatus) ? $presetStatus : SAMPLE_STATUS\RECEIVED_AT_CLINIC;
                            if ($accessType === 'testing-lab') {
                                $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                            }
                        } else {
                            $tesRequestData['remote_sample'] = 'no';
                            $tesRequestData['result_status'] = (null !== $presetStatus) ? $presetStatus : SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
                            $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                            $tesRequestData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                            $tesRequestData['sample_code_key'] = $sampleData['sampleCodeKey'];
                        }

                        $this->db->reset();
                        // Use conditional update to handle potential race conditions
                        $this->db->where('unique_id', $item['unique_id']);

                        // Add condition to only update if sample code is still empty
                        // This prevents overwriting if another process has already set it
                        $this->db->where("($sampleCodeColumn IS NULL OR $sampleCodeColumn = '' OR $sampleCodeColumn = 'null')");

                        $success = $this->db->update($formTable, $tesRequestData);

                        if ($success && $this->db->count > 0) {
                            // Update was successful and actually modified a row
                            $response[$item['unique_id']] = $tesRequestData;
                            $this->updateQueueItem($item['id'], 1);
                        } else {
                            // The update didn't modify any rows - possibly a race condition
                            // Check if another process set the sample code
                            $checkQuery = "SELECT $sampleCodeColumn FROM $formTable WHERE unique_id = ?";
                            $checkData = $this->db->rawQueryOne($checkQuery, [$item['unique_id']]);

                            if (!empty($checkData) && !empty($checkData[$sampleCodeColumn])) {
                                // Another process set the sample code, that's fine
                                LoggerUtility::logInfo("Sample code for {$item['unique_id']} was set by another process: {$checkData[$sampleCodeColumn]}");
                                $this->updateQueueItem($item['id'], 1);
                            } else {
                                // Something else went wrong
                                throw new SystemException("Failed to update record with sample code for {$item['unique_id']}");
                            }
                        }
                    } catch (Throwable $e) {
                        // Mark the item as processed with error
                        $this->updateQueueItem($item['id'], 2, $e->getMessage());

                        LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getCode() . " - " . $e->getMessage(), [
                            'exception' => $e,
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'last_db_query' => $this->db->getLastQuery(),
                            'last_db_error' => $this->db->getLastError(),
                            'stacktrace' => $e->getTraceAsString()
                        ]);
                        continue;
                    }
                }
            }

            return $response;
        } finally {
            if ($parallelProcess === false && isset($lockFile) && file_exists($lockFile)) {
                // Remove the lock file when the script ends
                @unlink($lockFile);
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

            // Case 2: When result_status != RECEIVED_AT_CLINIC
            $this->db->reset();
            $this->db->where('result_status != ' . SAMPLE_STATUS\RECEIVED_AT_CLINIC);
            $this->db->where('sample_code IS NOT NULL');
            $this->db->where('sample_package_code', $manifestCode);
            $this->db->update($tableName, $buildUpdateData(false));

            return $status;
        }
    }
}

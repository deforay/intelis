<?php

namespace App\Services\STS;

use Throwable;
use RuntimeException;
use JsonMachine\Items;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;
use App\Utilities\QueryLoggerUtility;
use App\Abstracts\AbstractTestService;
use App\Exceptions\SystemException;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

final class ResultsService
{
    protected DatabaseService $db;
    protected string $testType;
    protected string $tableName;
    protected string $primaryKeyName;

    /** @var AbstractTestService $testTypeService */
    protected $testTypeService;
    protected $fieldsToRemoveForAcceptedResults = [];
    protected $unwantedColumns = [];

    public function __construct(DatabaseService $db, protected CommonService $commonService, protected UsersService $usersService, protected TestRequestsService $testRequestsService)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
    }


    private function setTestType(string $testType): void
    {
        $this->testType = $testType;
        $this->tableName = TestsService::getTestTableName($testType);
        $this->primaryKeyName = TestsService::getPrimaryColumn($testType);
        $serviceClass = TestsService::getTestServiceClass($testType);

        $this->testTypeService = ContainerRegistry::get($serviceClass);
    }

    /**
     * Upsert referral manifests into specimen_manifests.
     *
     * Rules:
     * - Only process rows with `manifest_type = 'referral'`
     * - Force/ensure `test_type = $testType`
     * - Keyed by `manifest_code` (required). If missing → skip + log.
     * - If row exists and values are unchanged (except timestamps) → skip.
     * - Uses only columns present in the local table (guards extra fields).
     *
     * @param string $testType
     * @param array<int,array<string,mixed>> $manifests
     * @return array{inserted:int,updated:int,skipped:int,errors:int}
     */
    public function receiveReferralManifests(string $testType, array $manifests): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        if ($manifests === []) {
            return $stats;
        }

        // Columns we never accept from remote for safety
        $unwantedColumns = [
            'manifest_id',            // PK
        ];

        // Allow only known columns for specimen_manifests
        $localFields = $this->commonService->getTableFieldsAsArray('specimen_manifests', $unwantedColumns);

        foreach ($manifests as $idx => $row) {
            try {
                if (empty($row) || !is_array($row)) {
                    $stats['skipped']++;
                    continue;
                }

                // We only accept referral manifests here
                if (isset($row['manifest_type']) && $row['manifest_type'] !== 'referral') {
                    $stats['skipped']++;
                    continue;
                }

                // manifest_code is required
                $manifestCode = $row['manifest_code'] ?? null;
                if (empty($manifestCode)) {
                    $stats['skipped']++;
                    LoggerUtility::logWarning("receiveReferralManifests skip: missing manifest_code at index $idx");
                    continue;
                }

                // Normalize/force important fields
                $row = MiscUtility::arrayEmptyStringsToNull($row);
                $row['manifest_type'] = 'referral';
                $row['test_type']     = $testType; // enforce consistency with the envelope

                // If remote didn't set last_modified_datetime, set now (keeps audit/logical order sane)
                $row['last_modified_datetime'] ??= DateUtility::getCurrentDateTime();

                // Keep only columns present locally
                $incoming = MiscUtility::updateMatchingKeysOnly($localFields, $row);

                // Find existing by manifest_code
                $this->db->reset();
                $this->db->where('manifest_code', $manifestCode);
                $existing = $this->db->getOne('specimen_manifests');

                // Compare ignoring commonly changing timestamps we set locally
                $compareIgnore = ['last_modified_datetime'];
                if (!empty($existing)) {
                    $same = MiscUtility::isArrayEqual($incoming, $existing, $compareIgnore);
                    if ($same) {
                        $stats['skipped']++;
                        continue;
                    }

                    // UPDATE
                    $this->db->reset();
                    $this->db->where('manifest_code', $manifestCode);
                    if ($this->db->update('specimen_manifests', $incoming) === false) {
                        throw new SystemException('Failed to update specimen_manifests for ' . $manifestCode);
                    }
                    $stats['updated']++;
                } else {
                    // INSERT
                    if ($this->db->insert('specimen_manifests', $incoming) === false) {
                        throw new SystemException('Failed to insert specimen_manifests for ' . $manifestCode);
                    }
                    $stats['inserted']++;
                }
            } catch (Throwable $e) {
                $stats['errors']++;
                LoggerUtility::logError('receiveReferralManifests error: ' . $e->getMessage(), [
                    'test_type'     => $testType,
                    'manifest_code' => $row['manifest_code'] ?? null,
                    'trace'         => $e->getTraceAsString(),
                    'last_db_error' => $this->db->getLastError(),
                    'last_db_query' => $this->db->getLastQuery(),
                ]);
            }
        }

        return $stats;
    }


    public function receiveResults($testType, $jsonResponse, $isSilent = false): array
    {
        $this->setTestType($testType);

        //remove unwanted columns
        $unwantedColumns = [
            $this->primaryKeyName,
            'sample_package_id',
            'sample_package_code',
            'request_created_by',
            'result_printed_on_sts_datetime',
            'result_printed_datetime'
        ];

        // Create an array with all column names set to null
        $localDbFieldArray = $this->commonService->getTableFieldsAsArray($this->tableName, $unwantedColumns);

        $sampleCodes = $facilityIds = [];
        $labId = null;

        if (JsonUtility::isJSON($jsonResponse)) {

            $resultData = [];
            $options = [
                'decoder' => new ExtJsonDecoder(true)
            ];
            $parsedData = Items::fromString($jsonResponse, $options);
            foreach ($parsedData as $name => $data) {
                if ($name === 'labId') {
                    $labId = $data;
                } elseif ($name === 'results') {
                    $resultData = $data;
                }
            }
            $counter = 0;
            foreach ($resultData as $key => $dataFromLIS) {
                try {
                    if (empty($dataFromLIS)) {
                        continue;
                    }
                    $this->db->beginTransaction();
                    $id = false;
                    $counter++;
                    if ($testType == "covid19" || $testType == "generic-tests" || $testType == "tb") {
                        $originalLISRecord = $dataFromLIS['form_data'] ?? [];
                    } else {
                        $originalLISRecord = $dataFromLIS ?? [];
                    }

                    if (empty($originalLISRecord)) {
                        continue;
                    }

                    // nullify empty strings
                    $originalLISRecord = MiscUtility::arrayEmptyStringsToNull($originalLISRecord);

                    // Overwrite the values in $localDbFieldArray with the values in $originalLISRecord
                    // basically we are making sure that we only use columns that are present in the $localDbFieldArray
                    // which is from local db and not using the ones in the $originalLISRecord
                    $resultFromLab = MiscUtility::updateMatchingKeysOnly($localDbFieldArray, $originalLISRecord);

                    if (isset($originalLISRecord['approved_by_name']) && !empty($originalLISRecord['approved_by_name'])) {

                        $resultFromLab['result_approved_by'] = $this->usersService->getOrCreateUser($originalLISRecord['approved_by_name']);
                        //$resultFromLab['result_approved_datetime'] ??= DateUtility::getCurrentDateTime();
                    }

                    //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
                    $resultFromLab['data_sync'] = 1;
                    $resultFromLab['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                    // if (
                    //     $resultFromLab['result_status'] != SAMPLE_STATUS\ACCEPTED &&
                    //     $resultFromLab['result_status'] != SAMPLE_STATUS\REJECTED
                    // ) {
                    //     $keysToRemove = [
                    //         'result',
                    //         'is_sample_rejected',
                    //         'reason_for_sample_rejection'
                    //     ];
                    //     $resultFromLab = MiscUtility::excludeKeys($resultFromLab, $keysToRemove);
                    // }

                    $localRecord = $this->testRequestsService->findMatchingLocalRecord($resultFromLab, $this->tableName, $this->primaryKeyName);

                    $formAttributes = JsonUtility::jsonToSetString(
                        $localRecord['form_attributes'] ?? null,
                        'form_attributes',
                        $resultFromLab['form_attributes'] ?? null
                    );
                    $resultFromLab['form_attributes'] = $formAttributes === null || $formAttributes === '' || $formAttributes === '0' ? null : $this->db->func($formAttributes);


                    // Now we update/insert the record
                    if (!empty($localRecord)) {

                        $primaryKeyValue = $localRecord[$this->primaryKeyName];
                        if (MiscUtility::isArrayEqual($resultFromLab, $localRecord, ['last_modified_datetime', 'form_attributes'])) {
                            $id = true; // treating as updated as the incoming data is same as local
                        } else {

                            if ($isSilent) {
                                unset($resultFromLab['last_modified_datetime']);
                            }
                            $this->db->where($this->primaryKeyName, $localRecord[$this->primaryKeyName]);
                            $id = $this->db->update($this->tableName, $resultFromLab);
                        }
                    } else {
                        // $id = $this->db->insert($this->tableName, $resultFromLab);
                        // $primaryKeyValue = $this->db->getInsertId();

                        for ($attempt = 0; $attempt < 3; $attempt++) {
                            try {
                                $id = $this->db->insert($this->tableName, $resultFromLab);
                                $primaryKeyValue = $this->db->getInsertId();
                                if ($id === false) {
                                    throw new RuntimeException('Insert failed after retries');
                                }
                                break;
                            } catch (Throwable $e) {
                                LoggerUtility::logWarning("Insert retry attempt $attempt due to: " . $e->getMessage());

                                if ($attempt >= 2 || !str_contains($e->getMessage(), 'Lock wait timeout')) {
                                    throw $e;
                                }
                                usleep(100000); // backoff before retry
                            }
                        }
                    }
                    // Sub-table sync for test types with additional data
                    if ($testType == "covid19") {
                        $this->commonService->syncSubTable('covid19_tests', 'covid19_id', $primaryKeyValue, $dataFromLIS['data_from_tests'] ?? null, ['test_id', 'data_sync'], [], true);
                    } elseif ($testType == "generic-tests") {
                        $this->commonService->syncSubTable('generic_test_results', 'generic_id', $primaryKeyValue, $dataFromLIS['data_from_tests'] ?? null, ['generic_test_result_id', 'data_sync'], [], true);
                    } elseif ($testType == 'tb') {
                        $this->commonService->syncSubTable('tb_tests', 'tb_id', $primaryKeyValue, $dataFromLIS['data_from_tests'] ?? null, ['tb_test_id', 'data_sync'], [], true);
                    }

                    if ($id !== false && isset($resultFromLab['sample_code'])) {
                        $sampleCodes[] = $resultFromLab['sample_code'];
                        $facilityIds[] = $resultFromLab['facility_id'];
                    }
                    $this->db->commitTransaction();
                } catch (Throwable $e) {
                    $this->db->rollbackTransaction();
                    $errorId = MiscUtility::generateErrorId();
                    LoggerUtility::logError($e->getMessage(), [
                        'error_id' => $errorId,
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                        'test_type' => $this->testType,
                        'local_unique_id' => $localRecord['unique_id'] ?? null,
                        'received_unique_id' => $dataFromLIS['unique_id'] ?? null,
                        'local_sample_code' => $localRecord['sample_code'] ?? null,
                        'received_sample_code' => $dataFromLIS['sample_code'] ?? null,
                        'local_remote_sample_code' => $localRecord['remote_sample_code'] ?? null,
                        'received_remote_sample_code' => $dataFromLIS['remote_sample_code'] ?? null,
                        'local_facility_id' => $localRecord['facility_id'] ?? null,
                        'received_facility_id' => $dataFromLIS['facility_id'] ?? null,
                        'local_lab_id' => $localRecord['lab_id'] ?? null,
                        'received_lab_id' => $dataFromLIS['lab_id'] ?? null,
                        'local_result' => $localRecord['result'] ?? null,
                        'received_result' => $dataFromLIS['result'] ?? null,
                        'synced_from_lab_id' => $labId,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    QueryLoggerUtility::log($errorId . " - " . $e->getFile() . ":" . $e->getLine() . ":" . $this->db->getLastErrno());
                    QueryLoggerUtility::log($errorId . " - " .  $this->db->getLastError());
                    QueryLoggerUtility::log($errorId . " - " . $this->db->getLastQuery());
                }
            }
        }

        return $sampleCodes;
    }
}

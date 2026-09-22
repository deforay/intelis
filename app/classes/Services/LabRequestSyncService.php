<?php

namespace App\Services;

use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;

use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;

/**
 * Saves the requests a lab pulls from the STS (tasks/remote/requests-receiver.php).
 *
 * The receiver fetches and reports; this matches each pulled request to the
 * lab's own record, updates or inserts it, and says which ones were saved and
 * which were not, for the receipt the lab sends back. Kept out of the script so
 * the saving can be tested without an STS.
 */
final class LabRequestSyncService
{
    private const string UNIDENTIFIABLE =
        'unidentifiable: no remote_sample_code, no unique_id, and no sample_code paired with a lab or facility';

    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $general,
        private readonly TestRequestsService $testRequestsService
    ) {
    }

    /**
     * Per module: the columns never taken from the STS (removeKeys), and the ones
     * taken on insert but never on update, because the lab owns them.
     *
     * @return array<string, array{removeKeys: list<string>, excludeUpdateKeys: list<string>}>
     */
    public static function moduleConfigs(): array
    {
        return [
            'vl' => [
                'removeKeys' => [
                    TestsService::getPrimaryColumn('vl'),
                    'sample_batch_id',
                    'result_value_log',
                    'result_value_absolute',
                    'result_value_absolute_decimal',
                    'result_value_text',
                    'result',
                    'sample_tested_datetime',
                    'sample_received_at_lab_datetime',
                    'result_dispatched_datetime',
                    'is_sample_rejected',
                    'reason_for_sample_rejection',
                    'result_approved_by',
                    'result_approved_datetime',
                    'request_created_datetime',
                    'last_modified_by',
                    'data_sync'
                ],
                'excludeUpdateKeys' => [
                    'sample_code',
                    'sample_code_key',
                    'sample_code_format',
                    'sample_batch_id',
                    'lab_id',
                    'vl_test_platform',
                    'sample_received_at_hub_datetime',
                    'sample_received_at_lab_datetime',
                    'sample_tested_datetime',
                    'result_dispatched_datetime',
                    'is_sample_rejected',
                    'reason_for_sample_rejection',
                    'rejection_on',
                    'result_value_absolute',
                    'result_value_absolute_decimal',
                    'result_value_text',
                    'result',
                    'result_value_log',
                    'result_value_hiv_detection',
                    'reason_for_failure',
                    'result_reviewed_by',
                    'result_reviewed_datetime',
                    'vl_focal_person',
                    'vl_focal_person_phone_number',
                    'tested_by',
                    'result_approved_by',
                    'result_approved_datetime',
                    'lab_tech_comments',
                    'reason_for_result_changes',
                    'revised_by',
                    'revised_on',
                    'last_modified_by',
                    'last_modified_datetime',
                    'manual_result_entry',
                    'result_status',
                    'data_sync',
                    'result_printed_datetime',
                    'vl_result_category'
                ],
            ],
            'eid' => [
                'removeKeys' => [
                    TestsService::getPrimaryColumn('eid'),
                    'sample_batch_id',
                    'result',
                    'sample_tested_datetime',
                    'sample_received_at_lab_datetime',
                    'result_dispatched_datetime',
                    'is_sample_rejected',
                    'reason_for_sample_rejection',
                    'result_approved_by',
                    'result_approved_datetime',
                    'data_sync'
                ],
                'excludeUpdateKeys' => [
                    'sample_code',
                    'sample_code_key',
                    'sample_code_format',
                    'sample_batch_id',
                    'sample_received_at_lab_datetime',
                    'eid_test_platform',
                    'import_machine_name',
                    'sample_tested_datetime',
                    'is_sample_rejected',
                    'lab_id',
                    'result',
                    'tested_by',
                    'lab_tech_comments',
                    'result_approved_by',
                    'result_approved_datetime',
                    'revised_by',
                    'revised_on',
                    'result_reviewed_by',
                    'result_reviewed_datetime',
                    'result_dispatched_datetime',
                    'reason_for_changing',
                    'result_status',
                    'data_sync',
                    'reason_for_sample_rejection',
                    'rejection_on',
                    'last_modified_by',
                    'result_printed_datetime',
                    'last_modified_datetime'
                ],
            ],
            'covid19' => [
                'removeKeys' => [
                    TestsService::getPrimaryColumn('covid19'),
                    'sample_batch_id',
                    'result',
                    'sample_tested_datetime',
                    'sample_received_at_lab_datetime',
                    'result_dispatched_datetime',
                    'is_sample_rejected',
                    'reason_for_sample_rejection',
                    'result_approved_by',
                    'result_approved_datetime',
                    'last_modified_by',
                    'request_created_datetime',
                    'data_sync'
                ],
                'excludeUpdateKeys' => [
                    'sample_code',
                    'sample_code_key',
                    'sample_code_format',
                    'lab_id',
                    'sample_condition',
                    'lab_technician',
                    'testing_point',
                    'is_sample_rejected',
                    'result',
                    'result_sent_to_source',
                    'other_diseases',
                    'tested_by',
                    'result_approved_by',
                    'result_approved_datetime',
                    'is_result_authorised',
                    'authorized_by',
                    'authorized_on',
                    'revised_by',
                    'revised_on',
                    'result_reviewed_by',
                    'result_reviewed_datetime',
                    'reason_for_changing',
                    'rejection_on',
                    'result_status',
                    'data_sync',
                    'reason_for_sample_rejection',
                    'last_modified_by',
                    'result_printed_datetime',
                    'result_dispatched_datetime',
                    'last_modified_datetime',
                    'data_from_comorbidities',
                    'data_from_symptoms',
                    'data_from_tests'
                ],
            ],
            'hepatitis' => [
                'removeKeys' => [
                    TestsService::getPrimaryColumn('hepatitis'),
                    'sample_batch_id',
                    'result',
                    'hcv_vl_count',
                    'hbv_vl_count',
                    'sample_tested_datetime',
                    'sample_received_at_lab_datetime',
                    'result_dispatched_datetime',
                    'is_sample_rejected',
                    'reason_for_sample_rejection',
                    'result_approved_by',
                    'result_approved_datetime',
                    'last_modified_by',
                    'request_created_datetime',
                    'data_sync'
                ],
                'excludeUpdateKeys' => [
                    'sample_code',
                    'sample_code_key',
                    'sample_code_format',
                    'sample_batch_id',
                    'lab_id',
                    'sample_condition',
                    'sample_tested_datetime',
                    'vl_testing_site',
                    'is_sample_rejected',
                    'result',
                    'hcv_vl_count',
                    'hbv_vl_count',
                    'hepatitis_test_platform',
                    'import_machine_name',
                    'is_result_authorised',
                    'result_reviewed_by',
                    'result_reviewed_datetime',
                    'authorized_by',
                    'authorized_on',
                    'revised_by',
                    'revised_on',
                    'result_status',
                    'result_sent_to_source',
                    'data_sync',
                    'last_modified_by',
                    'last_modified_datetime',
                    'result_printed_datetime',
                    'result_dispatched_datetime',
                    'reason_for_vl_test',
                    'data_from_comorbidities',
                    'data_from_risks'
                ],
            ],
            'tb' => [
                'removeKeys' => [
                    TestsService::getPrimaryColumn('tb'),
                    'sample_batch_id',
                    'result',
                    'xpert_mtb_result',
                    'sample_tested_datetime',
                    'sample_received_at_lab_datetime',
                    'result_dispatched_datetime',
                    'is_sample_rejected',
                    'reason_for_sample_rejection',
                    'result_approved_by',
                    'result_approved_datetime',
                    'last_modified_by',
                    'request_created_datetime',
                    'data_sync'
                ],
                'excludeUpdateKeys' => [
                    'sample_code',
                    'sample_code_key',
                    'sample_code_format',
                    'sample_batch_id',
                    'specimen_quality',
                    'lab_id',
                    'reason_for_tb_test',
                    'tests_requested',
                    'specimen_type',
                    'sample_collection_date',
                    'sample_received_at_lab_datetime',
                    'is_sample_rejected',
                    'result',
                    'referral_manifest_code',
                    'xpert_mtb_result',
                    'result_sent_to_source',
                    'result_dispatched_datetime',
                    'result_reviewed_by',
                    'result_reviewed_datetime',
                    'result_approved_by',
                    'result_approved_datetime',
                    'sample_tested_datetime',
                    'tested_by',
                    'rejection_on',
                    'result_status',
                    'data_sync',
                    'reason_for_sample_rejection',
                    'last_modified_by',
                    'last_modified_datetime',
                    'lab_technician',
                    'result_printed_datetime',
                    'data_from_tests'
                ],
            ],
            'cd4' => [
                'removeKeys' => [
                    TestsService::getPrimaryColumn('cd4'),
                    'sample_batch_id',
                    'cd4_result',
                    'sample_tested_datetime',
                    'sample_received_at_lab_datetime',
                    'result_dispatched_datetime',
                    'is_sample_rejected',
                    'reason_for_sample_rejection',
                    'result_approved_by',
                    'result_approved_datetime',
                    'data_sync'
                ],
                'excludeUpdateKeys' => [
                    'sample_code',
                    'sample_code_key',
                    'sample_code_format',
                    'sample_batch_id',
                    'lab_id',
                    'cd4_test_platform',
                    'sample_received_at_hub_datetime',
                    'sample_received_at_lab_datetime',
                    'sample_tested_datetime',
                    'result_dispatched_datetime',
                    'is_sample_rejected',
                    'reason_for_sample_rejection',
                    'rejection_on',
                    'cd4_result',
                    'result_reviewed_by',
                    'result_reviewed_datetime',
                    'cd4_focal_person',
                    'cd4_focal_person_phone_number',
                    'tested_by',
                    'result_approved_by',
                    'result_approved_datetime',
                    'lab_tech_comments',
                    'reason_for_result_changes',
                    'revised_by',
                    'revised_on',
                    'last_modified_by',
                    'last_modified_datetime',
                    'manual_result_entry',
                    'result_status',
                    'data_sync',
                    'result_printed_datetime',
                ],
            ],
        ];
    }

    /**
     * Save one module's pulled requests (every module but Custom Tests).
     *
     * @param iterable<mixed> $parsedData the pulled requests, as decoded rows
     * @param callable(int, int): void|null $progress called after each record with
     *        its index and the running success count
     * @return array{success: int, failures: int, inserts: int, updates: int,
     *         saved: list<string>, failed: list<array{unique_id: string, reason: ?string}>}
     */
    public function saveModule(
        string $module,
        iterable $parsedData,
        string $transactionId,
        bool $isSilent = false,
        bool $isDryRun = false,
        ?callable $progress = null
    ): array {
        $cfg = self::moduleConfigs()[$module];
        $primaryKeyName = TestsService::getPrimaryColumn($module);
        $tableName = TestsService::getTestTableName($module);

        $localDbFieldArray = $this->general->getTableFieldsAsArray($tableName, $cfg['removeKeys']);

        $loopIndex = 0;
        $successCounter = 0;
        $failureCounter = 0;
        $insertCounter = 0;
        $updateCounter = 0;
        $receiptSaved = $receiptFailed = [];

        foreach ($parsedData as $key => $remoteData) {
            // Per record: the catch below logs these, and a record that throws
            // before setting them must not be reported as the previous one.
            $request = $localRecord = null;
            try {
                $this->db->beginTransaction();

                // Only the columns the STS sent that this table has. A column the
                // STS does not have (it runs an older release) is left out rather
                // than set to NULL, which blanked the lab's value on every pull.
                $request = array_intersect_key((array) $remoteData, $localDbFieldArray);
                $syncResult = $this->syncTestRequest(
                    $request,
                    $module,
                    $tableName,
                    $primaryKeyName,
                    $cfg['excludeUpdateKeys'],
                    $transactionId,
                    $isSilent
                );
                $localRecord = $syncResult['localRecord'];

                if ($syncResult['is_failure']) {
                    $failureCounter++;
                    self::noteFailed($receiptFailed, $request, $syncResult['failure_reason'] ?? null);
                    LoggerUtility::logError("Sync operation failed", [
                        'reason' => $syncResult['failure_reason'],
                        'unique_id' => $request['unique_id'] ?? null,
                        'sample_code' => $request['sample_code'] ?? null,
                        'module' => $module,
                        'last_db_error' => $this->db->getLastError()
                    ]);
                    $this->db->rollbackTransaction();
                    continue; // Skip to next record
                }

                // Module-specific sub-table sync
                if ($module === 'covid19') {
                    $covid19Id = $localRecord[$primaryKeyName] ?? null;
                    $this->general->syncSubTable(
                        'covid19_patient_symptoms',
                        'covid19_id',
                        $covid19Id,
                        $remoteData['data_from_symptoms'] ?? null,
                        ['id']
                    );
                    $this->general->syncSubTable(
                        'covid19_patient_comorbidities',
                        'covid19_id',
                        $covid19Id,
                        $remoteData['data_from_comorbidities'] ?? null,
                        ['id']
                    );
                    $this->general->syncSubTable(
                        'covid19_tests',
                        'covid19_id',
                        $covid19Id,
                        $remoteData['data_from_tests'] ?? null,
                        ['test_id', 'data_sync'],
                        [],
                        true
                    );
                }

                if ($module === 'tb') {
                    $tbId = $localRecord[$primaryKeyName] ?? null;
                    $this->general->syncSubTable(
                        'tb_tests',
                        'tb_id',
                        $tbId,
                        $remoteData['data_from_tests'] ?? null,
                        ['tb_test_id', 'data_sync'],
                        [],
                        true
                    );
                }

                if ($module === 'hepatitis') {
                    $hepatitisId = $localRecord[$primaryKeyName] ?? null;
                    $this->general->syncSubTable(
                        'hepatitis_risk_factors',
                        'hepatitis_id',
                        $hepatitisId,
                        $remoteData['data_from_risks'] ?? null,
                        ['id'],
                        ['keyField' => 'riskfactors_id', 'valueField' => 'riskfactors_detected']
                    );
                    $this->general->syncSubTable(
                        'hepatitis_patient_comorbidities',
                        'hepatitis_id',
                        $hepatitisId,
                        $remoteData['data_from_comorbidities'] ?? null,
                        ['id'],
                        ['keyField' => 'comorbidity_id', 'valueField' => 'comorbidity_detected']
                    );
                }

                if ($syncResult['success']) {
                    $successCounter++;
                    if ($syncResult['is_insert']) {
                        $insertCounter++;
                    } else {
                        $updateCounter++;
                    }
                }
                if ($isDryRun) {
                    $this->db->rollbackTransaction();
                } else {
                    $this->db->commitTransaction();
                    // Saved, or already up to date: either way the lab has it.
                    self::noteSaved($receiptSaved, $request);
                }
            } catch (Throwable $e) {
                $this->db->rollbackTransaction();
                // A record that threw was not saved: count it, or the run reports
                // no failures and an all-failed pull is recorded as an empty one.
                $failureCounter++;
                self::noteFailed($receiptFailed, $request, $e->getMessage());
                LoggerUtility::logError($e->getMessage(), [
                    'error_id' => MiscUtility::generateErrorId(),
                    'exception_class' => $e::class,
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_query' => $this->db->getLastQuery(),
                    'last_db_error' => $this->db->getLastError(),
                    'local_unique_id' => $localRecord['unique_id'] ?? null,
                    'received_unique_id' => $request['unique_id'] ?? null,
                    'local_sample_code' => $localRecord['sample_code'] ?? null,
                    'received_sample_code' => $request['sample_code'] ?? null,
                    'local_remote_sample_code' => $localRecord['remote_sample_code'] ?? null,
                    'received_remote_sample_code' => $request['remote_sample_code'] ?? null,
                    'local_facility_id' => $localRecord['facility_id'] ?? null,
                    'received_facility_id' => $request['facility_id'] ?? null,
                    'local_lab_id' => $localRecord['lab_id'] ?? null,
                    'received_lab_id' => $request['lab_id'] ?? null,
                    'local_result' => $localRecord['result'] ?? null,
                    'received_result' => $request['result'] ?? null,
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }

            if ($progress !== null) {
                $progress($loopIndex, $successCounter);
            }
            $loopIndex++;
        }

        return [
            'success' => $successCounter,
            'failures' => $failureCounter,
            'inserts' => $insertCounter,
            'updates' => $updateCounter,
            'saved' => $receiptSaved,
            'failed' => $receiptFailed,
        ];
    }

    /**
     * Save pulled Custom Tests requests, which merge test_type_form and keep their
     * own column lists.
     *
     * @param iterable<mixed> $parsedData
     * @param callable(int, int): void|null $progress
     * @return array{success: int, failures: int, saved: list<string>,
     *         failed: list<array{unique_id: string, reason: ?string}>}
     */
    public function saveCustomTests(
        iterable $parsedData,
        string $transactionId,
        bool $isSilent = false,
        bool $isDryRun = false,
        ?callable $progress = null
    ): array {
        $primaryKeyName = TestsService::getPrimaryColumn('generic-tests');
        $tableName = TestsService::getTestTableName('generic-tests');

        $removeKeys = [
            $primaryKeyName,
            'sample_batch_id',
            'result',
            'sample_tested_datetime',
            'sample_received_at_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'data_sync'
        ];
        $localDbFieldArray = $this->general->getTableFieldsAsArray($tableName, $removeKeys);

        $loopIndex = 0;
        $successCounter = 0;
        $failureCounter = 0;
        $receiptSaved = $receiptFailed = [];

        foreach ($parsedData as $key => $remoteData) {
            $request = $localRecord = null;
            try {
                $this->db->beginTransaction();

                // Only the columns the STS sent that this table has. A column the
                // STS does not have (it runs an older release) is left out rather
                // than set to NULL, which blanked the lab's value on every pull.
                $request = array_intersect_key((array) $remoteData, $localDbFieldArray);
                $localRecord = $this->testRequestsService->findMatchingLocalRecord(
                    $request,
                    $tableName,
                    $primaryKeyName
                );

                if (!empty($localRecord)) {
                    $removeKeysForUpdate = [
                        'sample_code',
                        'sample_code_key',
                        'sample_code_format',
                        'sample_batch_id',
                        'lab_id',
                        'vl_test_platform',
                        'sample_received_at_hub_datetime',
                        'sample_received_at_lab_datetime',
                        'sample_tested_datetime',
                        'result_dispatched_datetime',
                        'is_sample_rejected',
                        'reason_for_sample_rejection',
                        'rejection_on',
                        'result',
                        'result_reviewed_by',
                        'result_reviewed_datetime',
                        'tested_by',
                        'result_approved_by',
                        'result_approved_datetime',
                        'lab_tech_comments',
                        'reason_for_test_result_changes',
                        'revised_by',
                        'revised_on',
                        'last_modified_by',
                        'last_modified_datetime',
                        'manual_result_entry',
                        'result_status',
                        'data_sync',
                        'result_printed_datetime',
                        'data_from_tests'
                    ];
                    // Merge test_type_form and form_attributes like original
                    $testTypeForm = JsonUtility::jsonToSetString(
                        $localRecord['test_type_form'] ?? null,
                        'test_type_form',
                        $request['test_type_form'] ?? null
                    );
                    $request['test_type_form'] = $this->jsonSetOrNull($testTypeForm);
                    $formAttributes = JsonUtility::jsonToSetString(
                        $localRecord['form_attributes'] ?? null,
                        'form_attributes',
                        $request['form_attributes'] ?? null
                    );
                    $request['form_attributes'] = $this->jsonSetOrNull($formAttributes);
                    $request['is_result_mail_sent'] ??= 'no';
                    $updatePayload = MiscUtility::excludeKeys($request, $removeKeysForUpdate);
                    $updatePayload = self::preserveLocallyOwnedFields($updatePayload, $localRecord);
                    $updatePayload = self::keepRequestSource($updatePayload);
                    // Conditional backfill of remote_sample_code
                    if (!empty($request['remote_sample_code']) && empty($localRecord['remote_sample_code'])) {
                        $this->db->rawQuery(
                            "UPDATE {$tableName} SET remote_sample_code = ? WHERE {$primaryKeyName} = ?"
                            . " AND (remote_sample_code IS NULL OR remote_sample_code = '')",
                            [$request['remote_sample_code'], $localRecord[$primaryKeyName]]
                        );
                        $localRecord['remote_sample_code'] = $request['remote_sample_code'];
                    }
                    $needsUpdate = !MiscUtility::isArrayEqual(
                        $updatePayload,
                        $localRecord,
                        ['last_modified_datetime', 'form_attributes']
                    );
                    if ($needsUpdate) {
                        $updatePayload['last_modified_datetime'] = DateUtility::getCurrentDateTime();
                        if ($isSilent) {
                            unset($updatePayload['last_modified_datetime']);
                        }
                        $this->db->where($primaryKeyName, $localRecord[$primaryKeyName]);
                        $id = $this->db->update($tableName, $updatePayload);
                    } else {
                        $id = true;
                    }
                    $genericId = $localRecord[$primaryKeyName];
                } elseif (!TestRequestsService::hasUsableIdentity($request)) {
                    // See syncTestRequest(): a record with no key to be found
                    // by would be re-inserted on every sync.
                    LoggerUtility::logError("Sync operation failed", [
                        'reason' => self::UNIDENTIFIABLE,
                        'unique_id' => $request['unique_id'] ?? null,
                        'sample_code' => $request['sample_code'] ?? null,
                        'module' => 'generic-tests',
                    ]);
                    $id = false;
                    $genericId = null;
                } elseif (!empty($request['sample_collection_date'])) {
                    // Insert path
                    $request['source_of_request'] = 'vlsts';
                    $testTypeForm = JsonUtility::jsonToSetString(
                        $request['test_type_form'] ?? null,
                        'test_type_form'
                    );
                    $request['test_type_form'] = $this->jsonSetOrNull($testTypeForm);
                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'] ?? null,
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = $this->jsonSetOrNull($formAttributes);
                    $request['is_result_mail_sent'] ??= 'no';
                    $request['data_sync'] = 0;
                    $id = $this->db->insert($tableName, $request);
                    $genericId = $this->db->getInsertId();
                } else {
                    // New to this lab but no collection date: it cannot be
                    // inserted. This used to be skipped without a word.
                    LoggerUtility::logError("Sync operation failed", [
                        'reason' => 'new request has no sample_collection_date',
                        'unique_id' => $request['unique_id'] ?? null,
                        'sample_code' => $request['sample_code'] ?? null,
                        'module' => 'generic-tests',
                    ]);
                    $id = false;
                    $genericId = null;
                }

                $this->general->syncSubTable(
                    'generic_test_results',
                    'generic_id',
                    $genericId,
                    $remoteData['data_from_tests'] ?? null,
                    ['test_id', 'data_sync'],
                    [],
                    true
                );

                if ($id === true || $id > 0) {
                    $successCounter++;
                } else {
                    $failureCounter++;
                }
                if ($isDryRun) {
                    $this->db->rollbackTransaction();
                } elseif ($id === true || $id > 0) {
                    $this->db->commitTransaction();
                    self::noteSaved($receiptSaved, $request);
                } else {
                    $this->db->commitTransaction();
                    self::noteFailed($receiptFailed, $request, 'not saved on the lab');
                }
            } catch (Throwable $e) {
                $this->db->rollbackTransaction();
                // A record that threw was not saved: count it, or the run reports
                // no failures and an all-failed pull is recorded as an empty one.
                $failureCounter++;
                self::noteFailed($receiptFailed, $request, $e->getMessage());
                LoggerUtility::logError($e->getMessage(), [
                    'error_id' => MiscUtility::generateErrorId(),
                    'exception_class' => $e::class,
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_query' => $this->db->getLastQuery(),
                    'last_db_error' => $this->db->getLastError(),
                    'local_unique_id' => $localRecord['unique_id'] ?? null,
                    'received_unique_id' => $request['unique_id'] ?? null,
                    'local_sample_code' => $localRecord['sample_code'] ?? null,
                    'received_sample_code' => $request['sample_code'] ?? null,
                    'local_remote_sample_code' => $localRecord['remote_sample_code'] ?? null,
                    'received_remote_sample_code' => $request['remote_sample_code'] ?? null,
                    'local_facility_id' => $localRecord['facility_id'] ?? null,
                    'received_facility_id' => $request['facility_id'] ?? null,
                    'local_lab_id' => $localRecord['lab_id'] ?? null,
                    'received_lab_id' => $request['lab_id'] ?? null,
                    'local_result' => $localRecord['result'] ?? null,
                    'received_result' => $request['result'] ?? null,
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }

            if ($progress !== null) {
                $progress($loopIndex, $successCounter);
            }
            $loopIndex++;
        }

        return [
            'success' => $successCounter,
            'failures' => $failureCounter,
            'saved' => $receiptSaved,
            'failed' => $receiptFailed,
        ];
    }

    /**
     * Codes the testing lab assigns on this instance. STS only ever holds a copy of
     * them, and only after the lab pushed the row up with its results, so STS is
     * never the authority for these columns -- the local value always wins.
     *
     * Without this, a request sync silently wiped them: getTableFieldsAsArray()
     * seeds every column as null, STS sends its own (still empty) copy, and neither
     * column is in removeKeys/excludeUpdateKeys, so the update wrote NULL over the
     * code the lab had just entered. The window is wide open in practice, because a
     * lab assigns its code right after activating a manifest -- long before any
     * result is pushed up. Manually registered samples never come back down from
     * STS, which is why only the synced ones lost their code.
     *
     * Backfill still works: when the column is empty locally, an incoming value is
     * applied as usual.
     */
    public static function preserveLocallyOwnedFields(array $updatePayload, array $localRecord): array
    {
        $locallyOwnedKeys = ['lab_assigned_code', 'cv_number'];

        foreach ($locallyOwnedKeys as $key) {
            if (!array_key_exists($key, $updatePayload)) {
                continue;
            }
            if (trim((string) ($localRecord[$key] ?? '')) !== '') {
                unset($updatePayload[$key]);
            }
        }

        return $updatePayload;
    }

    /**
     * The modules to pull again straight away, in the same run.
     *
     * Only while the STS says requests are still waiting for this lab, the number
     * is going down (a remainder that stays put, such as requests with no unique_id
     * that cannot be confirmed, would loop until the time runs out), the receipt got
     * through (else the STS sends the same batch again), and the lab saved at least
     * one request of the last batch: when every one failed, something is wrong on
     * the lab, and pulling on would only mark the rest of the backlog failed too.
     *
     * @param array<string, bool> $receiptDelivered by module, for those that sent a receipt
     * @param array<string, array<string, string>> $responseHeaders by module, lower-case names
     * @param array<string, int> $previousRemaining by module; updated for the next pass
     * @param array<string, int> $savedCounts by module, requests saved in the last batch
     * @return list<string>
     */
    public static function modulesToPullAgain(
        array $receiptDelivered,
        array $responseHeaders,
        array &$previousRemaining,
        array $savedCounts
    ): array {
        $modules = [];
        foreach ($receiptDelivered as $module => $delivered) {
            $remaining = (int) ($responseHeaders[$module]['x-pending-remaining'] ?? 0);
            $progressing = $remaining < ($previousRemaining[$module] ?? PHP_INT_MAX);
            $previousRemaining[$module] = $remaining;
            if ($delivered && $remaining > 0 && $progressing && ($savedCounts[$module] ?? 0) > 0) {
                $modules[] = $module;
            }
        }
        return $modules;
    }

    /** A JSON_SET() expression from JsonUtility::jsonToSetString(), or NULL when it built none. */
    private function jsonSetOrNull(?string $expression): mixed
    {
        return $expression === null || $expression === '' || $expression === '0'
            ? null
            : $this->db->func($expression);
    }

    /**
     * The lab records a request with no source as coming from the STS ('vlsts')
     * when it adds it. The STS's own copy still has none, and sending that on
     * every later pull blanked the lab's value and counted as a change.
     */
    public static function keepRequestSource(array $updatePayload): array
    {
        if (
            array_key_exists('source_of_request', $updatePayload)
            && trim((string) $updatePayload['source_of_request']) === ''
        ) {
            unset($updatePayload['source_of_request']);
        }
        return $updatePayload;
    }

    /**
     * Helper to sync a single test request: find matching local record, optionally backfill remote_sample_code,
     * compare meaningful fields, and update or insert.
     *
     * @return array ['success' => bool, 'is_insert' => bool, 'localRecord' => array]
     */
    private function syncTestRequest(
        array $incoming,
        string $testType,
        string $tableName,
        string $primaryKeyName,
        array $excludeKeysForUpdate,
        string $transactionId,
        bool $isSilent
    ): array {
        $localRecord = $this->testRequestsService->findMatchingLocalRecord($incoming, $tableName, $primaryKeyName);
        $didInsert = false;
        $didUpdate = false;
        $didFail = false;
        $failureReason = null;
        $resultRecord = $localRecord;
        if ($localRecord !== []) {
            // Build the patchable payload
            $updatePayload = MiscUtility::excludeKeys($incoming, $excludeKeysForUpdate);

            // Prepare form_attributes
            $formAttributes = JsonUtility::jsonToSetString(
                $incoming['form_attributes'] ?? null,
                'form_attributes',
                ['syncTransactionId' => $transactionId]
            );
            $updatePayload['form_attributes'] = $this->jsonSetOrNull($formAttributes);
            $updatePayload['is_result_mail_sent'] ??= 'no';
            $updatePayload = self::preserveLocallyOwnedFields($updatePayload, $localRecord);
            $updatePayload = self::keepRequestSource($updatePayload);

            // Conditional backfill of remote_sample_code
            if (!empty($incoming['remote_sample_code']) && empty($localRecord['remote_sample_code'])) {
                $this->db->rawQuery(
                    "UPDATE {$tableName} SET remote_sample_code = ? WHERE {$primaryKeyName} = ?"
                    . " AND (remote_sample_code IS NULL OR remote_sample_code = '')",
                    [$incoming['remote_sample_code'], $localRecord[$primaryKeyName]]
                );
                $localRecord['remote_sample_code'] = $incoming['remote_sample_code'];
            }

            // Determine if meaningful change exists
            $needsUpdate = !MiscUtility::isArrayEqual(
                $updatePayload,
                $localRecord,
                ['last_modified_datetime', 'form_attributes']
            );

            if ($needsUpdate) {
                $updatePayload['last_modified_datetime'] = DateUtility::getCurrentDateTime();
                if ($isSilent) {
                    unset($updatePayload['last_modified_datetime']);
                }
                // result_status is stripped from every incoming update, so the STS cannot
                // move a sample the lab has already decided about. This is the one branch
                // that puts it back: a TB sample referred to this lab arrives as received.
                //
                // Not when the lab has cancelled it. A cancellation is the lab saying the
                // sample will not be tested, and a later referral does not undo that -- it
                // would reopen a sample nobody is going to test, which is the kind of thing
                // a lab finds months later in a count it cannot explain.
                if ($testType == 'tb' && isset($updatePayload['referred_to_lab_id'])) {
                    $alreadyCancelled = (int) ($localRecord['result_status'] ?? 0) === CANCELLED;
                    if (
                        !$alreadyCancelled
                        && ($updatePayload['lab_id'] == $updatePayload['referred_to_lab_id'])
                        && ($updatePayload['referred_to_lab_id'] != $updatePayload['referred_by_lab_id'])
                    ) {
                        $updatePayload['result_status'] = RECEIVED_AT_TESTING_LAB;
                    }
                }
                $this->db->where($primaryKeyName, $localRecord[$primaryKeyName]);
                $res = $this->db->update($tableName, $updatePayload);

                if ($res === true) {
                    $didUpdate = true;
                    $resultRecord = array_merge($localRecord, $updatePayload);
                } else {
                    $didFail = true;
                    $failureReason = 'update_failed: ' . ($this->db->getLastError() ?: 'unknown error');
                }
            } else {
                $resultRecord = $localRecord;
            }
        } elseif (!TestRequestsService::hasUsableIdentity($incoming)) {
            // Not a match failure -- a record with no remote_sample_code, no
            // unique_id, and no sample_code paired with a lab or facility cannot be
            // found again, so inserting it would mean inserting it once more on
            // every sync from here on. It is reported through the same channel as
            // any other sync failure and left on the sending system.
            $didFail = true;
            $failureReason = self::UNIDENTIFIABLE;
        } else {
            // Insert path
            $incoming['source_of_request'] ??= 'vlsts';
            $formAttributes = JsonUtility::jsonToSetString(
                $incoming['form_attributes'] ?? null,
                'form_attributes',
                ['syncTransactionId' => $transactionId]
            );
            $incoming['form_attributes'] = $this->jsonSetOrNull($formAttributes);
            $incoming['is_result_mail_sent'] ??= 'no';
            $incoming['data_sync'] = 0;
            // Only TB and Custom Tests have the referral columns.
            $referredTo = $incoming['referred_to_lab_id'] ?? null;
            if (
                ($incoming['lab_id'] ?? null) == $referredTo
                && $referredTo != ($incoming['referred_by_lab_id'] ?? null)
            ) {
                $incoming['result_status'] = RECEIVED_AT_TESTING_LAB;
            }
            $res = $this->db->insert($tableName, $incoming);
            if ($res === true || $res > 0) {
                $didInsert = true;
                $insertId = $this->db->getInsertId();
                $resultRecord = [$primaryKeyName => $insertId] + $incoming;
            } else {
                $didFail = true;
                $failureReason = 'insert_failed: ' . ($this->db->getLastError() ?: 'unknown error');
            }
        }

        return [
            'success' => $didInsert || $didUpdate,
            'is_insert' => $didInsert,
            'is_update' => $didUpdate,
            'is_failure' => $didFail,
            'failure_reason' => $failureReason,
            'localRecord' => $resultRecord,
        ];
    }

    /**
     * The pulled request's unique_id, which a receipt names it by. A request without
     * one cannot be confirmed; the STS keeps it in flight and sends it again.
     *
     * @param array<string, mixed>|null $request
     */
    public static function receiptId(?array $request): ?string
    {
        $value = is_array($request) ? ($request['unique_id'] ?? null) : null;
        $uniqueId = is_scalar($value) ? trim((string) $value) : '';
        return $uniqueId === '' ? null : $uniqueId;
    }

    /** @param list<string> $saved */
    private static function noteSaved(array &$saved, ?array $request): void
    {
        $id = self::receiptId($request);
        if ($id !== null) {
            $saved[] = $id;
        }
    }

    /** @param list<array{unique_id: string, reason: ?string}> $failed */
    private static function noteFailed(array &$failed, ?array $request, ?string $reason): void
    {
        $id = self::receiptId($request);
        if ($id !== null) {
            $failed[] = ['unique_id' => $id, 'reason' => $reason];
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Utilities\MiscUtility;
use RuntimeException;
use Throwable;

use const COUNTRY\CAMEROON;
use const COUNTRY\RWANDA;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\TEST_FAILED;
use const SAMPLE_STATUS\PENDING_APPROVAL;

/**
 * Turns one row from the Interface Tool `orders` table into a result update.
 *
 * bin/interface.php reads those rows straight out of the Interface Tool database and
 * the Interface API will receive the same rows over HTTP, so everything that decides
 * what a row means lives here and neither caller repeats it.
 *
 * Callers keep what they alone care about: fetching rows, transactions, and writing
 * lims_sync_status back to wherever the row came from.
 */
final class InterfacingService
{
    private const FAILURE_RESULTS = ['fail', 'failed', 'failure', 'error', 'err'];

    /** @var array<string, string>|null primary key column => test table name */
    private ?array $activeModules = null;
    private ?int $formId = null;
    private ?bool $autoApprove = null;
    private ?TestAttemptService $attempts = null;

    /**
     * Resolved lazily rather than constructor-injected, so the existing call sites that
     * build this service by hand keep working unchanged.
     */
    private function attempts(): TestAttemptService
    {
        return $this->attempts ??= new TestAttemptService($this->db->connection('default'));
    }

    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $commonService,
        private readonly UsersService $usersService,
        private readonly VlService $vlService
    ) {
    }

    /**
     * Analyzers often report the same run twice, once in copies/ml and once as a log
     * value. Where both exist for an order the copies row wins and the log row is dropped.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function filterDuplicateUnits(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[($row['order_id'] ?? '') . '::' . ($row['test_id'] ?? '')][] = $row;
        }

        $filtered = [];
        foreach ($grouped as $group) {
            $hasCopies = false;
            foreach ($group as $row) {
                $unit = $this->unit($row);
                if (str_contains($unit, 'log')) {
                    continue;
                }
                foreach ($this->vlService->copiesPatterns as $pattern) {
                    if (str_contains($unit, $pattern)) {
                        $hasCopies = true;
                        break 2;
                    }
                }
            }

            foreach ($group as $row) {
                if ($hasCopies && str_contains($this->unit($row), 'log')) {
                    continue;
                }
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    /**
     * Imports a single `orders` row.
     *
     * `synced` mirrors lims_sync_status: true means the row was dealt with (1), false
     * means it could not be applied and should be retried or reviewed (2). `updated`
     * is false when the sample already held these values, which is a no-op rather
     * than a second import, so callers do not count it as an imported result.
     *
     * `$explainMisses` looks a missed sample up a second time to tell a locked sample
     * apart from an unknown one. That second pass runs against every active test
     * table, so callers that treat both reasons the same should turn it off.
     *
     * @param array<string, mixed> $row
     * @return array{synced: bool, updated: bool, table: ?string, reason: string}
     */
    public function importResult(
        array $row,
        int $labId,
        bool $includeLocked = false,
        bool $updateModifiedTime = true,
        bool $scopeToLab = false,
        bool $explainMisses = true
    ): array {
        $orderId = trim((string) ($row['order_id'] ?? ''));
        $testId = trim((string) ($row['test_id'] ?? ''));

        if ($orderId === '' && $testId === '') {
            return $this->outcome(false, false, null, 'no_order_or_test_id');
        }

        $scopedLabId = $scopeToLab ? $labId : null;

        // A TB result is only ever looked for among TB samples. The codes a GeneXpert
        // reports are often a lab's own register numbers, which a VL or EID sample can
        // carry too.
        if ($this->isTbTest($row)) {
            return $this->importTbResult(
                $row,
                $orderId,
                $testId,
                $labId,
                $includeLocked,
                $updateModifiedTime,
                $scopedLabId
            );
        }

        $sample = $this->findSample($orderId, $testId, $includeLocked, $scopedLabId);
        if ($sample === null) {
            $reason = $explainMisses
                ? $this->explainMiss($orderId, $testId, $includeLocked, $scopedLabId)
                : 'no_matching_sample';
            return $this->outcome(false, false, null, $reason);
        }

        $table = $sample['table'];
        $existing = $sample['row'];
        $instrument = $this->findInstrument($row['instrument_id'] ?? $row['machine_used'] ?? null);

        $data = match ($table) {
            'form_vl' => $this->buildVlData($row, $existing, $instrument, $labId),
            'form_eid' => $this->buildEidData($row, $instrument, $labId),
            'form_hepatitis' => $this->buildHepatitisData($row, $existing, $instrument, $labId),
            default => null,
        };

        if ($data === null) {
            // form_covid19 has no mapping yet, and hepatitis rows whose test type is
            // neither HBV nor HCV have nowhere to put the result.
            return $this->outcome(false, false, $table, 'unsupported_test_type');
        }

        // A result from the analyzer means the sample was tested, so it is no longer
        // rejected. The builders above already move result_status off Rejected; without
        // this the rejection flag and reason stay behind from an earlier rejection and
        // the row reads as rejected and resulted at once -- which is how the rejection
        // reports came to count samples that carry a viral load. Every manual path
        // (the result page, the bulk status grid, the file importer) already clears
        // both columns when a result is recorded.
        if (trim((string) ($data['result'] ?? '')) !== '') {
            $data['is_sample_rejected'] = 'no';
            $data['reason_for_sample_rejection'] = null;
        }

        if (!$updateModifiedTime) {
            unset($data['last_modified_datetime']);
        }

        // Timestamps always differ, so comparing them would make every row look changed.
        $ignoredKeys = ['last_modified_datetime', 'result_printed_datetime'];
        if (MiscUtility::isArrayEqual($data, $existing, $ignoredKeys)) {
            return $this->outcome(true, false, $table, 'already_up_to_date');
        }

        // Retain the outgoing result before the instrument write replaces it. findSample()
        // guards only on `locked`, never on whether a result is already present, so an
        // instrument re-sending a corrected result silently overwrites the earlier one --
        // including a failure. Nothing is written when there is no prior result to keep.
        $testType = TestsService::getTestTypeByTable($table);
        if ($testType !== null) {
            $this->attempts()->archive(
                $testType,
                (int) $existing[$sample['primaryKey']],
                TestAttemptService::BY_INTERFACE
            );
        }

        $this->db->connection('default')->where($sample['primaryKey'], $existing[$sample['primaryKey']]);
        $updated = $this->db->connection('default')->update($table, $data) === true;

        return $this->outcome($updated, $updated, $table, $updated ? 'updated' : 'update_failed');
    }

    /**
     * Imports a batch of rows submitted over the API and reports each one back.
     *
     * The lab is always taken from the caller's credential, never from the payload,
     * and the match is scoped to that lab. Each row commits on its own so one bad
     * row cannot roll back the rest of the run.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return list<array{id: mixed, outcome: string, limsSyncStatus: int, reason: string}>
     */
    public function importBatch(array $rows, int $labId): array
    {
        // The copies-versus-log rule can only be applied across a whole run, which is
        // why clients are asked to submit a run in one request.
        $kept = $this->filterDuplicateUnits($rows);
        $keptKeys = [];
        foreach ($kept as $row) {
            $keptKeys[$this->rowKey($row)] = true;
        }

        $report = [];
        foreach ($rows as $row) {
            $id = $row['id'] ?? null;

            if (!isset($keptKeys[$this->rowKey($row)])) {
                $report[] = $this->reportRow($id, 'duplicate_unit_discarded');
                continue;
            }

            try {
                $this->db->connection('default')->beginTransaction();
                $outcome = $this->importResult($row, $labId, scopeToLab: true);
                $this->db->connection('default')->commitTransaction();
            } catch (Throwable $e) {
                $this->db->connection('default')->rollbackTransaction();
                LoggerUtility::logError('Interface result import failed: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $report[] = $this->reportRow($id, 'update_failed');
                continue;
            }

            $report[] = $this->reportRow($id, $outcome['reason']);
        }

        return $report;
    }

    /**
     * Maps an internal reason onto what the client should do with its own row.
     *
     * @return array{id: mixed, outcome: string, limsSyncStatus: int, reason: string}
     */
    private function reportRow(mixed $id, string $reason): array
    {
        // limsSyncStatus is sent explicitly so a client never has to infer it:
        // 1 dealt with, 2 will not apply, 0 leave for the next run.
        [$outcome, $syncStatus] = match ($reason) {
            'updated' => ['accepted', 1],
            'already_up_to_date', 'newer_result_on_record' => ['unchanged', 1],
            'update_failed' => ['retry', 0],
            default => ['rejected', 2],
        };

        return [
            'id' => $id,
            'outcome' => $outcome,
            'limsSyncStatus' => $syncStatus,
            'reason' => $reason,
        ];
    }

    /**
     * Says why a lookup found nothing. Only runs once a row has already failed to
     * match, so the extra queries cost nothing on the happy path.
     */
    private function explainMiss(string $orderId, string $testId, bool $includeLocked, ?int $scopedLabId): string
    {
        // Safe to report: the lookup is still lab scoped, so this can only ever
        // describe a sample the caller is entitled to see.
        if (!$includeLocked && $this->findSample($orderId, $testId, true, $scopedLabId) !== null) {
            return 'sample_locked';
        }

        // A sample that exists outside the caller's lab is recorded here and not in
        // the response. Telling the caller would turn the endpoint into an oracle for
        // whether a sample code exists anywhere in the fleet, which is enough to
        // enumerate another lab's workload one guess at a time.
        if ($scopedLabId !== null && $this->findSample($orderId, $testId, true, null) !== null) {
            LoggerUtility::logWarning('Interface result rejected: sample belongs to another lab', [
                'submittingLabId' => $scopedLabId,
                'orderId' => $orderId,
                'testId' => $testId,
            ]);
        }

        return 'no_matching_sample';
    }

    /** @param array<string, mixed> $row */
    private function rowKey(array $row): string
    {
        return (string) ($row['id'] ?? '') . '|' . ($row['order_id'] ?? '') . '|' . ($row['test_id'] ?? '');
    }

    // -----------------------------------------------------------------
    // Lookups
    // -----------------------------------------------------------------

    /**
     * Searches every active test table for the sample this result belongs to.
     *
     * With `$onlyTable` the search is confined to that table and a code that more than
     * one sample carries matches none of them: the result comes back with `ambiguous`
     * set and no row.
     *
     * @return array{table: string, primaryKey: string, row: array<string, mixed>|null, ambiguous?: bool}|null
     */
    private function findSample(
        string $orderId,
        string $testId,
        bool $includeLocked,
        ?int $restrictToLabId = null,
        ?string $onlyTable = null
    ): ?array {
        // NOTE: the pairing is carried over from bin/interface.php so that moving this
        // lookup did not change which samples match: sample_code gets order_id,
        // remote_sample_code gets both, lab_assigned_code gets test_id.
        //
        // Empty values are left out. Binding '' matched any sample whose code was blank,
        // and a column with no value to look for is dropped from the OR entirely.
        $codesByColumn = [
            'sample_code' => [$orderId],
            'remote_sample_code' => [$orderId, $testId],
            'lab_assigned_code' => [$testId],
        ];
        $codeMatches = [];
        foreach ($codesByColumn as $column => $values) {
            $values = array_filter(array_map('trim', $values), static fn(string $v): bool => $v !== '');
            $values = array_values(array_unique($values));
            if ($values !== []) {
                $codeMatches[$column] = $values;
            }
        }

        if ($codeMatches === []) {
            return null;
        }

        $codeConditions = [];
        $codeParams = [];
        foreach ($codeMatches as $column => $values) {
            $codeConditions[] = "$column IN (" . implode(', ', array_fill(0, count($values), '?')) . ')';
            $codeParams = [...$codeParams, ...$values];
        }

        foreach ($this->activeModules() as $primaryKey => $table) {
            if ($onlyTable !== null && $table !== $onlyTable) {
                continue;
            }

            $conditions = [];
            $params = $codeParams;

            if (!$includeLocked) {
                $conditions[] = "IFNULL(locked, 'no') = 'no'";
            }

            $conditions[] = '(' . implode(' OR ', $codeConditions) . ')';

            // Callers that cannot be trusted to only send their own samples -- anything
            // arriving over the API -- match strictly on the lab the credential belongs
            // to. A sample not yet assigned to any lab is not claimable this way: the
            // lookup matches on sample codes alone, so anything looser would let an
            // installation reach a sample by guessing a code.
            if ($restrictToLabId !== null) {
                $conditions[] = 'lab_id = ?';
                $params[] = $restrictToLabId;
            }

            $conditions = implode(' AND ', $conditions);

            if ($onlyTable !== null) {
                $matches = $this->db->connection('default')->rawQuery(
                    "SELECT * FROM $table WHERE $conditions LIMIT 2",
                    $params
                ) ?: [];
                if (count($matches) > 1) {
                    return ['table' => $table, 'primaryKey' => $primaryKey, 'row' => null, 'ambiguous' => true];
                }
                return $matches === [] ? null : ['table' => $table, 'primaryKey' => $primaryKey, 'row' => $matches[0]];
            }

            $existing = $this->db->connection('default')->rawQueryOne(
                "SELECT * FROM $table WHERE $conditions",
                $params
            );

            if (!empty($existing)) {
                return ['table' => $table, 'primaryKey' => $primaryKey, 'row' => $existing];
            }
        }

        return null;
    }

    /**
     * Resolves the instrument from the machine name the analyzer reported, falling
     * back to the configured machine aliases. Carries the approver and reviewer
     * defaults plus the detection limits used to interpret the result.
     *
     * @return array<string, mixed>|null
     */
    private function findInstrument(mixed $machineName): ?array
    {
        $instrument = $this->db->connection('default')->rawQueryOne(
            'SELECT * FROM instruments WHERE instruments.machine_name = ?',
            [$machineName]
        );

        if (empty($instrument)) {
            $instrument = $this->db->connection('default')->rawQueryOne(
                'SELECT * FROM instruments
                    INNER JOIN instrument_machines ON instruments.instrument_id = instrument_machines.instrument_id
                    WHERE instrument_machines.config_machine_name = ?',
                [$machineName]
            );
        }

        return empty($instrument) ? null : $instrument;
    }

    // -----------------------------------------------------------------
    // Per module mapping
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $existing
     * @param array<string, mixed>|null $instrument
     * @return array<string, mixed>
     */
    private function buildVlData(array $row, array $existing, ?array $instrument, int $labId): array
    {
        $lowerLimit = $instrument['lower_limit'] ?? null;
        $result = null;
        $logVal = null;
        $absVal = null;
        $absDecimalVal = null;
        $txtVal = null;
        $resultStatus = null;

        if (!empty($row['results'])) {
            $result = trim(str_ireplace(['cp/ml', 'copies/ml'], '', (string) $row['results']));

            if ($result == '-1.00' || $result == 'BT') {
                $result = 'Target Not Detected';
            } elseif (strtolower($result) == 'detected' && !empty($lowerLimit)) {
                $result = "< $lowerLimit";
            }

            if ($result !== '' && $result !== '0' && !in_array(strtolower($result), self::FAILURE_RESULTS)) {
                $interpreted = $this->vlService->interpretViralLoadResult(
                    $result,
                    trim((string) $row['test_unit']),
                    $instrument['low_vl_result_text'] ?? null
                );

                if (!empty($interpreted)) {
                    $logVal = $interpreted['logVal'];
                    $result = $interpreted['result'] ?? $interpreted['txtVal'];
                    $absDecimalVal = $interpreted['absDecimalVal'];
                    $absVal = $interpreted['absVal'];
                    $txtVal = $interpreted['txtVal'];
                    $resultStatus = $interpreted['resultStatus'] ?? ACCEPTED;
                }
            }
        }

        // Nothing to approve if there is no result, and approval stays manual unless
        // the instance has opted into auto approval.
        if (empty($result) || !$this->autoApprove()) {
            $resultStatus = PENDING_APPROVAL;
        }

        $data = [
            'lab_id' => $labId,
            'instrument_id' => $instrument['instrument_id'] ?? null,
            'tested_by' => $this->usersService->getOrCreateUser($this->testerName($row)),
            'result_approved_by' => $this->instrumentUser($instrument, 'approved_by', 'vl'),
            'result_approved_datetime' => $row['authorised_date_time'],
            'result_reviewed_by' => $this->instrumentUser($instrument, 'reviewed_by', 'vl'),
            'result_reviewed_datetime' => $row['authorised_date_time'],
            'sample_tested_datetime' => $row['result_accepted_date_time'],
            'result_value_log' => $logVal,
            'result_value_absolute' => $absVal,
            'result_value_absolute_decimal' => $absDecimalVal,
            'result_value_text' => $txtVal,
            'result' => $result,
            'result_status' => $resultStatus ?? ACCEPTED,
            'vl_test_platform' => $instrument['machine_name'] ?? $row['machine_used'],
            'manual_result_entry' => 'no',
            'import_machine_file_name' => 'interface',
            'result_printed_datetime' => null,
            'result_dispatched_datetime' => null,
            'last_modified_datetime' => DateUtility::getCurrentDateTime(),
            'data_sync' => 0,
        ];

        // Cameroon carries a CV number in the raw ASTM text, keyed by sample code.
        if ($this->formId() === CAMEROON && !empty($row['raw_text'])) {
            $pattern = '/' . preg_quote((string) $existing['sample_code'], '/') . '\^CV\s+(\d+)/i';
            $data['cv_number'] = preg_match($pattern, (string) $row['raw_text'], $matches) ? trim($matches[1]) : null;
        }

        if (in_array(strtolower((string) $result), ['failed', 'fail', 'invalid', 'inconclusive'])) {
            $data['result_status'] = TEST_FAILED;
        }

        // Analyzers report a unit alongside the result and the interpretation follows it,
        // so a mislabelled unit turns into an impossible copies figure. Drop what cannot
        // be true before it reaches the table.
        $data = $this->vlService->sanitizeResultColumnsForWrite($data, 'interfacing');

        $data['vl_result_category'] = $this->vlService->getVLResultCategory($data['result_status'], $data['result']);
        if ($data['vl_result_category'] == 'failed' || $data['vl_result_category'] == 'invalid') {
            $data['result_status'] = TEST_FAILED;
        } elseif ($data['vl_result_category'] == 'rejected') {
            $data['result_status'] = REJECTED;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $instrument
     * @return array<string, mixed>
     */
    private function buildEidData(array $row, ?array $instrument, int $labId): array
    {
        $result = null;
        if (trim((string) $row['results']) !== '') {
            $reported = strtolower((string) $row['results']);
            $result = EidService::interpretEidResult($row['results']) ?? $reported;
        }

        $data = [
            'lab_id' => $labId,
            'tested_by' => $row['tested_by'],
            'instrument_id' => $instrument['instrument_id'] ?? null,
            'result_approved_datetime' => $row['authorised_date_time'],
            'sample_tested_datetime' => $row['result_accepted_date_time'],
            'result' => $result,
            'eid_test_platform' => $row['machine_used'],
            'result_status' => $this->autoApprove() ? ACCEPTED : PENDING_APPROVAL,
            'manual_result_entry' => 'no',
            'result_approved_by' => $this->instrumentUser($instrument, 'approved_by', 'eid'),
            'result_reviewed_by' => $this->instrumentUser($instrument, 'reviewed_by', 'eid'),
            'result_printed_datetime' => null,
            'result_dispatched_datetime' => null,
            'last_modified_datetime' => DateUtility::getCurrentDateTime(),
            'data_sync' => 0,
        ];

        // Nothing to approve if there is no result -- the same rule the viral
        // load branch above states, which this one was missing. bin/interface.php
        // pulls an order on the Interface Tool's own ready flag alone, never on
        // the result being present, so a row flagged ready with an unparsed
        // result reaches here with $result null. Auto-approval then stamped
        // Accepted on a sample holding nothing, which is how the EID grids came
        // to carry accepted samples with no result while viral load stayed clean.
        //
        // The status is dropped from the payload rather than lowered: an order
        // that reports no result says nothing about where the sample got to, so
        // whatever status the lab already recorded stands.
        if (trim((string) $result) === '') {
            unset($data['result_status']);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $existing
     * @param array<string, mixed>|null $instrument
     * @return array<string, mixed>|null null when the sample is neither HBV nor HCV
     */
    private function buildHepatitisData(array $row, array $existing, ?array $instrument, int $labId): ?array
    {
        $testType = strtolower((string) $existing['hepatitis_test_type']);
        if ($testType === 'hbv') {
            $resultField = 'hbv_vl_count';
            $otherField = 'hcv_vl_count';
        } elseif ($testType === 'hcv') {
            $resultField = 'hcv_vl_count';
            $otherField = 'hbv_vl_count';
        } else {
            return null;
        }

        $result = null;
        if (trim((string) $row['results']) !== '') {
            $interpreted = $this->vlService->interpretViralLoadResult(
                trim((string) $row['results']),
                trim((string) $row['test_unit']),
                $instrument['low_vl_result_text'] ?? null
            );
            $result = $interpreted['result'];
        }

        $data = [
            'lab_id' => $labId,
            'instrument_id' => $instrument['instrument_id'] ?? null,
            'tested_by' => $this->usersService->getOrCreateUser($row['tested_by']),
            'result_approved_datetime' => $row['authorised_date_time'],
            'sample_tested_datetime' => $row['result_accepted_date_time'],
            $resultField => $result,
            $otherField => null,
            'hepatitis_test_platform' => $row['machine_used'],
            'result_status' => $this->autoApprove() ? ACCEPTED : PENDING_APPROVAL,
            'manual_result_entry' => 'no',
            'result_approved_by' => $this->instrumentUser($instrument, 'approved_by', 'hepatitis'),
            'result_reviewed_by' => $this->instrumentUser($instrument, 'reviewed_by', 'hepatitis'),
            'result_printed_datetime' => null,
            'result_dispatched_datetime' => null,
            'last_modified_datetime' => DateUtility::getCurrentDateTime(),
            'data_sync' => 0,
        ];

        // As in the EID branch: no result means nothing to approve, and the
        // status the lab already recorded stands.
        if (trim((string) $result) === '') {
            unset($data['result_status']);
        }

        return $data;
    }

    // -----------------------------------------------------------------
    // TB (GeneXpert MTB/RIF and MTB/RIF Ultra)
    // -----------------------------------------------------------------

    /**
     * The MTB/RIF Ultra results the per-test result form offers, exactly as it
     * lists them (app/tb/results/forms/update-rwanda.php and the request forms).
     * A result the form cannot show is not written.
     */
    private const PER_TEST_FORM_ULTRA_RESULTS = [
        'MTB not detected',
        'MTB detected TRACE/RIF indeterminate',
        'MTB Detected Very Low/RIF not detected',
        'MTB Detected Very Low/RIF detected',
        'MTB Detected Low/RIF not detected',
        'MTB Detected Low/RIF detected',
        'MTB Detected Medium/RIF Not Detected',
        'MTB Detected Medium/RIF Detected',
        'MTB Detected High/RIF Not Detected',
        'MTB Detected High/RIF Detected',
        'No result/ invalid',
    ];

    private const PER_TEST_FORM_ULTRA_TEST_TYPE = 'MTB/ RIF Ultra';

    /** @var array<string, int>|null Xpert code (N, T, TI, RR, TT, I) => r_tb_results id */
    private ?array $xpertResultIds = null;
    private ?bool $releaseNegativePools = null;

    /** @param array<string, mixed> $row */
    private function isTbTest(array $row): bool
    {
        // UV2 and MTBXDR are the host test codes GeneXpert ships with; over HL7 the
        // assay name comes through instead ("MTB-RIF_ULTRA").
        return preg_match('/UV2|MTB|XDR/i', (string) ($row['test_type'] ?? '')) === 1;
    }

    /**
     * What an MTB/RIF or MTB/RIF Ultra result says, read from what the Interfacing
     * Tool stores: the first outcome that has a value as the result ("DETECTED LOW",
     * "MTB Trace DETECTED", "NOT DETECTED", "ERROR") and the rest in the notes
     * ("RIF Resistance NOT DETECTED").
     *
     * null when the result cannot be read. A detected result without its rifampicin
     * reading is one of them: rows stored before Interfacing Tool 4.7.0 carry no
     * notes, and a guess at resistance is worse than a result entered by hand.
     *
     * @return array{mtb: 'not_detected'|'detected'|'trace'|'invalid', level: ?string, rif: ?string}|null
     */
    public static function readXpertMtbRif(mixed $result, mixed $notes): ?array
    {
        $value = strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $result)));

        if ($value === 'NOT DETECTED') {
            return ['mtb' => 'not_detected', 'level' => null, 'rif' => null];
        }
        if ($value === 'MTB TRACE DETECTED' || $value === 'TRACE DETECTED') {
            // Ultra cannot read rifampicin resistance on a trace result.
            return ['mtb' => 'trace', 'level' => null, 'rif' => 'indeterminate'];
        }
        if (in_array($value, ['ERROR', 'INVALID', 'NO RESULT', 'FAILED', 'FAIL'], true)) {
            return ['mtb' => 'invalid', 'level' => null, 'rif' => null];
        }
        if (preg_match('/^DETECTED(?: (VERY LOW|LOW|MEDIUM|HIGH))?$/', $value, $detected) !== 1) {
            return null;
        }
        if (preg_match('/RIF RESISTANCE (NOT DETECTED|INDETERMINATE|DETECTED)\b/i', (string) $notes, $rif) !== 1) {
            return null;
        }

        return [
            'mtb' => 'detected',
            'level' => isset($detected[1]) ? ucwords(strtolower($detected[1])) : null,
            'rif' => str_replace(' ', '_', strtolower($rif[1])),
        ];
    }

    /**
     * The result as the per-test form lists it, or null when the form has no entry
     * for it (MTB detected with rifampicin resistance indeterminate, or no level).
     *
     * @param array{mtb: string, level: ?string, rif: ?string} $reading
     */
    public static function perTestFormUltraResult(array $reading): ?string
    {
        $wanted = match ($reading['mtb']) {
            'not_detected' => 'MTB not detected',
            'trace' => 'MTB detected TRACE/RIF indeterminate',
            'invalid' => 'No result/ invalid',
            default => match (true) {
                $reading['level'] === null => null,
                $reading['rif'] === 'not_detected' => "MTB Detected {$reading['level']}/RIF not detected",
                $reading['rif'] === 'detected' => "MTB Detected {$reading['level']}/RIF detected",
                default => null,
            },
        };
        if ($wanted === null) {
            return null;
        }

        // The form's own wording, whose capitalisation varies from line to line.
        foreach (self::PER_TEST_FORM_ULTRA_RESULTS as $listed) {
            if (strcasecmp($listed, $wanted) === 0) {
                return $listed;
            }
        }
        return null;
    }

    /**
     * The Xpert code the single-result forms record (r_tb_results, result_type x-pert).
     *
     * @param array{mtb: string, level: ?string, rif: ?string} $reading
     */
    public static function xpertResultCode(array $reading): ?string
    {
        return match ($reading['mtb']) {
            'not_detected' => 'N',
            'trace' => 'TT',
            'invalid' => 'I',
            default => match ($reading['rif']) {
                'not_detected' => 'T',
                'indeterminate' => 'TI',
                'detected' => 'RR',
                default => null,
            },
        };
    }

    /**
     * The sample codes in a pooled run: a GeneXpert pool is one test whose sample ID
     * lists its members, separated by commas.
     *
     * @return list<string>
     */
    public static function poolMembers(string $orderId): array
    {
        $members = array_filter(array_map('trim', explode(',', $orderId)), static fn(string $m): bool => $m !== '');
        return array_values(array_unique($members));
    }

    /**
     * @param array<string, mixed> $row
     * @return array{synced: bool, updated: bool, table: ?string, reason: string}
     */
    private function importTbResult(
        array $row,
        string $orderId,
        string $testId,
        int $labId,
        bool $includeLocked,
        bool $updateModifiedTime,
        ?int $scopedLabId
    ): array {
        if (!in_array('form_tb', $this->activeModules(), true)) {
            return $this->outcome(false, false, null, 'unsupported_test_type');
        }

        // MTB/XDR reports one outcome per drug, and neither result form has a place
        // for most of them yet.
        if (stripos((string) ($row['test_type'] ?? ''), 'XDR') !== false) {
            return $this->outcome(false, false, 'form_tb', 'unsupported_tb_test');
        }

        $reading = self::readXpertMtbRif($row['results'] ?? null, $row['notes'] ?? null);
        if ($reading === null) {
            return $this->outcome(false, false, 'form_tb', 'unreadable_tb_result');
        }

        $members = self::poolMembers($orderId !== '' ? $orderId : $testId);
        if (count($members) <= 1) {
            $code = $members[0] ?? $orderId;
            $sample = $this->findSample(
                $code,
                $orderId === '' ? $code : $testId,
                $includeLocked,
                $scopedLabId,
                'form_tb'
            );
            if ($sample === null) {
                return $this->outcome(false, false, null, 'no_matching_sample');
            }
            if (!empty($sample['ambiguous'])) {
                return $this->outcome(false, false, 'form_tb', 'ambiguous_sample');
            }
            return $this->writeTbResult($row, $sample, $reading, null, $labId, $updateModifiedTime);
        }

        // A pool is applied whole or not at all: every member has to be one known
        // sample before anything is written.
        $samples = [];
        foreach ($members as $member) {
            $sample = $this->findSample($member, $member, $includeLocked, $scopedLabId, 'form_tb');
            if ($sample === null) {
                return $this->outcome(false, false, 'form_tb', 'pool_member_not_found');
            }
            if (!empty($sample['ambiguous'])) {
                return $this->outcome(false, false, 'form_tb', 'pool_member_ambiguous');
            }
            $samples[$member] = $sample;
        }
        $tbIds = array_map(static fn(array $s): mixed => $s['row']['tb_id'], $samples);
        if (count(array_unique($tbIds)) !== count($tbIds)) {
            return $this->outcome(false, false, 'form_tb', 'pool_member_ambiguous');
        }

        // Anything but a negative pool means each member is tested on its own, and
        // those results arrive as rows of their own.
        if ($reading['mtb'] === 'invalid') {
            return $this->outcome(false, false, 'form_tb', 'pool_invalid_retest');
        }
        if ($reading['mtb'] !== 'not_detected') {
            return $this->outcome(false, false, 'form_tb', 'pool_positive_test_individually');
        }
        if (!$this->releaseNegativePools()) {
            return $this->outcome(false, false, 'form_tb', 'pool_release_off');
        }

        // Anything that would stop one member being recorded stops the pool. A member
        // that already has a later Xpert run of its own is only passed over.
        foreach ($samples as $sample) {
            $refusal = $this->tbRefusal($row, $sample['row'], $reading);
            if ($refusal !== null && !$refusal['synced']) {
                return $this->outcome(false, false, 'form_tb', 'pool_member_' . $refusal['reason']);
            }
        }

        $updated = false;
        foreach ($samples as $member => $sample) {
            $others = array_values(array_diff($members, [$member]));
            $note = 'Pooled with ' . implode(', ', $others) . ': pool NOT DETECTED';
            $outcome = $this->writeTbResult($row, $sample, $reading, $note, $labId, $updateModifiedTime);
            if ($outcome['reason'] === 'update_failed') {
                // Thrown rather than returned, so the caller rolls back the members
                // already written instead of committing part of the pool.
                throw new RuntimeException("Could not record pool $orderId on sample $member");
            }
            $updated = $updated || $outcome['updated'];
        }

        return $this->outcome(true, $updated, 'form_tb', $updated ? 'updated' : 'already_up_to_date');
    }

    /**
     * Records one Xpert result on one TB sample. The final interpretation
     * (form_tb.result) and the sample's status are the clinician's and are never
     * written here.
     *
     * @param array<string, mixed> $row
     * @param array{table: string, primaryKey: string, row: array<string, mixed>} $sample
     * @param array{mtb: string, level: ?string, rif: ?string} $reading
     * @return array{synced: bool, updated: bool, table: ?string, reason: string}
     */
    private function writeTbResult(
        array $row,
        array $sample,
        array $reading,
        ?string $poolNote,
        int $labId,
        bool $updateModifiedTime
    ): array {
        $existing = $sample['row'];
        $tbId = (int) $existing['tb_id'];

        $refusal = $this->tbRefusal($row, $existing, $reading);
        if ($refusal !== null) {
            return $refusal;
        }
        $testedAt = (string) $this->tbTestedAt($row);

        $instrument = $this->findInstrument($row['instrument_id'] ?? $row['machine_used'] ?? null);
        $testedBy = $this->usersService->getOrCreateUser($this->testerName($row));
        $comment = $poolNote;
        if ($reading['mtb'] === 'invalid' && trim((string) ($row['notes'] ?? '')) !== '') {
            $comment = trim(($comment ?? '') . ' ' . trim((string) $row['notes']));
        }

        $formData = [
            'sample_tested_datetime' => $testedAt,
            'tested_by' => $testedBy === null ? null : (string) $testedBy,
            'instrument_id' => isset($instrument['instrument_id']) ? (string) $instrument['instrument_id'] : null,
            'tb_test_platform' => $instrument['machine_name'] ?? $row['machine_used'] ?? null,
            'manual_result_entry' => 'no',
            'import_machine_file_name' => 'interface',
        ];
        // A sample referred in from another lab keeps that lab, as on the result page.
        if (empty($existing['lab_id'])) {
            $formData['lab_id'] = (string) $labId;
        }
        // The sample's tested date, tester and platform are those of its latest test
        // of any kind. A run older than that is still recorded but leaves them alone.
        $recordedAt = trim((string) ($existing['sample_tested_datetime'] ?? ''));
        if ($recordedAt !== '' && substr($testedAt, 0, 16) < substr($recordedAt, 0, 16)) {
            $formData = array_intersect_key($formData, ['lab_id' => true]);
        }

        if ($this->formId() === RWANDA) {
            return $this->writeTbTestRow(
                $existing,
                $reading,
                $comment,
                $formData,
                $testedAt,
                $testedBy === null ? null : (string) $testedBy,
                $labId,
                $updateModifiedTime
            );
        }

        $code = self::xpertResultCode($reading);
        $resultId = $code === null ? null : ($this->xpertResultIds()[$code] ?? null);
        if ($resultId === null) {
            return $this->outcome(false, false, 'form_tb', 'tb_result_not_on_form');
        }

        $data = $formData + [
            'xpert_mtb_result' => (string) $resultId,
            'xpert_result_date' => substr($testedAt, 0, 10),
        ];
        // The lab's own comments are never overwritten.
        if ($comment !== null && trim((string) ($existing['lab_tech_comments'] ?? '')) === '') {
            $data['lab_tech_comments'] = $comment;
        }

        if (MiscUtility::isArrayEqual($data, $existing)) {
            return $this->outcome(true, false, 'form_tb', 'already_up_to_date');
        }

        $this->attempts()->archive('tb', $tbId, TestAttemptService::BY_INTERFACE);

        $data['data_sync'] = 0;
        if ($updateModifiedTime) {
            $data['last_modified_datetime'] = DateUtility::getCurrentDateTime();
        }

        $this->db->connection('default')->where('tb_id', $tbId);
        $updated = $this->db->connection('default')->update('form_tb', $data) === true;

        return $this->outcome($updated, $updated, 'form_tb', $updated ? 'updated' : 'update_failed');
    }

    /**
     * Why this run cannot be recorded on this sample, or null when it can.
     *
     * An outcome with `synced` true is not a failure: the sample already has a later
     * Xpert run, so this one is passed over. Everything else is left for the lab,
     * and a pool with any member in that state is not recorded at all.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $existing
     * @param array{mtb: string, level: ?string, rif: ?string} $reading
     * @return array{synced: bool, updated: bool, table: ?string, reason: string}|null
     */
    private function tbRefusal(array $row, array $existing, array $reading): ?array
    {
        if (($existing['is_sample_rejected'] ?? null) === 'yes') {
            return $this->outcome(false, false, 'form_tb', 'sample_rejected');
        }
        // Once the clinician has written the final interpretation, a result that
        // arrives after it is theirs to weigh, not ours to slip in underneath.
        if (trim((string) ($existing['result'] ?? '')) !== '') {
            return $this->outcome(false, false, 'form_tb', 'result_finalized');
        }

        $testedAt = $this->tbTestedAt($row);
        if ($testedAt === null) {
            return $this->outcome(false, false, 'form_tb', 'no_test_datetime');
        }

        if ($this->formId() === RWANDA) {
            if (self::perTestFormUltraResult($reading) === null) {
                return $this->outcome(false, false, 'form_tb', 'tb_result_not_on_form');
            }
            // Against the sample's Xpert runs only: a smear entered later says
            // nothing about whether this run is still to be recorded.
            $latest = $this->db->connection('default')->rawQueryOne(
                "SELECT DATE_FORMAT(MAX(sample_tested_datetime), '%Y-%m-%d %H:%i') AS latest
                    FROM tb_tests WHERE tb_id = ? AND test_type = ?",
                [(int) $existing['tb_id'], self::PER_TEST_FORM_ULTRA_TEST_TYPE]
            );
            $latest = (string) ($latest['latest'] ?? '');
            if ($latest !== '' && substr($testedAt, 0, 16) < $latest) {
                return $this->outcome(true, false, 'form_tb', 'newer_result_on_record');
            }
            return null;
        }

        $code = self::xpertResultCode($reading);
        $resultId = $code === null ? null : ($this->xpertResultIds()[$code] ?? null);
        if ($resultId === null) {
            return $this->outcome(false, false, 'form_tb', 'tb_result_not_on_form');
        }

        // The single-result forms hold one Xpert result, and the attempt history
        // keeps nothing of it until there is a final interpretation. So a recorded
        // result is only ever replaced when it was I (invalid, error or no result)
        // and this is the retest; any other difference is the lab's to resolve.
        $recorded = trim((string) ($existing['xpert_mtb_result'] ?? ''));
        if ($recorded === '' || $recorded === (string) $resultId) {
            return null;
        }
        if ($recorded !== (string) ($this->xpertResultIds()['I'] ?? '')) {
            return $this->outcome(false, false, 'form_tb', 'xpert_result_on_record');
        }
        $recordedOn = trim((string) ($existing['xpert_result_date'] ?? ''));
        if ($recordedOn !== '' && substr($testedAt, 0, 10) < $recordedOn) {
            return $this->outcome(true, false, 'form_tb', 'newer_result_on_record');
        }
        return null;
    }

    /** @param array<string, mixed> $row */
    private function tbTestedAt(array $row): ?string
    {
        return DateUtility::getDateTime(
            (string) (($row['result_accepted_date_time'] ?? null) ?: ($row['analysed_date_time'] ?? ''))
        );
    }

    /**
     * The per-test form keeps each test as a tb_tests row. The result page deletes
     * and recreates those rows from what it posts, so an import only ever adds one:
     * it never rewrites or removes a row the lab has seen.
     *
     * Whether a run is already recorded is decided by the row's own content -- the
     * test type and when it was tested, to the minute -- because that is what
     * survives a save of the result page, which keeps no column of ours and
     * drops the seconds.
     *
     * @param array<string, mixed> $existing
     * @param array{mtb: string, level: ?string, rif: ?string} $reading
     * @param array<string, mixed> $formData
     * @return array{synced: bool, updated: bool, table: ?string, reason: string}
     */
    private function writeTbTestRow(
        array $existing,
        array $reading,
        ?string $comment,
        array $formData,
        string $testedAt,
        ?string $testedBy,
        int $labId,
        bool $updateModifiedTime
    ): array {
        $tbId = (int) $existing['tb_id'];
        $testResult = (string) self::perTestFormUltraResult($reading);

        $db = $this->db->connection('default');
        $recorded = $db->rawQueryOne(
            "SELECT tb_test_id FROM tb_tests
                WHERE tb_id = ? AND test_type = ?
                AND DATE_FORMAT(sample_tested_datetime, '%Y-%m-%d %H:%i') = ?",
            [$tbId, self::PER_TEST_FORM_ULTRA_TEST_TYPE, substr($testedAt, 0, 16)]
        );
        if (!empty($recorded)) {
            // Also when the lab has since changed that row's result: theirs stands.
            return $this->outcome(true, false, 'form_tb', 'already_up_to_date');
        }

        $inserted = $db->insert('tb_tests', [
            'tb_id' => $tbId,
            'lab_id' => $labId,
            'specimen_type' => $existing['specimen_type'] ?? null,
            'sample_received_at_lab_datetime' => $existing['sample_received_at_lab_datetime'] ?? null,
            'test_type' => self::PER_TEST_FORM_ULTRA_TEST_TYPE,
            'test_result' => $testResult,
            'sample_tested_datetime' => $testedAt,
            'tested_by' => $testedBy,
            'comments' => $comment,
            'updated_datetime' => DateUtility::getCurrentDateTime(),
        ]);
        if (!$inserted) {
            return $this->outcome(false, false, 'form_tb', 'update_failed');
        }

        // form_tb carries the latest test's details, as the result page leaves them.
        $formData['data_sync'] = 0;
        if ($updateModifiedTime) {
            $formData['last_modified_datetime'] = DateUtility::getCurrentDateTime();
        }
        $db->where('tb_id', $tbId);
        if ($db->update('form_tb', $formData) !== true) {
            throw new RuntimeException("Recorded a TB test on sample $tbId but could not update the sample");
        }

        return $this->outcome(true, true, 'form_tb', 'updated');
    }

    /** @return array<string, int> */
    private function xpertResultIds(): array
    {
        if ($this->xpertResultIds === null) {
            $this->xpertResultIds = [];
            // By the code each entry starts with rather than by id: ids are
            // AUTO_INCREMENT and differ between installs. Only what the forms list
            // as Xpert results: an entry filed under another type (RR was, before
            // 5.7.79, and an STS that has not upgraded sends it back that way) is
            // not shown on the form, and its next save would blank it.
            $rows = $this->db->connection('default')->rawQuery(
                "SELECT result_id, result FROM r_tb_results
                    WHERE status = 'active' AND result_type = 'x-pert'
                    AND result REGEXP '^(N|T|TI|RR|TT|I) \\\\('
                    ORDER BY result_id ASC"
            ) ?: [];
            foreach ($rows as $result) {
                $code = strstr((string) $result['result'], ' (', true);
                $this->xpertResultIds[$code] ??= (int) $result['result_id'];
            }
        }

        return $this->xpertResultIds;
    }

    private function releaseNegativePools(): bool
    {
        return $this->releaseNegativePools ??= $this->commonService->getGlobalConfig(
            'tb_interface_release_negative_pools'
        ) === 'yes';
    }

    // -----------------------------------------------------------------
    // Small helpers
    // -----------------------------------------------------------------

    /** @param array<string, mixed> $row */
    private function unit(array $row): string
    {
        return strtolower((string) preg_replace('/\s+/', '', (string) ($row['test_unit'] ?? '')));
    }

    /**
     * Some analyzers pack both the tester and the releaser into the operator field
     * as "tester^releaser". Only the tester is recorded.
     *
     * @param array<string, mixed> $row
     */
    private function testerName(array $row): mixed
    {
        $tester = $row['tested_by'];
        if (str_contains(strtolower((string) $tester), '^')) {
            return explode('^', (string) $tester)[0];
        }

        return $tester;
    }

    /** @param array<string, mixed>|null $instrument */
    private function instrumentUser(?array $instrument, string $column, string $testType): mixed
    {
        if (empty($instrument[$column])) {
            return null;
        }

        $users = json_decode((string) $instrument[$column], true);
        return is_array($users) ? ($users[$testType] ?? null) : null;
    }

    /** @return array<string, string> */
    private function activeModules(): array
    {
        if ($this->activeModules === null) {
            $this->activeModules = [];
            foreach (TestsService::getActiveTests() as $module) {
                $this->activeModules[TestsService::getPrimaryColumn($module)] = TestsService::getTestTableName($module);
            }
        }

        return $this->activeModules;
    }

    private function formId(): int
    {
        return $this->formId ??= (int) $this->commonService->getGlobalConfig('vl_form');
    }

    private function autoApprove(): bool
    {
        return $this->autoApprove ??= $this->commonService->getGlobalConfig('auto_approve_interface_results') === 'yes';
    }

    /** @return array{synced: bool, updated: bool, table: ?string, reason: string} */
    private function outcome(bool $synced, bool $updated, ?string $table, string $reason): array
    {
        return ['synced' => $synced, 'updated' => $updated, 'table' => $table, 'reason' => $reason];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Utilities\MiscUtility;
use Throwable;

use const COUNTRY\CAMEROON;
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
     * @param array<string, mixed> $row
     * @return array{synced: bool, updated: bool, table: ?string, reason: string}
     */
    public function importResult(
        array $row,
        int $labId,
        bool $includeLocked = false,
        bool $updateModifiedTime = true,
        bool $scopeToLab = false
    ): array {
        if (empty($row['order_id']) && empty($row['test_id'])) {
            return $this->outcome(false, false, null, 'no_order_or_test_id');
        }

        $orderId = (string) ($row['order_id'] ?? '');
        $testId = (string) ($row['test_id'] ?? '');
        $scopedLabId = $scopeToLab ? $labId : null;

        $sample = $this->findSample($orderId, $testId, $includeLocked, $scopedLabId);
        if ($sample === null) {
            // Tell a locked sample apart from one that does not exist, so a technician
            // is not sent looking for a missing request when the sample is simply locked.
            $locked = !$includeLocked
                && $this->findSample($orderId, $testId, true, $scopedLabId) !== null;

            return $this->outcome(false, false, null, $locked ? 'sample_locked' : 'no_matching_sample');
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

        if (!$updateModifiedTime) {
            unset($data['last_modified_datetime']);
        }

        // Timestamps always differ, so comparing them would make every row look changed.
        $ignoredKeys = ['last_modified_datetime', 'result_printed_datetime'];
        if (MiscUtility::isArrayEqual($data, $existing, $ignoredKeys)) {
            return $this->outcome(true, false, $table, 'already_up_to_date');
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
            'already_up_to_date' => ['unchanged', 1],
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
     * @return array{table: string, primaryKey: string, row: array<string, mixed>}|null
     */
    private function findSample(
        string $orderId,
        string $testId,
        bool $includeLocked,
        ?int $restrictToLabId = null
    ): ?array {
        foreach ($this->activeModules() as $primaryKey => $table) {
            $conditions = [];
            $params = [$orderId, $orderId, $orderId, $testId, $testId, $testId];

            if (!$includeLocked) {
                $conditions[] = "IFNULL(locked, 'no') = 'no'";
            }

            // NOTE: the bind order is carried over verbatim from bin/interface.php so that
            // moving this lookup does not change which samples match. It pairs up as
            // (order_id, order_id), (order_id, test_id), (test_id, test_id) rather than
            // giving each column both values.
            $conditions[] = '(sample_code IN (?, ?) OR remote_sample_code IN (?, ?) OR lab_assigned_code IN (?, ?))';

            // Callers that cannot be trusted to only send their own samples -- anything
            // arriving over the API -- restrict the match to samples already belonging to
            // that lab, or not yet assigned to any lab. Without this an installation could
            // overwrite another lab's result by guessing a sample code.
            if ($restrictToLabId !== null) {
                $conditions[] = '(lab_id = ? OR lab_id IS NULL OR lab_id = 0)';
                $params[] = $restrictToLabId;
            }

            $conditions = implode(' AND ', $conditions);

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
            if (str_contains($reported, 'not detected')) {
                $result = 'negative';
            } elseif (str_contains($reported, 'detected') || str_contains($reported, 'passed')) {
                $result = 'positive';
            } else {
                $result = $reported;
            }
        }

        return [
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

        return [
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

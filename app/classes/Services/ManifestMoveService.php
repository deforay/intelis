<?php

namespace App\Services;

use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\NO_RESULT;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\TEST_FAILED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;

/**
 * Moving a collection manifest, and every sample on it, to another testing lab.
 *
 * Done on the STS, which decides which lab a sample belongs to. A manifest with
 * any sample a lab has already tested is not moved: the result belongs to the
 * lab that produced it. Otherwise each sample goes to the new lab as if just
 * dispatched there -- its code (from the old lab's series), its reception and
 * its received status are cleared, so the new lab activates the manifest and
 * gives the samples codes of its own.
 *
 * The lab each sample was taken from is told to cancel its copy (the
 * withdraw-samples command), when its LIS has said it can. Its copy would
 * otherwise stay live there, and could still be received and tested.
 */
final class ManifestMoveService
{
    public const WITHDRAW_COMMAND = 'withdraw-samples';

    /** How recently a lab must have said it handles withdraw-samples. */
    private const CAPABILITY_STALE_MINUTES = 30 * 24 * 60;

    /** Statuses that mean the lab has decided about the sample. */
    private const DECIDED_STATUSES = [REJECTED, TEST_FAILED, ACCEPTED, PENDING_APPROVAL, NO_RESULT];

    public function __construct(
        private readonly DatabaseService $db,
        private readonly LabCapabilityService $labCapabilities
    ) {
    }

    /**
     * SQL that is true for a sample a lab has tested or otherwise decided
     * about: a decided status, a test date, or a result entered.
     */
    public static function testedCondition(string $testType, string $alias = ''): string
    {
        $prefix = $alias === '' ? '' : "$alias.";
        $resultColumn = $testType === 'cd4' ? 'cd4_result' : 'result';
        return "({$prefix}result_status IN (" . implode(',', self::DECIDED_STATUSES) . ')'
            . " OR {$prefix}sample_tested_datetime IS NOT NULL"
            . " OR NULLIF(TRIM({$prefix}{$resultColumn}), '') IS NOT NULL)";
    }

    /**
     * The collection manifests of a lab, newest first: those it is the
     * manifest's lab for, or holds samples of.
     *
     * @return list<array{code: string, created: ?string, status: ?string, samples: int, tested: int}>
     */
    public function listForLab(string $testType, int $labId, ?string $from = null, ?string $to = null): array
    {
        $table = TestsService::getTestTableName($testType);
        $where = ['m.module = ?', "COALESCE(m.manifest_type, 'collection') <> 'referral'"];
        $params = [$testType];
        if ($from !== null && $from !== '' && $to !== null && $to !== '') {
            $where[] = 'm.request_created_datetime BETWEEN ? AND ?';
            array_push($params, $from, $to);
        }
        $rows = $this->db->rawQuery(
            "SELECT m.manifest_code, m.request_created_datetime, m.manifest_status,
                    COUNT(t.unique_id) AS samples,
                    COALESCE(SUM(" . self::testedCondition($testType, 't') . "), 0) AS tested
               FROM specimen_manifests m
               JOIN `$table` t
                 ON t.sample_package_id = m.manifest_id OR t.sample_package_code = m.manifest_code
              WHERE " . implode(' AND ', $where) . "
              GROUP BY m.manifest_id, m.manifest_code, m.request_created_datetime, m.manifest_status
             HAVING MAX(m.lab_id = ?) = 1 OR MAX(t.lab_id = ?) = 1
              ORDER BY m.request_created_datetime DESC
              LIMIT 500",
            [...$params, $labId, $labId]
        );
        return array_map(static fn(array $row): array => [
            'code' => (string) $row['manifest_code'],
            'created' => $row['request_created_datetime'],
            'status' => $row['manifest_status'],
            'samples' => (int) $row['samples'],
            'tested' => (int) $row['tested'],
        ], $rows);
    }

    /**
     * Moves the given manifests to $toLabId. Each manifest is moved whole or
     * not at all.
     *
     * @param list<string> $manifestCodes
     * @return array{
     *     moved: array<string, int>,
     *     refused: array<string, string>,
     *     withdrawals: list<array{labId: int, samples: int, commandId: ?string}>
     * }
     */
    public function move(
        string $testType,
        array $manifestCodes,
        int $toLabId,
        string $reason,
        ?string $userId
    ): array {
        $outcome = ['moved' => [], 'refused' => [], 'withdrawals' => []];
        if ($toLabId <= 0) {
            throw new \InvalidArgumentException('A testing lab to move the manifests to is required');
        }
        $table = TestsService::getTestTableName($testType);
        $primaryKey = TestsService::getPrimaryColumn($testType);
        $now = DateUtility::getCurrentDateTime();

        /** @var array<int, list<string>> $withdrawnByLab unique ids each lab must cancel */
        $withdrawnByLab = [];
        /** @var array<int, list<string>> $manifestsByLab */
        $manifestsByLab = [];
        /** @var list<string> $movedHere unique ids now at $toLabId */
        $movedHere = [];

        $this->db->beginTransaction();
        try {
            foreach (self::cleanCodes($manifestCodes) as $code) {
                $manifest = $this->db->rawQueryOne(
                    'SELECT manifest_id, lab_id, manifest_type, manifest_status, manifest_change_history
                       FROM specimen_manifests WHERE manifest_code = ? AND module = ? FOR UPDATE',
                    [$code, $testType]
                );
                if (empty($manifest)) {
                    $outcome['refused'][$code] = 'not-found';
                    continue;
                }
                if (($manifest['manifest_type'] ?? 'collection') === 'referral') {
                    $outcome['refused'][$code] = 'referral';
                    continue;
                }
                $samples = $this->db->rawQuery(
                    "SELECT `$primaryKey` AS id, unique_id, lab_id, result_status,
                            " . self::testedCondition($testType) . " AS tested
                       FROM `$table`
                      WHERE sample_package_id = ? OR sample_package_code = ?
                      FOR UPDATE",
                    [(int) $manifest['manifest_id'], $code]
                );
                if (array_filter($samples, static fn(array $s): bool => (int) $s['tested'] === 1) !== []) {
                    $outcome['refused'][$code] = 'tested';
                    continue;
                }
                $toMove = array_values(array_filter(
                    $samples,
                    static fn(array $s): bool => (int) ($s['lab_id'] ?? 0) !== $toLabId
                ));
                if ($toMove === [] && (int) $manifest['lab_id'] === $toLabId) {
                    $outcome['refused'][$code] = 'already-there';
                    continue;
                }

                foreach ($toMove as $sample) {
                    $movedHere[] = (string) $sample['unique_id'];
                    $fromLab = (int) ($sample['lab_id'] ?? 0);
                    $received = (int) ($sample['result_status'] ?? 0) === RECEIVED_AT_TESTING_LAB;
                    $this->db->rawQuery(
                        "UPDATE `$table`
                            SET referring_lab_id = ?, lab_id = ?,
                                sample_code = NULL, sample_code_key = NULL, sample_code_format = NULL,
                                sample_received_at_lab_datetime = NULL,
                                result_status = ?,
                                samples_referred_datetime = ?, last_modified_datetime = ?, data_sync = 0
                          WHERE `$primaryKey` = ?",
                        [
                            $fromLab > 0 ? $fromLab : null,
                            $toLabId,
                            $received ? RECEIVED_AT_CLINIC : (int) $sample['result_status'],
                            $now,
                            $now,
                            $sample['id'],
                        ]
                    );
                    if ($fromLab > 0 && trim((string) $sample['unique_id']) !== '') {
                        $withdrawnByLab[$fromLab][] = (string) $sample['unique_id'];
                        $manifestsByLab[$fromLab][$code] = $code;
                    }
                }

                $history = json_decode((string) ($manifest['manifest_change_history'] ?? ''), true);
                $history = is_array($history) ? $history : [];
                $history[] = [
                    'reason' => $reason,
                    'changedBy' => $userId,
                    'date' => $now,
                    'fromLabId' => (int) $manifest['lab_id'],
                    'toLabId' => $toLabId,
                ];
                $status = $manifest['manifest_status'] === TestRequestsService::MANIFEST_RECEIVED
                    ? TestRequestsService::MANIFEST_DISPATCHED
                    : $manifest['manifest_status'];
                $this->db->rawQuery(
                    'UPDATE specimen_manifests
                        SET lab_id = ?, manifest_status = ?, manifest_change_history = ?, last_modified_datetime = ?
                      WHERE manifest_id = ?',
                    [$toLabId, $status, json_encode($history), $now, (int) $manifest['manifest_id']]
                );
                $outcome['moved'][$code] = count($toMove);
            }

            $this->dropWithdrawals($toLabId, $movedHere, $now);

            foreach ($withdrawnByLab as $fromLab => $uniqueIds) {
                $commandId = null;
                $canWithdraw = $this->labCapabilities->supportsCommand(
                    $fromLab,
                    self::WITHDRAW_COMMAND,
                    self::CAPABILITY_STALE_MINUTES
                );
                if ($canWithdraw) {
                    $commandId = MiscUtility::generateULID(false);
                    $this->db->insert('s_lis_remote_commands', [
                        'command_id' => $commandId,
                        'lab_id' => $fromLab,
                        'command' => self::WITHDRAW_COMMAND,
                        'params' => json_encode([
                            'testType' => $testType,
                            'movedToLabId' => $toLabId,
                            'manifestCodes' => array_values($manifestsByLab[$fromLab] ?? []),
                            'uniqueIds' => $uniqueIds,
                        ]),
                        'status' => 'pending',
                        'requested_by' => $userId,
                        'requested_at' => $now,
                        'nonce' => MiscUtility::generateULID(false),
                    ]);
                }
                $outcome['withdrawals'][] = [
                    'labId' => $fromLab,
                    'samples' => count($uniqueIds),
                    'commandId' => $commandId,
                ];
            }

            $this->db->commitTransaction();
        } catch (Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }

        return $outcome;
    }

    /**
     * A sample moved (back) to a lab must not be cancelled there by a withdrawal
     * from an earlier move that the lab has not picked up yet. Those samples
     * come out of its pending withdrawals; one left with none is cancelled.
     * One the lab already ran is undone by its next pull (LabRequestSyncService).
     *
     * @param list<string> $uniqueIds
     */
    private function dropWithdrawals(int $labId, array $uniqueIds, string $now): void
    {
        if ($uniqueIds === []) {
            return;
        }
        $pending = $this->db->rawQuery(
            "SELECT command_id, params FROM s_lis_remote_commands
              WHERE lab_id = ? AND command = ? AND status = 'pending' FOR UPDATE",
            [$labId, self::WITHDRAW_COMMAND]
        );
        foreach ($pending as $command) {
            $params = json_decode((string) $command['params'], true);
            if (!is_array($params) || !is_array($params['uniqueIds'] ?? null)) {
                continue;
            }
            $left = array_values(array_diff($params['uniqueIds'], $uniqueIds));
            if (count($left) === count($params['uniqueIds'])) {
                continue;
            }
            if ($left === []) {
                $this->db->rawQuery(
                    "UPDATE s_lis_remote_commands
                        SET status = 'cancelled', completed_at = ?, last_error = 'Superseded by a later move'
                      WHERE command_id = ?",
                    [$now, $command['command_id']]
                );
            } else {
                $params['uniqueIds'] = $left;
                $this->db->rawQuery(
                    'UPDATE s_lis_remote_commands SET params = ? WHERE command_id = ?',
                    [json_encode($params), $command['command_id']]
                );
            }
        }
    }

    /**
     * @param array<mixed> $codes
     * @return list<string>
     */
    private static function cleanCodes(array $codes): array
    {
        // Older cached pickers wrapped each code in literal quotes.
        $codes = array_map(static fn($code): string => trim(trim((string) $code), "'"), $codes);
        return array_values(array_unique(array_filter($codes, 'strlen')));
    }
}

<?php

namespace App\Services;

use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Utilities\TestedSampleRule;

use const SAMPLE_STATUS\CANCELLED;
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

    public function __construct(
        private readonly DatabaseService $db,
        private readonly LabCapabilityService $labCapabilities
    ) {
    }

    /**
     * The collection manifests of a lab, newest first: those it is the
     * manifest's lab for, or holds samples of. With a facility map (a user
     * limited to some facilities), only manifests all of whose samples are
     * from those facilities.
     *
     * @return list<array{code: string, created: ?string, status: ?string, samples: int, tested: int}>
     */
    public function listForLab(
        string $testType,
        int $labId,
        ?string $from = null,
        ?string $to = null,
        ?string $facilityMap = null
    ): array {
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
                    COALESCE(SUM(" . TestedSampleRule::sql($testType, 't') . "), 0) AS tested
               FROM specimen_manifests m
               JOIN `$table` t
                 ON t.sample_package_id = m.manifest_id OR t.sample_package_code = m.manifest_code
              WHERE " . implode(' AND ', $where) . "
              GROUP BY m.manifest_id, m.manifest_code, m.request_created_datetime, m.manifest_status
             HAVING (MAX(m.lab_id = ?) = 1 OR MAX(t.lab_id = ?) = 1)"
            . $this->withinFacilities('t', $facilityMap) . "
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
        ?string $userId,
        ?string $facilityMap = null
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
                    "SELECT t.`$primaryKey` AS id, t.unique_id, t.lab_id, t.result_status,
                            " . TestedSampleRule::sql($testType, 't') . " AS tested
                       FROM `$table` AS t
                      WHERE t.sample_package_id = ? OR t.sample_package_code = ?
                      FOR UPDATE",
                    [(int) $manifest['manifest_id'], $code]
                );
                if ($facilityMap !== null && trim($facilityMap) !== '') {
                    $outside = $this->db->rawQueryOne(
                        "SELECT 1 AS outside FROM `$table` AS t
                          WHERE (t.sample_package_id = ? OR t.sample_package_code = ?)
                            AND NOT (t.facility_id IN (" . $this->db->inIntList($facilityMap) . ')) LIMIT 1',
                        [(int) $manifest['manifest_id'], $code]
                    );
                    if (!empty($outside)) {
                        $outcome['refused'][$code] = 'not-found';
                        continue;
                    }
                }
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

            $this->dropWithdrawals($testType, $toLabId, $movedHere, $now);

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
     * Puts back samples a move gave to another lab and a lab's push then gave
     * back to the old lab, before the STS refused such pushes.
     *
     * The lab moved to held copies of some of them already, pulled through a
     * clinic mapped to it, under the old lab; its pull never changed a lab, and
     * its next results push named the old lab again. Those samples sit on a
     * moved manifest, marked moved (samples_referred_datetime), with a lab
     * other than the manifest's, and untested -- a tested one stays with the
     * lab that tested it. Each goes back to the manifest's lab, recording the
     * lab it is taken from, and is sent to its lab again. Its code is kept: the
     * lab moved to gave it. If that lab already uses the code for another
     * sample, the code is cleared and activation gives a new one. A sample
     * moved on its own since (Move Samples) is left where it was put. Move
     * Samples records only the day, so one moved on its own earlier the same
     * day is left too: accepted, because the tool has no menu entry and a
     * sample left behind is put right by moving its manifest again, whereas
     * one wrongly put back would undo a move an operator chose.
     *
     * @return array<string, int> samples put back, by table
     */
    public function healRevertedMoves(): array
    {
        $healed = [];
        $now = DateUtility::getCurrentDateTime();
        $seen = [];
        foreach (TestsService::getActiveTests() as $testType) {
            $table = TestsService::getTestTableName($testType);
            if (isset($seen[$table])) {
                continue;
            }
            $seen[$table] = true;
            $primaryKey = TestsService::getPrimaryColumn($testType);
            $rows = $this->db->rawQuery(
                "SELECT DISTINCT t.`$primaryKey` AS id, t.lab_id, t.sample_code,
                        m.manifest_id, m.lab_id AS manifest_lab
                   FROM `$table` AS t
                   JOIN specimen_manifests AS m
                     ON m.module = ? AND (m.manifest_id = t.sample_package_id OR m.manifest_code = t.sample_package_code)
                  WHERE m.lab_id > 0 AND t.lab_id > 0 AND t.lab_id <> m.lab_id
                    AND COALESCE(JSON_LENGTH(m.manifest_change_history), 0) > 0
                    AND t.samples_referred_datetime IS NOT NULL
                    AND t.result_status <> " . CANCELLED . "
                    AND NOT EXISTS (
                        SELECT 1 FROM move_samples_map msm
                          JOIN move_samples ms ON ms.move_sample_id = msm.move_sample_id
                         WHERE msm.test_type = ? AND msm.test_type_sample_id = t.`$primaryKey`
                           AND ms.moved_on >= DATE(t.samples_referred_datetime)
                    )
                    AND NOT " . TestedSampleRule::sql($testType, 't'),
                [$testType, $testType]
            );
            foreach ($rows as $row) {
                $clash = trim((string) $row['sample_code']) !== '' && !empty($this->db->rawQueryOne(
                    "SELECT 1 AS clash FROM `$table` WHERE sample_code = ? AND lab_id = ? AND `$primaryKey` <> ?",
                    [$row['sample_code'], $row['manifest_lab'], $row['id']]
                ));
                // Rechecked as it is written: a result or a code the lab sent, or
                // a change to the manifest or its samples, since the rows were read
                // keeps the sample where it is. A sample
                // the new lab cannot take (its mobile-app code is used there) is
                // logged and left, and does not stop the others.
                try {
                    $this->db->rawQuery(
                        "UPDATE `$table` AS t
                            SET t.referring_lab_id = t.lab_id, t.lab_id = ?, t.data_sync = 0, t.last_modified_datetime = ?"
                            . ($clash ? ', t.sample_code = NULL, t.sample_code_key = NULL, t.sample_code_format = NULL' : '')
                            . " WHERE t.`$primaryKey` = ? AND t.lab_id = ? AND t.sample_code <=> ?
                                AND t.result_status <> " . CANCELLED . "
                                AND EXISTS (
                                    SELECT 1 FROM specimen_manifests m
                                     WHERE m.manifest_id = ? AND m.lab_id = ?
                                       AND (t.sample_package_id = m.manifest_id OR t.sample_package_code = m.manifest_code)
                                )
                                AND NOT " . TestedSampleRule::sql($testType, 't'),
                        [
                            (int) $row['manifest_lab'], $now, $row['id'], (int) $row['lab_id'], $row['sample_code'],
                            (int) $row['manifest_id'], (int) $row['manifest_lab'],
                        ]
                    );
                } catch (Throwable $e) {
                    // Only the unique-key clash is expected. Anything else fails the
                    // run, so the run-once is not recorded done and is tried again.
                    if ((int) $e->getCode() !== 1062) {
                        throw $e;
                    }
                    LoggerUtility::logWarning('Could not give a moved sample back to its lab', [
                        'table' => $table,
                        'id' => $row['id'],
                        'lab_id' => (int) $row['manifest_lab'],
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
                if ((int) $this->db->count > 0) {
                    $healed[$table] = ($healed[$table] ?? 0) + 1;
                }
            }
        }
        return $healed;
    }

    /**
     * A sample moved (back) to a lab must not be cancelled there by a withdrawal
     * from an earlier move that the lab has not picked up yet. Those samples
     * come out of its pending withdrawals; one left with none is cancelled.
     * One the lab already ran is undone by its next pull (LabRequestSyncService).
     *
     * @param list<string> $uniqueIds
     */
    private function dropWithdrawals(string $testType, int $labId, array $uniqueIds, string $now): void
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
            // unique_id is unique within one test type's table only.
            if (
                !is_array($params) || !is_array($params['uniqueIds'] ?? null)
                || ($params['testType'] ?? null) !== $testType
            ) {
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

    /** HAVING clause part keeping groups whose samples are all in the facility map. */
    private function withinFacilities(string $alias, ?string $facilityMap): string
    {
        if ($facilityMap === null || trim($facilityMap) === '') {
            return '';
        }
        return " AND MIN($alias.facility_id IN (" . $this->db->inIntList($facilityMap) . ')) = 1';
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

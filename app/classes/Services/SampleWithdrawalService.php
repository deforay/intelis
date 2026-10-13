<?php

namespace App\Services;

use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Utilities\TestedSampleRule;

use const SAMPLE_STATUS\CANCELLED;

/**
 * A lab cancelling its copies of samples the STS moved to another lab (the
 * withdraw-samples command, queued by ManifestMoveService).
 *
 * The STS refuses to move a sample it knows to be tested, but this lab may have
 * tested one since its last sync. Such a sample is left exactly as it is and
 * reported back: the result is this lab's, and its next sync claims the sample.
 *
 * A cancelled copy keeps its lab and code, is marked synced and is kept out of
 * every results send (notWithdrawnCondition). Sent, it would put "cancelled"
 * on the sample the new lab is working on; the STS refuses it in any case
 * (ResultsService, a lab a sample was moved away from).
 */
final class SampleWithdrawalService
{
    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * SQL that is true for a row that is NOT a withdrawn copy. Kept out of every
     * results send, including a resend of everything changed since a date,
     * which ignores data_sync.
     */
    public static function notWithdrawnCondition(string $alias): string
    {
        return "NOT ($alias.result_status = " . CANCELLED
            . " AND JSON_EXTRACT($alias.form_attributes, '$.movedToLabId') IS NOT NULL)";
    }

    /**
     * @param list<string> $uniqueIds
     * @return array{
     *     cancelled: list<string>, tested: list<string>,
     *     alreadyCancelled: list<string>, notFound: list<string>, failed: list<string>
     * }
     */
    public function withdraw(string $testType, array $uniqueIds, int $movedToLabId): array
    {
        $outcome = ['cancelled' => [], 'tested' => [], 'alreadyCancelled' => [], 'notFound' => [], 'failed' => []];
        $table = TestsService::getTestTableName($testType);
        $primaryKey = TestsService::getPrimaryColumn($testType);
        $now = DateUtility::getCurrentDateTime();
        $uniqueIds = array_values(array_unique(array_filter(
            array_map(static fn($id): string => trim((string) $id), $uniqueIds),
            'strlen'
        )));

        foreach ($uniqueIds as $uniqueId) {
            $this->db->beginTransaction();
            try {
                $row = $this->db->rawQueryOne(
                    "SELECT t.`$primaryKey` AS id, t.result_status,
                            " . TestedSampleRule::sql($testType, 't') . " AS tested
                       FROM `$table` AS t WHERE t.unique_id = ? FOR UPDATE",
                    [$uniqueId]
                );
                if (empty($row)) {
                    $outcome['notFound'][] = $uniqueId;
                } elseif ((int) $row['tested'] === 1) {
                    $outcome['tested'][] = $uniqueId;
                } elseif ((int) $row['result_status'] === CANCELLED) {
                    $outcome['alreadyCancelled'][] = $uniqueId;
                } else {
                    $this->db->rawQuery(
                        "UPDATE `$table`
                            SET result_status = ?, data_sync = 1, last_modified_datetime = ?,
                                form_attributes = JSON_SET(
                                    COALESCE(form_attributes, JSON_OBJECT()),
                                    '$.movedToLabId', ?, '$.movedOn', ?
                                )
                          WHERE `$primaryKey` = ?",
                        [CANCELLED, $now, $movedToLabId, $now, $row['id']]
                    );
                    $outcome['cancelled'][] = $uniqueId;
                }
                $this->db->commitTransaction();
            } catch (Throwable $e) {
                // One sample that cannot be cancelled must not leave the rest live.
                $this->db->rollbackTransaction();
                $outcome['failed'][] = $uniqueId;
                LoggerUtility::logError('Could not withdraw a moved sample: ' . $e->getMessage(), [
                    'test_type' => $testType,
                    'unique_id' => $uniqueId,
                ]);
            }
        }

        return $outcome;
    }
}

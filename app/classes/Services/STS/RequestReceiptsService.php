<?php

declare(strict_types=1);

namespace App\Services\STS;

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Services\DatabaseService;
use Throwable;
use InvalidArgumentException;

/**
 * Applies a lab's receipt for the requests it pulled.
 *
 * A lab that asks for receipts gets its requests marked data_sync = 2 (in flight)
 * when they are sent. Its receipt then says which it saved and which it could not.
 * Saved ones become data_sync = 1, but only while still 2: an STS edit made while
 * the request was out has set it back to 0, and that edit still has to go. Failed
 * ones go back to 0 and are recorded in request_sync_failures with the lab's
 * reason, so later pulls send them again.
 *
 * Only rows the lab is allowed to pull are touched, by the same lab and facility
 * scope requests.php selects on.
 */
final class RequestReceiptsService
{
    public const int IN_FLIGHT = 2;

    /** Largest receipt accepted in one call; a lab sends one per pulled batch. */
    public const int MAX_IDS = 5000;

    private const int CHUNK = 500;
    private const int MAX_REASON_LENGTH = 1000;

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * @param list<mixed> $saved unique_ids the lab saved
     * @param list<mixed> $failed [{unique_id, reason}] the lab could not save
     * @param string|int|null $facilityMap comma-separated facility ids the lab also serves
     * @return array{confirmed: int, failed: int}
     */
    public function apply(
        int $labId,
        string $testType,
        array $saved,
        array $failed,
        string|int|null $facilityMap = null
    ): array {
        try {
            $table = TestsService::getTestTableName($testType);
        } catch (Throwable) {
            $table = '';
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $table)) {
            throw new InvalidArgumentException('Unknown test type');
        }

        $savedIds = self::uniqueIds($saved);
        $isSaved = array_fill_keys($savedIds, true);
        $failures = [];
        foreach ($failed as $entry) {
            $id = is_array($entry) ? self::text($entry['unique_id'] ?? null) : '';
            // A request listed as both saved and failed was saved.
            if ($id !== '' && !isset($isSaved[$id])) {
                $reason = is_array($entry) ? self::text($entry['reason'] ?? null) : '';
                $failures[$id] = mb_substr($reason, 0, self::MAX_REASON_LENGTH) ?: null;
            }
        }
        if (count($savedIds) + count($failures) > self::MAX_IDS) {
            throw new InvalidArgumentException('Receipt is larger than ' . self::MAX_IDS . ' requests');
        }

        [$scope, $scopeParams] = self::scope($labId, $facilityMap);
        $confirmed = 0;
        foreach (array_chunk($savedIds, self::CHUNK) as $chunk) {
            $this->db->rawQuery(
                "UPDATE `$table` SET `data_sync` = 1
                    WHERE `unique_id` IN (" . self::placeholders($chunk) . ") AND `data_sync` = " . self::IN_FLIGHT
                    . " AND $scope",
                [...$chunk, ...$scopeParams]
            );
            $confirmed += (int) $this->db->count;
            $this->db->rawQuery(
                "DELETE FROM `request_sync_failures`
                    WHERE `lab_id` = ? AND `test_type` = ? AND `unique_id` IN (" . self::placeholders($chunk) . ')',
                [$labId, $testType, ...$chunk]
            );
        }

        $now = DateUtility::getCurrentDateTime();
        $failedCount = 0;
        foreach (array_chunk(array_keys($failures), self::CHUNK) as $chunk) {
            $chunk = array_map('strval', $chunk);
            // Only what is still out with this lab. A request already confirmed (a
            // later receipt for it arrived first) or edited since, or another lab's,
            // is not this receipt's to fail, and recording it would flag a saved
            // request as needing attention.
            $inFlight = array_map('strval', array_column($this->db->rawQuery(
                "SELECT `unique_id` FROM `$table`
                    WHERE `unique_id` IN (" . self::placeholders($chunk) . ") AND `data_sync` = " . self::IN_FLIGHT
                    . " AND $scope",
                [...$chunk, ...$scopeParams]
            ), 'unique_id'));
            if ($inFlight === []) {
                continue;
            }
            // Back to pending, so the next pull sends it again.
            $this->db->rawQuery(
                "UPDATE `$table` SET `data_sync` = 0
                    WHERE `unique_id` IN (" . self::placeholders($inFlight) . ") AND `data_sync` = " . self::IN_FLIGHT
                    . " AND $scope",
                [...$inFlight, ...$scopeParams]
            );
            $failedCount += count($inFlight);
            foreach ($inFlight as $id) {
                $this->db->rawQuery(
                    "INSERT INTO `request_sync_failures`
                        (`lab_id`, `test_type`, `unique_id`, `reason`, `attempts`,
                            `first_failed_datetime`, `last_failed_datetime`)
                        VALUES (?, ?, ?, ?, 1, ?, ?)
                        ON DUPLICATE KEY UPDATE `attempts` = `attempts` + 1,
                            `reason` = VALUES(`reason`), `last_failed_datetime` = VALUES(`last_failed_datetime`)",
                    [$labId, $testType, $id, $failures[$id], $now, $now]
                );
            }
        }

        return ['confirmed' => $confirmed, 'failed' => $failedCount];
    }

    /**
     * The rows this lab pulls: its own, and those of facilities mapped to it.
     *
     * @return array{0: string, 1: list<int>}
     */
    private static function scope(int $labId, string|int|null $facilityMap): array
    {
        $facilityIds = [];
        foreach (explode(',', (string) $facilityMap) as $id) {
            if (ctype_digit(trim($id))) {
                $facilityIds[] = (int) trim($id);
            }
        }
        if ($facilityIds === []) {
            return ['`lab_id` = ?', [$labId]];
        }
        return [
            '(`lab_id` = ? OR `facility_id` IN (' . self::placeholders($facilityIds) . '))',
            [$labId, ...$facilityIds],
        ];
    }

    /**
     * @param list<mixed> $values
     * @return list<string>
     */
    private static function uniqueIds(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $id = self::text($value);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        return array_map('strval', array_keys($ids));
    }

    /** @param list<mixed> $values */
    private static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}

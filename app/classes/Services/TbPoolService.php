<?php

declare(strict_types=1);

namespace App\Services;

use App\Registries\ContainerRegistry;

/**
 * GeneXpert sputum pooling for TB batches.
 *
 * A lab mixes up to four samples into one cartridge. The cartridge's sample ID is the
 * pool code, "POOL-<batch code>-<n>", and form_tb.pool_code holds it on every sample
 * in the pool. TB sample codes start with the TB prefix, so a code that starts with
 * POOL- is a pool and nothing else, on the cartridge and to the importer.
 *
 * When the run comes back, InterfacingService finds the samples by that code: a
 * negative pool is negative for each of them, and anything else means each one is
 * tested on its own.
 *
 * A sample that was in a pool once is never pooled again. Its pool already said it
 * needs a test of its own.
 *
 * Once a worksheet with pools on it is printed, cartridges may be labelled from it, so
 * the pools are fixed from then on: they are not renumbered, renamed or deleted with
 * the batch. The time of that print is kept in batch_attributes.pools_printed_at.
 */
final class TbPoolService
{
    public const MAX_POOL_SIZE = 4;
    public const CODE_PREFIX = 'POOL-';

    public const RESULT_NEGATIVE = 'negative';
    public const RESULT_RETEST = 'retest';

    public function __construct(private ?DatabaseService $db = null)
    {
        $this->db ??= ContainerRegistry::get(DatabaseService::class);
    }

    public static function poolCode(string $batchCode, int $number): string
    {
        return self::prefix($batchCode) . $number;
    }

    /** What every pool code of the batch starts with. */
    public static function prefix(string $batchCode): string
    {
        return self::CODE_PREFIX . trim($batchCode) . '-';
    }

    public static function isPoolCode(string $code): bool
    {
        return stripos(trim($code), self::CODE_PREFIX) === 0;
    }

    /**
     * Whether a pool code is one of this batch's. The pool number has to follow the
     * prefix directly: batch "TB1" owns POOL-TB1-2, and batch "TB1-2" owns POOL-TB1-2-1.
     */
    public static function belongsToBatch(?string $poolCode, string $batchCode): bool
    {
        $prefix = self::prefix($batchCode);
        $poolCode = (string) $poolCode;
        return str_starts_with($poolCode, $prefix) && ctype_digit(substr($poolCode, strlen($prefix)));
    }

    /**
     * The SQL condition for "pool_code is one of this batch's", and its parameters.
     * The LIKE lets the pool_code index narrow the rows. The binary comparison keeps
     * out a batch whose code differs only in case, and the REGEXP one whose code
     * starts with this one's, as belongsToBatch() does.
     *
     * @return array{string, list<string|int>}
     */
    private static function batchCondition(string $batchCode): array
    {
        $prefix = self::prefix($batchCode);
        return [
            "pool_code LIKE ? AND BINARY LEFT(pool_code, ?) = BINARY ? AND SUBSTRING(pool_code, ?) REGEXP '^[0-9]+$'",
            [addcslashes($prefix, '\\%_') . '%', strlen($prefix), $prefix, strlen($prefix) + 1],
        ];
    }

    /**
     * The sample ids in a worksheet's order, from its label order (JSON as
     * batch_details.label_order holds it, or the posted list of items). Controls
     * are left out.
     *
     * @param string|iterable<mixed>|null $labelOrder
     * @return list<int>
     */
    public static function sampleIdsInOrder(string|iterable|null $labelOrder): array
    {
        if (is_string($labelOrder)) {
            $labelOrder = json_decode($labelOrder, true);
        }
        $ids = [];
        foreach (is_iterable($labelOrder) ? $labelOrder : [] as $item) {
            if (str_starts_with((string) $item, 's_')) {
                $ids[] = (int) substr((string) $item, 2);
            }
        }
        return $ids;
    }

    /**
     * Splits samples into pools in the order given, `$poolSize` to a pool. Samples in
     * `$runAlone` are left out. A last pool of one is no pool, so that sample is left
     * out too.
     *
     * @param list<int> $orderedIds
     * @param array<int, true> $runAlone
     * @return list<list<int>>
     */
    public static function group(array $orderedIds, array $runAlone, int $poolSize): array
    {
        $poolSize = min($poolSize, self::MAX_POOL_SIZE);
        if ($poolSize < 2) {
            return [];
        }

        $pools = [];
        $current = [];
        foreach ($orderedIds as $id) {
            if (isset($runAlone[$id])) {
                continue;
            }
            $current[] = $id;
            if (count($current) === $poolSize) {
                $pools[] = $current;
                $current = [];
            }
        }
        if (count($current) >= 2) {
            $pools[] = $current;
        }

        return $pools;
    }

    /**
     * The batch's samples: tb_id, pool_code, pool_result.
     *
     * @return list<array<string, mixed>>
     */
    private function batchRows(int $batchId): array
    {
        return $this->db->rawQuery(
            "SELECT tb_id, pool_code, pool_result FROM form_tb WHERE sample_batch_id = ?",
            [$batchId]
        ) ?: [];
    }

    /**
     * The batch's samples that are tested on their own whatever the pool size: those
     * that were in a pool before, here or in another batch.
     *
     * @param list<array<string, mixed>>|null $rows the batch's rows, when already read
     * @return array<int, true> tb_id => true
     */
    public function runAloneIds(int $batchId, string $batchCode, ?array $rows = null): array
    {
        $runAlone = [];
        foreach ($rows ?? $this->batchRows($batchId) as $row) {
            $earlierPool = !empty($row['pool_code']) && !self::belongsToBatch($row['pool_code'], $batchCode);
            if ($earlierPool || !empty($row['pool_result'])) {
                $runAlone[(int) $row['tb_id']] = true;
            }
        }
        return $runAlone;
    }

    /** @return array{pooled: int, resulted: int} */
    private function poolCounts(string $batchCode): array
    {
        [$condition, $params] = self::batchCondition($batchCode);
        $row = $this->db->rawQueryOne(
            "SELECT COUNT(*) AS pooled, COALESCE(SUM(pool_result IS NOT NULL), 0) AS resulted
                FROM form_tb WHERE $condition",
            $params
        );
        return ['pooled' => (int) ($row['pooled'] ?? 0), 'resulted' => (int) ($row['resulted'] ?? 0)];
    }

    public function hasResults(string $batchCode): bool
    {
        return $this->poolCounts($batchCode)['resulted'] > 0;
    }

    /**
     * Whether the batch's pools are fixed: a pool has a result, or a worksheet with
     * pools on it was printed and cartridges may already carry their codes.
     */
    public function isLocked(string $batchCode, ?string $poolsPrintedAt): bool
    {
        $counts = $this->poolCounts($batchCode);
        return $counts['resulted'] > 0 || (trim((string) $poolsPrintedAt) !== '' && $counts['pooled'] > 0);
    }

    /** Whether another batch already has this code, which its pool codes would share. */
    public function isBatchCodeInUse(string $batchCode, int $exceptBatchId): bool
    {
        return !empty($this->db->rawQueryOne(
            "SELECT batch_id FROM batch_details WHERE batch_code = ? AND batch_id <> ?",
            [trim($batchCode), $exceptBatchId]
        ));
    }

    /** @param array<string, mixed> $batch a batch_details row */
    public function isBatchLocked(array $batch): bool
    {
        return trim((string) ($batch['batch_code'] ?? '')) !== ''
            && $this->isLocked((string) $batch['batch_code'], self::poolsPrintedAt($batch));
    }

    /**
     * When a worksheet with this batch's pools on it was first printed, or null.
     *
     * @param array<string, mixed> $batch a batch_details row
     */
    public static function poolsPrintedAt(array $batch): ?string
    {
        $attributes = json_decode((string) ($batch['batch_attributes'] ?? ''), true);
        $printedAt = is_array($attributes) ? trim((string) ($attributes['pools_printed_at'] ?? '')) : '';
        return $printedAt === '' ? null : $printedAt;
    }

    /** Notes the first print of a worksheet with pools on it, which fixes the pools. */
    public function markPoolsPrinted(int $batchId, string $printedAt): void
    {
        $row = $this->db->rawQueryOne("SELECT batch_attributes FROM batch_details WHERE batch_id = ?", [$batchId]);
        $attributes = json_decode((string) ($row['batch_attributes'] ?? ''), true);
        $attributes = is_array($attributes) ? $attributes : [];
        if (!empty($attributes['pools_printed_at'])) {
            return;
        }
        $attributes['pools_printed_at'] = $printedAt;
        $this->db->rawQuery(
            "UPDATE batch_details SET batch_attributes = ? WHERE batch_id = ?",
            [json_encode($attributes), $batchId]
        );
    }

    /**
     * The pool each of the batch's samples is in now.
     *
     * @return array<int, string> tb_id => pool code, for pooled samples only
     */
    public function poolCodesOfBatch(int $batchId, string $batchCode): array
    {
        $codes = [];
        foreach ($this->batchRows($batchId) as $row) {
            if (self::belongsToBatch($row['pool_code'], $batchCode)) {
                $codes[(int) $row['tb_id']] = (string) $row['pool_code'];
            }
        }
        return $codes;
    }

    /**
     * Whether a lab assigned code cannot be used: analyzer sample IDs are matched
     * against it, so it must be unique among TB samples and never look like a pool
     * code.
     */
    public function isLabAssignedCodeTaken(string $code, int $ownTbId): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }
        if (self::isPoolCode($code)) {
            return true;
        }
        return !empty($this->db->rawQueryOne(
            "SELECT tb_id FROM form_tb WHERE lab_assigned_code = ? AND tb_id <> ?",
            [$code, $ownTbId]
        ));
    }

    /**
     * Pools the batch's samples again, in the order given, in one transaction.
     * Returns false and changes nothing when the pools are fixed (see isLocked()).
     *
     * @param list<int> $orderedIds tb_id of each sample, in worksheet order
     */
    public function assignPools(
        int $batchId,
        string $batchCode,
        ?string $poolsPrintedAt,
        array $orderedIds,
        int $poolSize
    ): bool {
        $this->db->beginTransaction();
        try {
            $pooled = $this->repool($batchId, $batchCode, $poolsPrintedAt, $orderedIds, $poolSize);
            $this->db->commitTransaction();
            return $pooled;
        } catch (\Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }
    }

    /** @param list<int> $orderedIds */
    private function repool(
        int $batchId,
        string $batchCode,
        ?string $poolsPrintedAt,
        array $orderedIds,
        int $poolSize
    ): bool {
        if ($this->isLocked($batchCode, $poolsPrintedAt)) {
            return false;
        }

        $this->clearPools($batchCode);

        $rows = $this->batchRows($batchId);
        $inBatch = array_flip(array_map('intval', array_column($rows, 'tb_id')));
        $orderedIds = array_values(array_filter(
            array_map('intval', $orderedIds),
            static fn(int $id): bool => isset($inBatch[$id])
        ));

        $runAlone = $this->runAloneIds($batchId, $batchCode, $rows);
        foreach (self::group($orderedIds, $runAlone, $poolSize) as $index => $members) {
            $this->db->rawQuery(
                "UPDATE form_tb SET pool_code = ? WHERE tb_id IN (" . $this->db->inIntList($members) . ")",
                [self::poolCode($batchCode, $index + 1)]
            );
        }

        return true;
    }

    /**
     * Takes samples that have left the batch out of its pools. Call only while the
     * pools are not fixed: a cartridge that may have been made keeps its samples.
     */
    public function releaseRemovedSamples(int $batchId, string $batchCode): void
    {
        [$condition, $params] = self::batchCondition($batchCode);
        $this->db->rawQuery(
            "UPDATE form_tb SET pool_code = NULL
                WHERE $condition
                AND pool_result IS NULL
                AND (sample_batch_id IS NULL OR sample_batch_id <> ?)",
            [...$params, $batchId]
        );

        // A pool left with one sample is no pool: that sample runs on its own.
        $this->db->rawQuery(
            "UPDATE form_tb SET pool_code = NULL
                WHERE pool_result IS NULL AND pool_code IN (
                    SELECT pool_code FROM (
                        SELECT pool_code FROM form_tb
                            WHERE $condition AND pool_result IS NULL
                            GROUP BY pool_code HAVING COUNT(*) < 2
                    ) AS lone_pools
                )",
            $params
        );
    }

    /** Removes the batch's pools that have no result yet. */
    public function clearPools(string $batchCode): void
    {
        [$condition, $params] = self::batchCondition($batchCode);
        $this->db->rawQuery("UPDATE form_tb SET pool_code = NULL WHERE $condition AND pool_result IS NULL", $params);
    }

    /**
     * The batch's worksheet rows in label order: each control, each pool once under
     * its pool code, and every other sample under its own code. Samples of the batch
     * missing from the label order follow at the end. Empty when the batch has no
     * pools, since then the worksheet lists its samples one by one.
     *
     * @param string|iterable<mixed>|null $labelOrder batch_details.label_order
     * @param string $scopeWhere an extra SQL condition on form_tb, such as the
     *                           session's lab scope; empty for none
     * @return list<array{kind: 'pool'|'sample'|'control', code: string, samples: list<array<string, mixed>>}>
     */
    public function cartridgesOfBatch(
        int $batchId,
        string $batchCode,
        string|iterable|null $labelOrder,
        string $scopeWhere = ''
    ): array {
        [$condition, $params] = self::batchCondition($batchCode);
        $scope = trim($scopeWhere) === '' ? '' : " AND ($scopeWhere)";
        $rows = $this->db->rawQuery(
            "SELECT tb_id, sample_code, remote_sample_code, lab_assigned_code, pool_code, pool_result
                FROM form_tb WHERE (sample_batch_id = ? OR ($condition))$scope ORDER BY tb_id",
            [$batchId, ...$params]
        ) ?: [];
        $byId = [];
        $hasPools = false;
        foreach ($rows as $row) {
            $byId[(int) $row['tb_id']] = $row;
            $hasPools = $hasPools || self::belongsToBatch($row['pool_code'], $batchCode);
        }
        if (!$hasPools) {
            return [];
        }

        if (is_string($labelOrder)) {
            $labelOrder = json_decode($labelOrder, true);
        }
        $items = [];
        foreach (is_iterable($labelOrder) ? $labelOrder : [] as $item) {
            $item = (string) $item;
            if (!str_starts_with($item, 's_')) {
                $items[] = ['control', $item];
            } elseif (isset($byId[(int) substr($item, 2)])) {
                $items[] = ['sample', (int) substr($item, 2)];
            }
        }
        $listed = array_column(array_filter($items, static fn(array $i): bool => $i[0] === 'sample'), 1);
        foreach (array_diff(array_keys($byId), $listed) as $id) {
            $items[] = ['sample', $id];
        }

        $cartridges = [];
        $poolIndex = [];
        foreach ($items as [$kind, $value]) {
            if ($kind === 'control') {
                $cartridges[] = ['kind' => 'control', 'code' => $value, 'samples' => []];
                continue;
            }
            $row = $byId[$value];
            $poolCode = (string) $row['pool_code'];
            if (self::belongsToBatch($poolCode, $batchCode)) {
                if (!isset($poolIndex[$poolCode])) {
                    $poolIndex[$poolCode] = count($cartridges);
                    $cartridges[] = ['kind' => 'pool', 'code' => $poolCode, 'samples' => []];
                }
                $cartridges[$poolIndex[$poolCode]]['samples'][] = $row;
                continue;
            }
            $cartridges[] = [
                'kind' => 'sample',
                'code' => (string) ($row['sample_code'] ?: $row['remote_sample_code']),
                'samples' => [$row],
            ];
        }
        return $cartridges;
    }

    /**
     * The samples a pool code names, whole rows. Empty when the code is not a pool
     * code.
     *
     * @param int|null $labId only samples of this lab, for callers that must not reach
     *                        another lab's samples
     * @return list<array<string, mixed>>
     */
    public function samplesInPool(string $poolCode, ?int $labId = null): array
    {
        $poolCode = trim($poolCode);
        if ($poolCode === '') {
            return [];
        }

        $params = [$poolCode];
        $labCondition = '';
        if ($labId !== null) {
            $labCondition = ' AND lab_id = ?';
            $params[] = $labId;
        }
        return $this->db->rawQuery(
            "SELECT * FROM form_tb WHERE pool_code = ?$labCondition ORDER BY tb_id",
            $params
        ) ?: [];
    }

    /** The batch a pool code was made in, or null when no batch has that code now. */
    public function batchIdOfPool(string $poolCode): ?int
    {
        if (preg_match('/^' . preg_quote(self::CODE_PREFIX, '/') . '(.+)-\d+$/i', trim($poolCode), $match) !== 1) {
            return null;
        }
        $row = $this->db->rawQueryOne("SELECT batch_id FROM batch_details WHERE batch_code = ?", [$match[1]]);
        return isset($row['batch_id']) ? (int) $row['batch_id'] : null;
    }

    /**
     * The pool results already recorded on these samples.
     *
     * @param list<int> $tbIds
     * @return list<string>
     */
    public function recordedResults(array $tbIds): array
    {
        if ($tbIds === []) {
            return [];
        }
        $rows = $this->db->rawQuery(
            "SELECT DISTINCT pool_result FROM form_tb
                WHERE tb_id IN (" . $this->db->inIntList($tbIds) . ") AND pool_result IS NOT NULL"
        );
        return array_values(array_column($rows, 'pool_result'));
    }

    /**
     * Records what a pool read on each of its samples. The first result recorded
     * stands. A run sent again changes nothing.
     *
     * A pool that is not negative also takes the samples in `$releaseIds` out of the
     * pool's batch, so the lab can put them in a new one to be tested on their own. A
     * sample that has moved to another batch since stays there.
     *
     * @param list<int> $tbIds every sample of the pool
     * @param int|null $poolBatchId the batch the pool was made in
     * @param list<int> $releaseIds the samples still waiting to be tested
     * @return bool whether any sample changed
     */
    public function recordResult(array $tbIds, string $result, ?int $poolBatchId = null, array $releaseIds = []): bool
    {
        if ($tbIds === []) {
            return false;
        }

        $release = $result === self::RESULT_RETEST && $poolBatchId !== null && $releaseIds !== [];
        $this->db->rawQuery(
            "UPDATE form_tb SET pool_result = ?"
                . ($release
                    ? ", sample_batch_id = IF(sample_batch_id = ? AND tb_id IN (" . $this->db->inIntList($releaseIds)
                        . "), NULL, sample_batch_id)"
                    : "")
                . " WHERE tb_id IN (" . $this->db->inIntList($tbIds) . ") AND pool_result IS NULL",
            $release ? [$result, $poolBatchId] : [$result]
        );
        return $this->db->count > 0;
    }
}

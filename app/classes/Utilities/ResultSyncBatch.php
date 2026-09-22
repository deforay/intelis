<?php

declare(strict_types=1);

namespace App\Utilities;

use Closure;
use Countable;
use Generator;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Snapshot eligible IDs, then load result rows only as the sender needs them.
 * Keeps IDs in memory, not the full backlog of results and child test data.
 *
 * @phpstan-type Row array<string, mixed>
 */
final class ResultSyncBatch implements Countable
{
    /** @var list<int|string> */
    private array $ids;

    /** @var Closure(string, list<int|string>): list<Row> */
    private Closure $query;

    /** @var null|Closure(list<int|string>): void */
    private ?Closure $beforeFetch;

    /**
     * SQL and column names come from the sender, never request input.
     * The selection must not already contain ORDER BY or LIMIT.
     *
     * $beforeFetch runs with each slice of IDs just before chunks() reads their rows,
     * so the sender can mark them in flight first; preview() never calls it.
     *
     * @param callable(string, list<int|string>): list<Row> $query
     * @param null|callable(list<int|string>): void $beforeFetch
     */
    public function __construct(
        callable $query,
        private readonly string $selection,
        private readonly string $idColumn,
        bool $keyByUniqueId = false,
        ?callable $beforeFetch = null
    ) {
        $this->beforeFetch = $beforeFetch === null ? null : Closure::fromCallable($beforeFetch);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/D', $idColumn)) {
            throw new InvalidArgumentException('Expected a qualified result ID column.');
        }
        $this->query = Closure::fromCallable($query);
        $idField = explode('.', $idColumn)[1];
        $columns = "result_ids.$idField" . ($keyByUniqueId ? ", result_ids.unique_id" : "");
        $rows = ($this->query)(
            "SELECT $columns FROM ($selection) AS result_ids ORDER BY result_ids.$idField",
            []
        );
        // The old nested payload array kept the last row for each unique_id.
        // Resolve collisions here so they cannot land in separate outgoing batches.
        if ($keyByUniqueId) {
            $rows = array_column($rows, null, 'unique_id');
        }
        $this->ids = [];
        foreach ($rows as $row) {
            $id = $row[$idField] ?? null;
            if (!is_int($id) && !is_string($id)) {
                throw new UnexpectedValueException('Result selection returned an invalid primary key.');
            }
            $this->ids[] = $id;
        }
    }

    public function count(): int
    {
        return count($this->ids);
    }

    /** @return list<Row> */
    public function preview(): array
    {
        return $this->fetch(array_slice($this->ids, 0, 10));
    }

    /** @var array{readMs: float, prepareMs: float} */
    private array $timings = ['readMs' => 0.0, 'prepareMs' => 0.0];

    /** @return array{readMs: float, prepareMs: float} */
    public function timings(): array
    {
        return $this->timings;
    }

    /**
     * A callable size is read after each yield, so response hints affect the next batch.
     * The existing per-row callback remains supported for other callers.
     *
     * @param int|callable(): int $size
     * @param null|callable(Row): mixed $childTests
     * @param null|callable(array<int, Row>): array<array-key, Row> $prepareBatch
     * @return Generator<int, array<array-key, Row>>
     */
    public function chunks(
        int|callable $size,
        ?callable $childTests = null,
        ?callable $prepareBatch = null
    ): Generator {
        $offset = $index = 0;
        do {
            $limit = is_int($size) ? $size : $size();
            if ($limit < 1) {
                throw new InvalidArgumentException('Chunk size must be positive.');
            }
            $start = hrtime(true);
            $rows = [];
            // Offset into the fixed ID snapshot, never into a shrinking SQL selection.
            while (count($rows) < $limit && $offset < count($this->ids)) {
                $ids = array_slice($this->ids, $offset, $limit - count($rows));
                $offset += count($ids);
                if ($this->beforeFetch !== null) {
                    ($this->beforeFetch)($ids);
                }
                foreach ($this->fetch($ids) as $row) {
                    $rows[$index++] = $row;
                }
            }
            $readMs = (hrtime(true) - $start) / 1e6;
            if ($rows === []) {
                break;
            }
            $start = hrtime(true);
            if ($prepareBatch !== null) {
                $rows = $prepareBatch($rows);
            } elseif ($childTests !== null) {
                $rows = \iter\toArrayWithKeys(\iter\map(
                    static fn(array $row): array => [
                        'form_data' => $row,
                        'data_from_tests' => $childTests($row),
                    ],
                    \iter\reindex(static fn(array $row) => $row['unique_id'], $rows)
                ));
            }
            $this->timings = ['readMs' => $readMs, 'prepareMs' => (hrtime(true) - $start) / 1e6];
            yield $rows;
        } while ($offset < count($this->ids));
    }

    /**
     * Preserve the v2 child-data shapes when switching from scalar to bulk reads.
     * COVID-19 includes a parent-ID wrapper; generic and TB send lists of child rows.
     *
     * @param array<int, Row> $rows
     * @param array<array-key, array<array-key, Row>> $children
     * @return array<array-key, Row>
     */
    public static function nestedPayload(
        array $rows,
        array $children,
        string $idField,
        bool $wrapParentId = false
    ): array {
        $payload = [];
        foreach ($rows as $row) {
            $id = $row[$idField];
            if (!is_int($id) && !is_string($id)) {
                throw new UnexpectedValueException('Result row has an invalid parent ID.');
            }
            $tests = $children[$id] ?? [];
            $key = $row['unique_id'];
            if ($key !== null && !is_int($key) && !is_string($key)) {
                throw new UnexpectedValueException('Result row has an invalid unique ID.');
            }
            $payload[$key ?? ''] = [
                'form_data' => $row,
                'data_from_tests' => $wrapParentId
                    ? ($tests === [] ? [] : [$id => $tests])
                    : array_values($tests),
            ];
        }
        return $payload;
    }

    /**
     * @param list<int|string> $ids
     * @return list<Row>
     */
    private function fetch(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $rows = ($this->query)(
            "$this->selection AND $this->idColumn IN ($placeholders) ORDER BY $this->idColumn",
            $ids
        );
        // Deduplication preserves a key's first position, even when its last row wins.
        $idField = explode('.', $this->idColumn)[1];
        $byId = array_column($rows, null, $idField);
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }
}

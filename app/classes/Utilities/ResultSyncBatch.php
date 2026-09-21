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

    /**
     * SQL and column names come from the sender, never request input.
     * The selection must not already contain ORDER BY or LIMIT.
     *
     * @param callable(string, list<int|string>): list<Row> $query
     */
    public function __construct(
        callable $query,
        private readonly string $selection,
        private readonly string $idColumn,
        bool $keyByUniqueId = false
    ) {
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

    /**
     * @param null|callable(Row): mixed $childTests
     * @return \Iterator<array<array-key, Row>>
     */
    public function chunks(int $size, ?callable $childTests = null): \Iterator
    {
        $rows = $this->rows($size);
        if ($childTests !== null) {
            $rows = \iter\map(
                static fn(array $row): array => [
                    'form_data' => $row,
                    'data_from_tests' => $childTests($row),
                ],
                \iter\reindex(static fn(array $row) => $row['unique_id'], $rows)
            );
        }
        // Preserve the flat numeric keys and nested unique_id keys used on the wire.
        return \iter\chunk($rows, $size, true);
    }

    /** @return Generator<int, Row> */
    private function rows(int $size): Generator
    {
        $index = 0;
        // Never OFFSET over data_sync: acknowledgments remove rows from that selection.
        // A fixed ID snapshot also leaves newly arriving results for the next run.
        foreach (\iter\chunk($this->ids, $size) as $ids) {
            foreach ($this->fetch(array_values($ids)) as $row) {
                yield $index++ => $row;
            }
        }
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

<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\ResultSyncBatch;
use PDO;
use PHPUnit\Framework\TestCase;

final class ResultSyncBatchTest extends TestCase
{
    private array $records = [];
    private array $fetches = [];

    private function selection(int $count): ResultSyncBatch
    {
        for ($id = 1; $id <= $count; $id++) {
            $this->records[$id] = ['id' => $id, 'unique_id' => "uuid-$id", 'sample_code' => "S$id"];
        }
        return new ResultSyncBatch(function (string $sql, array $ids): array {
            if ($ids === []) {
                self::assertSame(
                    'SELECT result_ids.id FROM (SELECT r.* FROM results r WHERE r.data_sync = 0)'
                    . ' AS result_ids ORDER BY result_ids.id',
                    $sql
                );
                return array_map(static fn(int $id): array => ['id' => $id], array_keys($this->records));
            }
            $this->fetches[] = $ids;
            self::assertStringContainsString('AND r.id IN (' . implode(', ', array_fill(0, count($ids), '?')), $sql);
            self::assertStringEndsWith('ORDER BY r.id', $sql);
            return array_values(array_intersect_key($this->records, array_flip($ids)));
        }, 'SELECT r.* FROM results r WHERE r.data_sync = 0', 'r.id');
    }

    public function testEachSliceIsMarkedBeforeItsRowsAreRead(): void
    {
        $events = [];
        $selection = new ResultSyncBatch(
            function (string $sql, array $ids) use (&$events): array {
                if ($ids === []) {
                    return [['id' => 1], ['id' => 2], ['id' => 3]];
                }
                $events[] = ['read', $ids];
                return array_map(static fn(int $id): array => ['id' => $id], $ids);
            },
            'SELECT r.* FROM results r WHERE r.data_sync = 0',
            'r.id',
            beforeFetch: static function (array $ids) use (&$events): void {
                $events[] = ['mark', $ids];
            }
        );

        $selection->preview();
        self::assertSame([['read', [1, 2, 3]]], $events, 'a preview marks nothing');

        $events = [];
        iterator_to_array($selection->chunks(2));

        self::assertSame(
            [['mark', [1, 2]], ['read', [1, 2]], ['mark', [3]], ['read', [3]]],
            $events,
            'a change made after the mark resets data_sync, so the mark has to come first'
        );
    }

    public function testAcknowledgmentsDoNotSkipRowsOrRetryFailedRowsWithinTheRun(): void
    {
        $selection = $this->selection(5);
        self::assertCount(5, $selection);
        $chunks = $selection->chunks(2);
        self::assertSame([], $this->fetches);
        $sent = [];
        foreach ($chunks as $index => $chunk) {
            $sent[] = array_column($chunk, 'id');
            if ($index === 0) {
                // One acknowledgment removes a row; the failed row remains eligible.
                unset($this->records[1]);
                $this->records[6] = ['id' => 6, 'unique_id' => 'uuid-6', 'sample_code' => 'S6'];
            }
        }
        self::assertSame([[1, 2], [3, 4], [5]], $sent);
        self::assertSame([[1, 2], [3, 4], [5]], $this->fetches);
    }

    public function testFlatPayloadKeysMatchThePreviousArrayChunkFormat(): void
    {
        $selection = $this->selection(5);
        self::assertSame(
            array_chunk(array_values($this->records), 2, true),
            iterator_to_array($selection->chunks(2))
        );
    }

    public function testSizeChangesApplyToTheNextFetchWithoutSkippingOrRepeatingIds(): void
    {
        $selection = $this->selection(8);
        $size = 3;
        $chunks = $selection->chunks(static function () use (&$size): int {
            return $size;
        });
        $sent = [];
        foreach ($chunks as $index => $chunk) {
            $sent[] = array_column($chunk, 'id');
            $size = $index === 0 ? 1 : 4;
        }
        self::assertSame([[1, 2, 3], [4], [5, 6, 7, 8]], $sent);
        self::assertSame($sent, $this->fetches);
    }

    public function testBulkPreparationRunsOncePerConsumedBatchAndPreservesLegacyLists(): void
    {
        $selection = $this->selection(5);
        $calls = [];
        $chunks = $selection->chunks(2, prepareBatch: static function (array $rows) use (&$calls): array {
            $calls[] = array_column($rows, 'id');
            $children = [];
            foreach ($rows as $row) {
                $children[$row['id']][99] = ['test_id' => 99, 'result' => 'negative'];
            }
            return ResultSyncBatch::nestedPayload($rows, $children, 'id');
        });
        self::assertSame([], $calls);
        $expected = [];
        foreach ($this->records as $row) {
            $expected[$row['unique_id']] = [
                'form_data' => $row,
                'data_from_tests' => [['test_id' => 99, 'result' => 'negative']],
            ];
        }
        self::assertSame(array_slice($expected, 0, 2, true), $chunks->current());
        self::assertSame([[1, 2]], $calls);
        self::assertSame(array_chunk($expected, 2, true), iterator_to_array($chunks));
        self::assertSame([[1, 2], [3, 4], [5]], $calls);
        self::assertGreaterThanOrEqual(0, $selection->timings()['readMs']);
        self::assertGreaterThanOrEqual(0, $selection->timings()['prepareMs']);
    }

    public function testCovidChildPayloadKeepsItsParentWrapperAndEmptyChildrenStayEmpty(): void
    {
        $row = ['covid19_id' => 42, 'unique_id' => 'uuid-42'];
        $tests = [8 => ['test_id' => 8, 'result' => 'negative']];
        self::assertSame(['uuid-42' => [
            'form_data' => $row,
            'data_from_tests' => [42 => $tests],
        ]], ResultSyncBatch::nestedPayload([$row], [42 => $tests], 'covid19_id', wrapParentId: true));
        foreach ([false, true] as $wrap) {
            self::assertSame(['uuid-42' => [
                'form_data' => $row,
                'data_from_tests' => [],
            ]], ResultSyncBatch::nestedPayload([$row], [], 'covid19_id', $wrap));
        }
    }

    public function testNestedPayloadsAreLoadedOnlyForTheCurrentChunkAndKeepUniqueIds(): void
    {
        $selection = $this->selection(5);
        $loaded = [];
        $chunks = $selection->chunks(2, static function (array $row) use (&$loaded): array {
            $loaded[] = $row['id'];
            return [['result' => "result-{$row['id']}"]];
        });
        self::assertSame([], $loaded);
        $expected = [];
        foreach ($this->records as $row) {
            $expected[$row['unique_id']] = [
                'form_data' => $row,
                'data_from_tests' => [['result' => "result-{$row['id']}"]],
            ];
        }
        self::assertSame(array_slice($expected, 0, 2, true), $chunks->current());
        self::assertSame([1, 2], $loaded);
        self::assertSame([[1, 2]], $this->fetches);
        self::assertSame(array_chunk($expected, 2, true), iterator_to_array($chunks));
    }

    public function testDryRunPreviewDoesNotReadTheBacklogOrLoadChildTests(): void
    {
        $selection = $this->selection(25);
        $selection->chunks(2, static function (): never {
            self::fail('Dry run must not expand child tests.');
        });
        self::assertCount(10, $selection->preview());
        self::assertCount(25, $selection);
        self::assertSame([range(1, 10)], $this->fetches);
    }

    public function testEmptySelectionDoesNotFetchRows(): void
    {
        $selection = $this->selection(0);
        self::assertCount(0, $selection);
        self::assertSame([], $selection->preview());
        self::assertSame([], iterator_to_array($selection->chunks(2)));
        self::assertSame([], $this->fetches);
    }

    public function testRowsThatBecomeIneligibleBeforeFetchingAreOmitted(): void
    {
        $selection = $this->selection(5);
        unset($this->records[3], $this->records[4]);
        self::assertSame([[1, 2], [5]], array_map(
            static fn(array $chunk): array => array_column($chunk, 'id'),
            iterator_to_array($selection->chunks(2))
        ));
    }

    public function testSqlSelectionKeepsFiltersAndJoinsWhileAcknowledgmentsChangeEligibility(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('SQLite is required for this SQL execution test.');
        }
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE results ('
            . 'id INTEGER PRIMARY KEY, data_sync INTEGER, sample_code TEXT, approver INTEGER)');
        $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $db->exec("INSERT INTO users VALUES (1, 'Approver')");
        $db->exec("INSERT INTO results VALUES (1, 0, 'A', 1), (2, 0, 'B', 1), (3, 0, 'C', 1), (4, 1, 'D', 1)");
        $selection = new ResultSyncBatch(static function (string $sql, array $params) use ($db): array {
            $statement = $db->prepare($sql);
            $statement->execute($params);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }, 'SELECT r.*, a.name AS approved_by_name FROM results r LEFT JOIN users a ON a.id = r.approver'
            . " WHERE r.data_sync = 0 AND IFNULL(r.sample_code, '') != ''", 'r.id');
        self::assertCount(3, $selection);
        $sent = [];
        foreach ($selection->chunks(2) as $chunk) {
            foreach ($chunk as $row) {
                $sent[] = $row['sample_code'];
                self::assertSame('Approver', $row['approved_by_name']);
                $statement = $db->prepare('UPDATE results SET data_sync = 1 WHERE id = ?');
                $statement->execute([$row['id']]);
            }
        }
        self::assertSame(['A', 'B', 'C'], $sent);
    }

    public function testNestedDuplicateKeysKeepTheLastRowAcrossBatchBoundaries(): void
    {
        $rows = [
            ['id' => 1, 'unique_id' => 'same'],
            ['id' => 2, 'unique_id' => 'other'],
            ['id' => 3, 'unique_id' => 'same'],
            ['id' => 4, 'unique_id' => null],
            ['id' => 5, 'unique_id' => null],
        ];
        $selection = new ResultSyncBatch(static function (string $sql, array $ids) use ($rows): array {
            return $ids === [] ? $rows : array_values(array_filter(
                $rows,
                static fn(array $row): bool => in_array($row['id'], $ids, true)
            ));
        }, 'SELECT r.* FROM results r WHERE r.data_sync = 0', 'r.id', keyByUniqueId: true);
        $expected = [];
        foreach ($rows as $row) {
            $expected[$row['unique_id']] = ['form_data' => $row, 'data_from_tests' => []];
        }
        self::assertCount(3, $selection);
        self::assertSame(
            array_chunk($expected, 2, true),
            iterator_to_array($selection->chunks(2, static fn(): array => []))
        );
    }
}

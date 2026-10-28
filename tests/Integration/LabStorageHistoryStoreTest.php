<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\STS\LabMetadataService;
use App\Services\StorageService;
use App\Utilities\FileCacheUtility;
use App\Utilities\MemoUtility;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * The STS storing the sample storage history labs send it.
 *
 * Every lab numbers its history from 1, and the STS stored each row under that number,
 * so one lab's row 5 overwrote another's. From 5.7.88 a row is matched on the lab (as
 * its token says) and the lab's own id for it. The migration hands the rows the STS
 * already holds to their labs through their freezers, and has every lab send its
 * history again so what was overwritten comes back.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class LabStorageHistoryStoreTest extends TestCase
{
    private const LAB_A = 11;
    private const LAB_B = 12;

    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(string $instanceType = 'remoteuser'): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $database = TestRun::database('intelis_storage_history_test');
        // getTableFieldsAsArray() reads columns from SYSTEM_CONFIG's database.
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database]]);
        }
        $db = LegacyAppHarness::boot($database, [
            'system_config', 'global_config', 's_vlsm_instance', 'facility_details', 'lab_storage',
            'lab_storage_history',
        ]);
        $this->booted = true;
        LegacyAppHarness::addMigrationColumns('5.7.87', ['s_vlsm_instance']);
        // Freezers belong to facilities; which facility is not what is tested here.
        $db->rawQuery('SET FOREIGN_KEY_CHECKS = 0');
        $db->insert('system_config', ['name' => 'sc_user_type', 'value' => $instanceType]);
        $db->insert('lab_storage', ['storage_id' => 'freezer-a', 'storage_code' => 'FA', 'lab_id' => self::LAB_A]);
        $db->insert('lab_storage', ['storage_id' => 'freezer-b', 'storage_code' => 'FB', 'lab_id' => self::LAB_B]);
    }

    /** Run sys/migrations/5.7.88.sql as written. */
    private static function migrate(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/sys/migrations/5.7.88.sql');
        $sql = (string) preg_replace('/^--.*$/m', '', $sql);
        $statements = array_filter(array_map('trim', explode(";\n", $sql)));
        if (count($statements) < 5) {
            throw new RuntimeException('sys/migrations/5.7.88.sql is not what this test runs');
        }
        foreach ($statements as $statement) {
            LegacyAppHarness::db()->rawQuery(rtrim($statement, ';'));
        }
        // Column lists are cached; the ones read before the migration lack its columns.
        MemoUtility::clear();
        ContainerRegistry::get(FileCacheUtility::class)->clear();
    }

    private static function row(int $historyId, string $freezer, string $sample, array $values = []): array
    {
        return $values + [
            'history_id' => $historyId, 'test_type' => 'vl', 'sample_unique_id' => $sample, 'volume' => '1.00',
            'freezer_id' => $freezer, 'rack' => 1, 'box' => 1, 'position' => $historyId,
            'sample_status' => 'Stored', 'updated_datetime' => '2026-09-01 10:00:00', 'updated_by' => 'u1',
        ];
    }

    private static function send(int $labId, array $rows): void
    {
        ContainerRegistry::get(LabMetadataService::class)->storeTables([
            'primaryKey' => [1 => 'history_id'],
            'table' => [1 => 'lab_storage_history'],
            'data' => [1 => $rows],
        ], $labId);
    }

    /**
     * What the STS holds, as lab => lab's id => sample; rows no lab has claimed under
     * 'unclaimed', by this server's id.
     */
    private static function held(): array
    {
        $held = [];
        foreach (
            LegacyAppHarness::db()->rawQuery(
                'SELECT history_id, source_lab_id, lab_history_id, sample_unique_id
                   FROM lab_storage_history ORDER BY history_id'
            ) as $row
        ) {
            if ($row['source_lab_id'] === null) {
                $held['unclaimed'][(int) $row['history_id']] = $row['sample_unique_id'];
            } else {
                $held[(int) $row['source_lab_id']][(int) $row['lab_history_id']] = $row['sample_unique_id'];
            }
        }
        return $held;
    }

    #[RunInSeparateProcess]
    public function testTwoLabsRowsWithTheSameIdAreBothKept(): void
    {
        $this->boot();
        self::migrate();

        self::send(self::LAB_A, [self::row(5, 'freezer-a', 'sample-of-a')]);
        self::send(self::LAB_B, [self::row(5, 'freezer-b', 'sample-of-b')]);

        $this->assertSame(
            [self::LAB_A => [5 => 'sample-of-a'], self::LAB_B => [5 => 'sample-of-b']],
            self::held()
        );
    }

    #[RunInSeparateProcess]
    public function testARowSentAgainIsUpdatedInPlace(): void
    {
        $this->boot();
        self::migrate();

        self::send(self::LAB_A, [self::row(5, 'freezer-a', 'sample-of-a')]);
        self::send(self::LAB_A, [self::row(5, 'freezer-a', 'sample-of-a', ['sample_status' => 'Removed'])]);

        $rows = LegacyAppHarness::db()->rawQuery('SELECT sample_status FROM lab_storage_history');
        $this->assertSame([['sample_status' => 'Removed']], $rows);
    }

    #[RunInSeparateProcess]
    public function testTheLabIsTheOneTheTokenNamesNotTheOneTheRowNames(): void
    {
        $this->boot();
        self::migrate();

        self::send(self::LAB_A, [self::row(5, 'freezer-a', 'sample-of-a', ['source_lab_id' => self::LAB_B])]);

        $this->assertSame([self::LAB_A => [5 => 'sample-of-a']], self::held());
    }

    #[RunInSeparateProcess]
    public function testTheRowKeepsTheTimeTheLabRecorded(): void
    {
        $this->boot();
        self::migrate();

        self::send(self::LAB_A, [self::row(5, 'freezer-a', 's', ['updated_datetime' => '2026-08-15 09:30:00'])]);

        $this->assertSame(
            '2026-08-15 09:30:00',
            LegacyAppHarness::db()->rawQueryOne('SELECT updated_datetime FROM lab_storage_history')['updated_datetime']
        );
    }

    #[RunInSeparateProcess]
    public function testWithoutALabNothingIsStored(): void
    {
        $this->boot();
        self::migrate();

        ContainerRegistry::get(LabMetadataService::class)->storeTables([
            'primaryKey' => [1 => 'history_id'],
            'table' => [1 => 'lab_storage_history'],
            'data' => [1 => [self::row(5, 'freezer-a', 'sample-of-a')]],
        ]);

        $this->assertSame([], self::held());
    }

    #[RunInSeparateProcess]
    public function testTheUpgradeLeavesTheRowsTheStsHoldsUnclaimed(): void
    {
        $this->boot();
        LegacyAppHarness::db()->insert('lab_storage_history', self::row(4, 'freezer-a', 'sample-of-a'));

        self::migrate();

        $this->assertSame(['unclaimed' => [4 => 'sample-of-a']], self::held());
    }

    #[RunInSeparateProcess]
    public function testALabClaimsTheRowsThatAreStillItsOwnWhenItSendsThemAgain(): void
    {
        $this->boot();
        $db = LegacyAppHarness::db();
        // As stored before 5.7.88, under the ids the labs sent: lab B's row 3 overwrote
        // lab A's row 3; row 4 is still lab A's.
        $db->insert('lab_storage_history', self::row(3, 'freezer-b', 'sample-of-b'));
        $db->insert('lab_storage_history', self::row(4, 'freezer-a', 'sample-of-a'));
        self::migrate();

        self::send(self::LAB_A, [self::row(3, 'freezer-a', 'lost-of-a'), self::row(4, 'freezer-a', 'sample-of-a')]);
        self::send(self::LAB_B, [self::row(3, 'freezer-b', 'sample-of-b')]);

        $this->assertSame(
            [self::LAB_B => [3 => 'sample-of-b'], self::LAB_A => [4 => 'sample-of-a', 3 => 'lost-of-a']],
            self::held()
        );
        $this->assertSame(
            4,
            (int) $db->rawQueryOne("SELECT history_id FROM lab_storage_history WHERE sample_unique_id = 'sample-of-a'")
            ['history_id'],
            'a claimed row keeps its place'
        );
    }

    #[RunInSeparateProcess]
    public function testARowWrittenOnTheStsIsNotClaimedByALabRowWithTheSameId(): void
    {
        $this->boot();
        // Written on the STS: the same sample and freezer, by someone at the STS.
        LegacyAppHarness::db()->insert('lab_storage_history', self::row(7, 'freezer-a', 'sample-of-a', [
            'sample_status' => 'Removed', 'updated_by' => 'sts-user',
        ]));
        self::migrate();

        self::send(self::LAB_A, [self::row(7, 'freezer-a', 'sample-of-a')]);

        $held = self::held();
        $this->assertSame([7 => 'sample-of-a'], $held['unclaimed'] ?? null);
        $this->assertSame([7 => 'sample-of-a'], $held[self::LAB_A] ?? null);
    }

    #[RunInSeparateProcess]
    public function testARowThatDiffersInAnyStoredValueIsNotClaimed(): void
    {
        $this->boot();
        // The same sample in the same place, but a different volume and a comment.
        LegacyAppHarness::db()->insert('lab_storage_history', self::row(7, 'freezer-a', 'sample-of-a', [
            'volume' => '2.50', 'comments' => 'split at the STS',
        ]));
        self::migrate();

        self::send(self::LAB_A, [self::row(7, 'freezer-a', 'sample-of-a')]);

        $this->assertSame(
            ['2.50', 'split at the STS'],
            array_values(LegacyAppHarness::db()->rawQueryOne(
                'SELECT volume, comments FROM lab_storage_history WHERE history_id = 7'
            ))
        );
        $this->assertSame([7 => 'sample-of-a'], self::held()[self::LAB_A] ?? null);
    }

    #[RunInSeparateProcess]
    public function testSendingTheHistoryAgainTwiceClaimsNothingTwice(): void
    {
        $this->boot();
        LegacyAppHarness::db()->insert('lab_storage_history', self::row(4, 'freezer-a', 'sample-of-a'));
        LegacyAppHarness::db()->insert('lab_storage_history', self::row(5, 'freezer-a', 'sample-of-a'));
        self::migrate();

        self::send(self::LAB_A, [self::row(4, 'freezer-a', 'sample-of-a')]);
        self::send(self::LAB_A, [self::row(4, 'freezer-a', 'sample-of-a')]);

        $this->assertSame([self::LAB_A => [4 => 'sample-of-a'], 'unclaimed' => [5 => 'sample-of-a']], self::held());
    }

    /**
     * The row app/vl/requests/sample-storage.php takes as where a sample is now, read
     * the way the page reads it.
     */
    private static function latest(string $sample, bool $isSts): array
    {
        $rows = LegacyAppHarness::db()->rawQuery(
            'SELECT h.* FROM lab_storage_history h WHERE h.sample_unique_id = ? ORDER BY '
                . StorageService::historyOrder($isSts),
            [$sample]
        );
        $historyId = StorageService::latestHistoryIds($rows)[$sample] ?? null;
        foreach ($rows as $row) {
            if ((int) $row['history_id'] === $historyId) {
                return [$row['sample_status'], $row['freezer_id']];
            }
        }
        return [];
    }

    #[RunInSeparateProcess]
    public function testARowSentAgainAfterBeingLostDoesNotBecomeTheLatest(): void
    {
        $this->boot();
        $db = LegacyAppHarness::db();
        // Lab A stored a sample (its row 3) and then removed it (its row 4); lab B's
        // own row 3 overwrote the first before 5.7.88.
        $db->insert('lab_storage_history', self::row(3, 'freezer-b', 'sample-of-b'));
        $db->insert('lab_storage_history', self::row(4, 'freezer-a', 'sample-of-a', [
            'sample_status' => 'Removed', 'updated_datetime' => '2026-09-02 10:00:00',
        ]));
        self::migrate();

        // Lab A sends its history again: row 3 comes back, with a later id on the STS.
        self::send(self::LAB_A, [self::row(3, 'freezer-a', 'sample-of-a')]);

        $this->assertSame(['Removed', 'freezer-a'], self::latest('sample-of-a', true));
    }

    #[RunInSeparateProcess]
    public function testOnTheStsARemovalWrittenThereIsLaterThanTheLabsRows(): void
    {
        $this->boot();
        self::migrate();
        // The lab's ids run far past the STS's own.
        self::send(self::LAB_A, [self::row(5000, 'freezer-a', 'sample-of-a')]);

        LegacyAppHarness::db()->insert('lab_storage_history', [
            'test_type' => 'vl', 'sample_unique_id' => 'sample-of-a', 'volume' => 1, 'freezer_id' => 'freezer-a',
            'rack' => 1, 'box' => 1, 'position' => 1, 'sample_status' => 'Removed',
            'updated_datetime' => '2026-09-05 12:00:00', 'updated_by' => 'sts-user',
        ]);

        $this->assertSame(['Removed', 'freezer-a'], self::latest('sample-of-a', true));
    }

    #[RunInSeparateProcess]
    public function testAMoveWrittenInOneSecondEndsInTheNewFreezer(): void
    {
        $this->boot();
        self::migrate();

        self::send(self::LAB_A, [
            self::row(8, 'freezer-a', 'sample-of-a', ['sample_status' => 'Removed']),
            self::row(9, 'freezer-a2', 'sample-of-a', ['sample_status' => 'Added']),
        ]);

        $this->assertSame(['Added', 'freezer-a2'], self::latest('sample-of-a', true));
    }

    #[RunInSeparateProcess]
    public function testOnTheStsARemovalInTheSameSecondAsTheLabsRowIsTheLater(): void
    {
        $this->boot();
        self::migrate();
        self::send(self::LAB_A, [
            self::row(5000, 'freezer-a', 'sample-of-a', ['updated_datetime' => '2026-09-05 12:00:00']),
        ]);

        LegacyAppHarness::db()->insert('lab_storage_history', [
            'test_type' => 'vl', 'sample_unique_id' => 'sample-of-a', 'volume' => 1, 'freezer_id' => 'freezer-a',
            'rack' => 1, 'box' => 1, 'position' => 1, 'sample_status' => 'Removed',
            'updated_datetime' => '2026-09-05 12:00:00', 'updated_by' => 'sts-user',
        ]);

        $this->assertSame(['Removed', 'freezer-a'], self::latest('sample-of-a', true));
    }

    #[RunInSeparateProcess]
    public function testOnALabTheOrderIsTheOrderTheRowsWereWrittenIn(): void
    {
        $this->boot('vluser');
        $db = LegacyAppHarness::db();
        // A clock set back between the two: the later row carries the earlier time.
        $db->insert('lab_storage_history', self::row(1, 'freezer-a', 'sample-of-a', [
            'updated_datetime' => '2026-09-02 10:00:00',
        ]));
        $db->insert('lab_storage_history', self::row(2, 'freezer-a', 'sample-of-a', [
            'sample_status' => 'Removed', 'updated_datetime' => '2026-09-01 10:00:00',
        ]));

        $this->assertSame(['Removed', 'freezer-a'], self::latest('sample-of-a', false));
    }

    #[RunInSeparateProcess]
    public function testOnALabTheUpgradeLeavesItsRowsAloneAndSendsTheHistoryAgain(): void
    {
        $this->boot('vluser');
        $db = LegacyAppHarness::db();
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'lab', 'last_lab_storage_history_id' => 40]);
        $db->insert('lab_storage_history', self::row(4, 'freezer-a', 'sample-of-a'));

        self::migrate();

        $this->assertSame(['unclaimed' => [4 => 'sample-of-a']], self::held());
        $this->assertNull($db->rawQueryOne('SELECT last_lab_storage_history_id AS v FROM s_vlsm_instance')['v']);
    }
}

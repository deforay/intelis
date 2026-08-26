<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use mysqli;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Transaction nesting, against a real server.
 *
 * MySQL has one transaction per connection, so a second START TRANSACTION would
 * implicitly commit the first. DatabaseService therefore counts scopes and lets only
 * the outermost one reach the server. This has to be tested against MySQL rather than
 * a double: what is under test is precisely what the server does with SAVEPOINT,
 * ROLLBACK TO SAVEPOINT and an implicit commit.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run. Without them the suite skips, so
 * `composer test` stays green on a machine with no database.
 */
final class DatabaseTransactionNestingTest extends TestCase
{
    private const DATABASE = 'intelis_tx_nesting_test';

    private static ?DatabaseService $db = null;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');

        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }

        $port = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $bootstrap->query('CREATE DATABASE `' . self::DATABASE . '`');
        $bootstrap->select_db(self::DATABASE);
        $bootstrap->query(
            'CREATE TABLE samples (id INT AUTO_INCREMENT PRIMARY KEY, tag VARCHAR(32)) ENGINE=InnoDB'
        );
        $bootstrap->query('CREATE TABLE seq (id INT PRIMARY KEY, n INT) ENGINE=InnoDB');
        $bootstrap->query('INSERT INTO seq VALUES (1, 0)');
        $bootstrap->close();

        self::$db = new DatabaseService([
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => self::DATABASE,
            'port' => $port,
        ]);
    }

    protected function setUp(): void
    {
        if (!self::$db instanceof DatabaseService) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        self::$db->rawQuery('DELETE FROM samples');
        self::$db->rawQuery('UPDATE seq SET n = 0 WHERE id = 1');
    }

    /** @return list<string> */
    private function tags(): array
    {
        return array_column(self::$db->rawQuery('SELECT tag FROM samples ORDER BY id') ?: [], 'tag');
    }

    private function sequence(): int
    {
        return (int) (self::$db->rawQueryOne('SELECT n FROM seq WHERE id = 1')['n'] ?? -1);
    }

    /**
     * The shape of insertSample() in every module's service: it opens a transaction of
     * its own, commits on success, rolls back on failure, and knows nothing about the
     * batch its caller may be part of.
     */
    private function insertSample(string $tag, bool $shouldFail = false): int
    {
        try {
            self::$db->beginTransaction();
            self::$db->insert('samples', ['tag' => $tag]);
            $id = (int) self::$db->getInsertId();
            if ($shouldFail) {
                throw new RuntimeException("insert failed for $tag");
            }
            self::$db->commitTransaction();
            return $id;
        } catch (Throwable) {
            self::$db->rollbackTransaction();
            return 0;
        }
    }

    /**
     * api/v1.1/*\/save-request.php opens one transaction, inserts every record in the
     * payload through insertSample(), and commits at the end. The service's commit
     * must not end the batch -- it used to, so the first record committed the lot and
     * the endpoint's own rollback then had nothing left to undo.
     */
    public function testANestedCommitDoesNotEndTheBatch(): void
    {
        self::$db->beginTransaction();
        $this->insertSample('rec-1');
        $this->insertSample('rec-2');
        self::$db->rollbackTransaction();

        self::assertSame([], $this->tags());
        self::assertFalse(self::$db->isTransactionActive());
    }

    public function testTheBatchStillCommitsEveryRecord(): void
    {
        self::$db->beginTransaction();
        $this->insertSample('rec-1');
        $this->insertSample('rec-2');
        self::$db->commitTransaction();

        self::assertSame(['rec-1', 'rec-2'], $this->tags());
    }

    /**
     * The endpoints count failed records and carry on, so one bad record has to unwind
     * to its own savepoint rather than take the payload with it.
     */
    public function testAFailedRecordIsSkippedAndTheBatchSurvives(): void
    {
        self::$db->beginTransaction();
        $this->insertSample('ok-1');
        $failed = $this->insertSample('bad', shouldFail: true);
        $this->insertSample('ok-2');
        self::$db->commitTransaction();

        self::assertSame(['ok-1', 'ok-2'], $this->tags());
        self::assertSame(0, $failed);
    }

    /**
     * AbstractTestService::generateSampleCode() claiming a number while the caller
     * holds a transaction -- the interop receivers do exactly this. Rolling the
     * receiver back has to return the number to the series, because a gap in the
     * series is what a lab notices.
     */
    public function testANestedSampleCodeClaimIsReturnedWhenTheCallerRollsBack(): void
    {
        self::$db->beginTransaction();

        self::$db->beginTransaction();
        self::$db->rawQuery('UPDATE seq SET n = n + 1 WHERE id = 1');
        self::$db->commitTransaction();

        self::$db->insert('samples', ['tag' => 'code-1']);
        self::$db->rollbackTransaction();

        self::assertSame(0, $this->sequence());
        self::assertSame([], $this->tags());
    }

    public function testANestedSampleCodeClaimSurvivesWhenTheCallerCommits(): void
    {
        self::$db->beginTransaction();

        self::$db->beginTransaction();
        self::$db->rawQuery('UPDATE seq SET n = n + 1 WHERE id = 1');
        self::$db->commitTransaction();

        self::$db->insert('samples', ['tag' => 'code-1']);
        self::$db->commitTransaction();

        self::assertSame(1, $this->sequence());
        self::assertSame(['code-1'], $this->tags());
    }

    public function testOnlyTheRolledBackScopeIsLostWhenNestingThreeDeep(): void
    {
        self::$db->beginTransaction();
        self::$db->insert('samples', ['tag' => 'L1']);
        self::$db->beginTransaction();
        self::$db->insert('samples', ['tag' => 'L2']);
        self::$db->beginTransaction();
        self::$db->insert('samples', ['tag' => 'L3']);
        self::$db->rollbackTransaction();
        self::$db->commitTransaction();
        self::$db->commitTransaction();

        self::assertSame(['L1', 'L2'], $this->tags());
    }

    /**
     * TestRequestsService rolls back unconditionally as a backstop on paths that may
     * already have resolved, and says so in a comment. Keep that true.
     */
    public function testCallsAfterTheTransactionIsResolvedAreNoOps(): void
    {
        self::$db->beginTransaction();
        self::$db->insert('samples', ['tag' => 'kept']);
        self::$db->commitTransaction();

        self::$db->rollbackTransaction();
        self::$db->commitTransaction();

        self::assertSame(['kept'], $this->tags());
        self::assertFalse(self::$db->isTransactionActive());
    }

    public function testPlainSingleLevelUseIsUnchanged(): void
    {
        self::$db->beginTransaction();
        self::$db->insert('samples', ['tag' => 'plain']);
        self::$db->commitTransaction();
        self::assertSame(['plain'], $this->tags());

        self::$db->rawQuery('DELETE FROM samples');

        self::$db->beginTransaction();
        self::$db->insert('samples', ['tag' => 'discarded']);
        self::$db->rollbackTransaction();
        self::assertSame([], $this->tags());
    }

    public function testReadOnlyTransactionsStillPairWithRollback(): void
    {
        self::$db->beginReadOnlyTransaction();
        self::$db->insert('samples', ['tag' => 'ro']);
        self::$db->rollbackTransaction();

        self::assertSame([], $this->tags());
        self::assertFalse(self::$db->isTransactionActive());
    }

    /**
     * SAVEPOINT cannot go through the prepared statement protocol, so these three
     * fatalled the moment anything called them. Nothing did, until nesting started to.
     */
    public function testAnExplicitPartialRollbackLeavesTheTransactionOpen(): void
    {
        self::$db->beginTransaction();
        self::$db->insert('samples', ['tag' => 'a']);
        self::$db->createSavepoint('manual_1');
        self::$db->insert('samples', ['tag' => 'b']);
        self::$db->rollbackTransaction('manual_1');

        self::assertTrue(self::$db->isTransactionActive());

        self::$db->commitTransaction();
        self::assertSame(['a'], $this->tags());
    }

    /**
     * A savepoint name is an identifier and cannot be bound, so it is validated rather
     * than escaped.
     */
    public function testASavepointNameIsValidatedRatherThanInterpolated(): void
    {
        self::$db->beginTransaction();

        $this->expectException(Throwable::class);
        try {
            self::$db->createSavepoint('bad name`; DROP TABLE samples; --');
        } finally {
            self::$db->rollbackTransaction();
        }
    }
}

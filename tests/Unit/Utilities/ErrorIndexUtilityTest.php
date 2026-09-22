<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use App\Utilities\MiscUtility;
use App\Utilities\ErrorIndexUtility;
use App\Utilities\ErrorIndexLogHandler;
use PHPUnit\Framework\TestCase;

final class ErrorIndexUtilityTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/error-index-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        ErrorIndexUtility::usePath($this->dir . '/errors.sqlite');
    }

    protected function tearDown(): void
    {
        ErrorIndexUtility::usePath(null);
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function entry(array $overrides = []): array
    {
        return $overrides + [
            'logged_at' => date('Y-m-d H:i:s'),
            'level' => 'ERROR',
            'message' => 'Something broke',
            'error_id' => MiscUtility::generateErrorId(),
            'exception_class' => 'RuntimeException',
            'file' => ROOT_PATH . '/app/example.php',
            'line' => 42,
        ];
    }

    public function testGeneratedIdsMatchThePattern(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue(ErrorIndexUtility::isErrorId(MiscUtility::generateErrorId()));
        }
        $this->assertTrue(ErrorIndexUtility::isErrorId(' err-7k3q-x9m2 '));
        $this->assertFalse(ErrorIndexUtility::isErrorId('ERR-20260922-143012-65f1a3'));
        $this->assertFalse(ErrorIndexUtility::isErrorId('ERR-7K3Q-X9MI'), 'I is not in Crockford base32');
        $this->assertFalse(ErrorIndexUtility::isErrorId('timeout'));
    }

    public function testFindReturnsTheDayAnErrorWasLogged(): void
    {
        $entry = $this->entry(['logged_at' => '2026-09-01 10:15:00']);
        ErrorIndexUtility::record($entry);

        $row = ErrorIndexUtility::find(strtolower($entry['error_id']));

        $this->assertNotNull($row);
        $this->assertSame('2026-09-01', $row['log_date']);
        $this->assertSame('app/example.php', $row['file'], 'paths are stored relative to the install');
        $this->assertNull(ErrorIndexUtility::find('ERR-0000-0000'));
    }

    public function testFindWithoutAnIndexFileDoesNotCreateOne(): void
    {
        $this->assertNull(ErrorIndexUtility::find('ERR-0000-0000'));
        $this->assertSame([], ErrorIndexUtility::recurring());
        $this->assertFileDoesNotExist(ErrorIndexUtility::path());
    }

    public function testSameLocationGroupsTogetherWhateverTheMessage(): void
    {
        ErrorIndexUtility::record($this->entry(['message' => 'Deadlock on sample 1']));
        ErrorIndexUtility::record($this->entry(['message' => 'Deadlock on sample 2']));
        ErrorIndexUtility::record($this->entry(['message' => 'Deadlock on sample 3', 'error_id' => 'ERR-AAAA-BBBB']));
        ErrorIndexUtility::record($this->entry(['line' => 99]));

        $groups = ErrorIndexUtility::recurring();

        $this->assertCount(2, $groups);
        $this->assertSame(3, $groups[0]['occurrences']);
        $this->assertSame('Deadlock on sample 3', $groups[0]['message'], 'the latest message represents the group');
        $this->assertSame('ERR-AAAA-BBBB', $groups[0]['latest_error_id']);
        $this->assertSame(1, $groups[1]['occurrences']);
    }

    public function testWithoutALocationTheMessageIsTheFingerprint(): void
    {
        $this->assertSame(
            ErrorIndexUtility::fingerprint(null, null, null, 'Disk full'),
            ErrorIndexUtility::fingerprint(null, null, null, 'Disk full')
        );
        $this->assertNotSame(
            ErrorIndexUtility::fingerprint(null, null, null, 'Disk full'),
            ErrorIndexUtility::fingerprint(null, null, null, 'Timeout')
        );
    }

    public function testRecurringOnlyCountsTheWindow(): void
    {
        ErrorIndexUtility::record($this->entry(['logged_at' => date('Y-m-d H:i:s', strtotime('-30 days'))]));
        ErrorIndexUtility::record($this->entry());

        $groups = ErrorIndexUtility::recurring(days: 7);

        $this->assertCount(1, $groups);
        $this->assertSame(1, $groups[0]['occurrences']);
    }

    public function testPruneDropsRowsPastRetention(): void
    {
        $old = $this->entry(['logged_at' => date('Y-m-d H:i:s', strtotime('-100 days'))]);
        $recent = $this->entry();
        ErrorIndexUtility::record($old);
        ErrorIndexUtility::record($recent);

        $result = ErrorIndexUtility::prune(retentionDays: 90, compact: true);

        $this->assertSame(1, $result['deleted']);
        $this->assertTrue($result['compacted']);
        $this->assertFalse($result['rebuilt']);
        $this->assertNull(ErrorIndexUtility::find($old['error_id']));
        $this->assertNotNull(ErrorIndexUtility::find($recent['error_id']));
    }

    public function testPruneResetsADamagedFile(): void
    {
        ErrorIndexUtility::usePath(null);
        ErrorIndexUtility::usePath($this->dir . '/errors.sqlite');
        file_put_contents(ErrorIndexUtility::path(), str_repeat('not a database ', 100));

        $result = ErrorIndexUtility::prune();

        $this->assertTrue($result['rebuilt']);
        $this->assertFileDoesNotExist(ErrorIndexUtility::path());

        $entry = $this->entry();
        ErrorIndexUtility::record($entry);
        $this->assertNotNull(ErrorIndexUtility::find($entry['error_id']), 'a fresh index is created on the next error');
    }

    /** @return list<string> */
    private function days(string $search): array
    {
        return array_column(ErrorIndexUtility::searchDays($search), 'log_date');
    }

    private function recordSearchFixtures(): void
    {
        ErrorIndexUtility::record($this->entry([
            'logged_at' => '2026-09-01 09:00:00',
            'message' => 'MySQL server has gone away',
            'exception_class' => 'mysqli_sql_exception',
        ]));
        ErrorIndexUtility::record($this->entry([
            'logged_at' => '2026-09-03 11:00:00',
            'message' => 'Deadlock found when trying to get lock on form_vl',
        ]));
        ErrorIndexUtility::record($this->entry([
            'logged_at' => '2026-09-03 12:00:00',
            'message' => 'Deadlock found when trying to get lock on form_eid',
            'error_id' => 'ERR-DDDD-3333',
        ]));
        ErrorIndexUtility::record($this->entry([
            'logged_at' => '2026-09-05 08:00:00',
            'message' => 'Connection timed out',
            'file' => ROOT_PATH . '/app/tasks/remote/results-sender.php',
            'url' => '/remote/v2/results.php',
        ]));
    }

    public function testSearchListsTheDaysAnErrorHappenedNewestFirst(): void
    {
        $this->recordSearchFixtures();
        ErrorIndexUtility::record($this->entry(['logged_at' => '2026-09-06 08:00:00', 'message' => 'Deadlock again']));

        $days = ErrorIndexUtility::searchDays('deadlock');

        $this->assertSame(['2026-09-06', '2026-09-03'], array_column($days, 'log_date'));
        $this->assertSame(2, $days[1]['matches']);
        $this->assertSame('Deadlock found when trying to get lock on form_eid', $days[1]['message'], 'the latest one');
        $this->assertSame('ERR-DDDD-3333', $days[1]['error_id']);
    }

    public function testEveryWordHasToMatch(): void
    {
        $this->recordSearchFixtures();

        $this->assertSame(['2026-09-03'], $this->days('deadlock form_vl'));
        $this->assertSame([], $this->days('deadlock timed'));
    }

    public function testWordsMatchFromTheirStartAndPhrasesAsWritten(): void
    {
        $this->recordSearchFixtures();

        $this->assertSame(['2026-09-01'], $this->days('gon'), 'a word matches from its start');
        $this->assertSame(['2026-09-01'], $this->days('"has gone away"'));
        $this->assertSame([], $this->days('"gone has away"'), 'a phrase matches in order');
        $this->assertSame(['2026-09-01'], $this->days('+gone'), 'the viewer markers are read as the word');
    }

    public function testSearchLooksAtTheClassFileAndUrlToo(): void
    {
        $this->recordSearchFixtures();

        $this->assertSame(['2026-09-01'], $this->days('mysqli_sql_exception'));
        $this->assertSame(['2026-09-05'], $this->days('results-sender'));
        $this->assertSame(['2026-09-05'], $this->days('remote/v2/results.php'));
    }

    public function testQuerySyntaxInTheSearchIsSearchedForNotRun(): void
    {
        $this->recordSearchFixtures();

        $searches = [
            'NEAR(deadlock timed)', 'deadlock OR timed', '"unbalanced', 'message:deadlock', '-deadlock', '*', '::', '',
        ];
        foreach ($searches as $search) {
            $days = ErrorIndexUtility::searchDays($search);
            $this->assertIsArray($days, $search);
        }
        $this->assertSame([], $this->days('deadlock OR timed'), 'OR is a word here, not an operator');
        $this->assertSame([], $this->days('*'));
        $this->assertSame(['2026-09-03'], $this->days('deadlock "form_vl'), 'a stray quote does not break the search');
    }

    public function testPrunedErrorsAreNoLongerFound(): void
    {
        ErrorIndexUtility::record($this->entry([
            'logged_at' => date('Y-m-d H:i:s', strtotime('-100 days')),
            'message' => 'Ancient failure',
        ]));
        ErrorIndexUtility::record($this->entry(['message' => 'Recent failure']));

        ErrorIndexUtility::prune(retentionDays: 90);

        $this->assertSame([], $this->days('ancient'));
        $this->assertCount(1, ErrorIndexUtility::searchDays('failure'));
    }

    public function testAnIndexFromBeforeFullTextIsSearchable(): void
    {
        // An index file written by the previous release: the errors table, no full-text table.
        $pdo = new \PDO('sqlite:' . ErrorIndexUtility::path());
        $pdo->exec(
            'CREATE TABLE errors (id INTEGER PRIMARY KEY, error_id TEXT, logged_at TEXT NOT NULL,
                log_date TEXT NOT NULL, level TEXT NOT NULL, fingerprint TEXT NOT NULL, exception_class TEXT,
                message TEXT NOT NULL, file TEXT, line INTEGER, url TEXT, user_id TEXT, ip TEXT)'
        );
        $pdo->exec(
            "INSERT INTO errors (error_id, logged_at, log_date, level, fingerprint, message)
                VALUES ('ERR-OLDD-0001', '2026-08-30 10:00:00', '2026-08-30', 'ERROR', 'f', 'Disk quota exceeded')"
        );
        $pdo = null;

        // Searchable straight away, by LIKE: a request does not build the full-text table.
        $this->assertSame(['2026-08-30'], $this->days('quota'));
        $this->assertFalse($this->hasFullTextTable());

        ErrorIndexUtility::record($this->entry([
            'logged_at' => '2026-09-02 10:00:00', 'message' => 'Disk quota again',
        ]));
        ErrorIndexUtility::prune(retentionDays: 3650);
        $this->assertTrue($this->hasFullTextTable(), 'housekeeping builds it from the rows already there');

        ErrorIndexUtility::record($this->entry([
            'logged_at' => '2026-09-04 10:00:00', 'message' => 'Disk quota third',
        ]));
        $this->assertSame(['2026-09-04', '2026-09-02', '2026-08-30'], $this->days('quota'));
        $this->assertSame(['2026-09-02'], $this->days('"quota again"'), 'a phrase, which LIKE could not tell apart');
    }

    private function hasFullTextTable(): bool
    {
        $pdo = new \PDO('sqlite:' . ErrorIndexUtility::path());
        return (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE name = 'errors_fts'")->fetchColumn();
    }

    public function testANewIndexHasFullTextFromItsFirstError(): void
    {
        ErrorIndexUtility::record($this->entry());

        $this->assertTrue($this->hasFullTextTable());
    }

    public function testSearchWithoutAnIndexFileDoesNotCreateOne(): void
    {
        $this->assertSame([], ErrorIndexUtility::searchDays('anything'));
        $this->assertFileDoesNotExist(ErrorIndexUtility::path());
    }

    public function testWithoutFullTextTheSearchStillWorks(): void
    {
        ErrorIndexUtility::usePath(ErrorIndexUtility::path(), fullText: false);
        $this->recordSearchFixtures();
        ErrorIndexUtility::record($this->entry([
            'logged_at' => '2026-09-07 08:00:00', 'message' => 'Rate 100% reached',
        ]));

        $this->assertSame(['2026-09-03'], $this->days('deadlock form_vl'));
        $this->assertSame(['2026-09-05'], $this->days('results-sender'));
        $this->assertSame(['2026-09-07'], $this->days('100%'));
        $this->assertSame([], $this->days('form%vl'), 'a % in the search is a character, not a wildcard');
        $this->assertSame([], $this->days('form_eid_'), 'nor is an underscore');
    }

    public function testRecordNeverThrowsWhenTheFolderIsMissing(): void
    {
        ErrorIndexUtility::usePath($this->dir . '/missing/errors.sqlite');

        ErrorIndexUtility::record($this->entry());

        $this->assertFileDoesNotExist($this->dir . '/missing/errors.sqlite');
    }

    public function testOriginUnwrapsToTheOriginalException(): void
    {
        $line = __LINE__ + 1;
        $original = new \PDOException('Deadlock found');
        $wrapper = new \RuntimeException('Deadlock found', 500, $original);

        $origin = ErrorIndexUtility::originOf($wrapper);

        $this->assertSame($original, $origin['exception']);
        $this->assertSame(__FILE__, $origin['file']);
        $this->assertSame($line, $origin['line']);
    }

    public function testOriginSkipsLibraryFramesToTheCallingCode(): void
    {
        $vendorDir = $this->dir . '/vendor';
        mkdir($vendorDir);
        $library = $vendorDir . '/library.php';
        file_put_contents(
            $library,
            '<?php return static function (): never { throw new \RuntimeException("driver failure"); };'
        );
        $throwFromLibrary = require $library;

        try {
            $line = __LINE__ + 1;
            $throwFromLibrary();
        } catch (\RuntimeException $e) {
            $origin = ErrorIndexUtility::originOf(new \RuntimeException('wrapped', 0, $e));
        } finally {
            unlink($library);
            rmdir($vendorDir);
        }

        $this->assertStringEndsWith("/vendor/library.php", $e->getFile(), "thrown inside the library");
        $this->assertSame(__FILE__, $origin['file'], 'reported at the code that called it');
        $this->assertSame($line, $origin['line']);
    }

    public function testHandlerIndexesErrorsButNotWarnings(): void
    {
        $logger = new Logger('test', [new ErrorIndexLogHandler()]);

        $logger->warning('Just a warning', ['error_id' => 'ERR-WWWW-WWWW']);
        $logger->error('Real failure', [
            'error_id' => 'ERR-EEEE-EEEE',
            'exception_class' => 'PDOException',
            'file' => ROOT_PATH . '/app/x.php',
            'line' => 7,
            'ip_address' => '10.0.0.5',
        ]);

        $this->assertNull(ErrorIndexUtility::find('ERR-WWWW-WWWW'));
        $row = ErrorIndexUtility::find('ERR-EEEE-EEEE');
        $this->assertNotNull($row);
        $this->assertSame('PDOException', $row['exception_class']);
        $this->assertSame('app/x.php', $row['file']);
        $this->assertSame('10.0.0.5', $row['ip']);
        $this->assertSame('ERROR', $row['level']);
    }

    public function testHandlerLetsTheRecordReachTheNextHandler(): void
    {
        $handler = new ErrorIndexLogHandler();
        $record = new LogRecord(new DateTimeImmutable(), 'test', Level::Error, 'x');

        $this->assertFalse($handler->handle($record), 'bubbling on, so the file handler still writes');
    }
}

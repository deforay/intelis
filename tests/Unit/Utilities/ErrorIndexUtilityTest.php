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

    public function testRecordNeverThrowsWhenTheFolderIsMissing(): void
    {
        ErrorIndexUtility::usePath($this->dir . '/missing/errors.sqlite');

        ErrorIndexUtility::record($this->entry());

        $this->assertFileDoesNotExist($this->dir . '/missing/errors.sqlite');
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

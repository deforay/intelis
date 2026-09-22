<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use App\Utilities\ErrorIndexUtility;
use Slim\Psr7\Factory\ServerRequestFactory;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The log viewer's "days with errors matching this search", driven through the
 * page the way a browser calls it.
 *
 * One drive per test: the handler uses require_once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ErrorIndexDaysEndpointTest extends TestCase
{
    private bool $booted = false;
    private string $dir = '';

    protected function tearDown(): void
    {
        ErrorIndexUtility::usePath(null);
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if ($this->dir !== '' && is_dir($this->dir)) {
            rmdir($this->dir);
        }
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $database = 'intelis_error_index_days_test_' . getmypid();
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['vl' => true]]);
        }
        LegacyAppHarness::boot($database, ['system_config', 'global_config', 'roles', 'user_details']);
        $this->booted = true;
        LegacyAppHarness::withSession(['roleId' => 1]);

        $this->dir = sys_get_temp_dir() . '/error-index-days-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        ErrorIndexUtility::usePath($this->dir . '/errors.sqlite');
    }

    private static function error(string $loggedAt, string $message, ?string $class = null): void
    {
        ErrorIndexUtility::record([
            'logged_at' => $loggedAt, 'level' => 'ERROR', 'message' => $message,
            'exception_class' => $class, 'file' => ROOT_PATH . '/app/example.php', 'line' => 10,
        ]);
    }

    /** @return array{days: list<array<string, mixed>>} */
    private static function drive(mixed $search, string &$raw = ''): array
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/admin/monitoring/get-error-index-days.php')
            ->withQueryParams($search === null ? [] : ['search' => $search])
            ->withHeader('User-Agent', 'intelis-tests')
            ->withHeader('X-Forwarded-For', '127.0.0.1');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $raw = (string) $handler->handle($request)->getBody();
        return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    }

    #[RunInSeparateProcess]
    public function testListsTheDaysWithAMatchingErrorNewestFirst(): void
    {
        $this->boot();
        self::error('2026-09-01 09:00:00', 'Deadlock found on form_vl', 'mysqli_sql_exception');
        self::error('2026-09-03 10:00:00', 'Deadlock found on form_eid');
        self::error('2026-09-03 11:00:00', 'Deadlock found on form_tb', 'mysqli_sql_exception');
        self::error('2026-09-04 11:00:00', 'Connection timed out');

        $days = self::drive('deadlock')['days'];

        $this->assertSame(
            [DateUtility::humanReadableDateFormat('2026-09-03'), DateUtility::humanReadableDateFormat('2026-09-01')],
            array_column($days, 'date')
        );
        $this->assertSame([2, 1], array_column($days, 'matches'));
        $this->assertSame('mysqli_sql_exception Deadlock found on form_tb', $days[0]['message']);
    }

    #[RunInSeparateProcess]
    public function testAnErrorMessageCannotBreakOutOfTheResponse(): void
    {
        $this->boot();
        self::error('2026-09-05 09:00:00', 'Bad value <script>alert(1)</script> & "quotes"');

        $raw = '';
        $days = self::drive('value', $raw)['days'];

        $this->assertStringNotContainsString('<script>', $raw);
        $this->assertSame('Bad value <script>alert(1)</script> & "quotes"', $days[0]['message']);
    }

    #[RunInSeparateProcess]
    public function testNoSearchListsNothing(): void
    {
        $this->boot();
        self::error('2026-09-05 09:00:00', 'Something failed');

        $this->assertSame([], self::drive(null)['days']);
    }

    #[RunInSeparateProcess]
    public function testABlankSearchListsNothing(): void
    {
        $this->boot();
        self::error('2026-09-05 09:00:00', 'Something failed');

        $this->assertSame([], self::drive('   ')['days']);
    }

    #[RunInSeparateProcess]
    public function testASearchThatIsNotTextListsNothing(): void
    {
        $this->boot();
        self::error('2026-09-05 09:00:00', 'Something failed');

        $this->assertSame([], self::drive(['failed'])['days']);
    }

    #[RunInSeparateProcess]
    public function testAnOverlongSearchListsNothing(): void
    {
        $this->boot();
        self::error('2026-09-05 09:00:00', 'Something failed');

        $this->assertSame([], self::drive('failed ' . str_repeat('x', 300))['days']);
    }

    #[RunInSeparateProcess]
    public function testWithoutAnIndexFileNothingIsListedAndNoneIsCreated(): void
    {
        $this->boot();

        $this->assertSame([], self::drive('failed')['days']);
        $this->assertFileDoesNotExist(ErrorIndexUtility::path());
    }
}

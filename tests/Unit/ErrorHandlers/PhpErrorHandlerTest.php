<?php

declare(strict_types=1);

namespace Tests\Unit\ErrorHandlers;

use App\ErrorHandlers\PhpErrorHandler;
use App\Utilities\LoggerUtility;
use ErrorException;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;

final class PhpErrorHandlerTest extends TestCase
{
    private TestHandler $logs;
    private int $reporting;
    private bool $installed = false;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
        LoggerUtility::getLogger()->pushHandler($this->logs);
        // What production runs with; the test runner narrows it.
        $this->reporting = error_reporting(E_ALL);
    }

    protected function tearDown(): void
    {
        LoggerUtility::getLogger()->popHandler();
        if ($this->installed) {
            restore_error_handler();
        }
        error_reporting($this->reporting);
    }

    private function install(bool $verbose): void
    {
        set_error_handler(new PhpErrorHandler($verbose));
        $this->installed = true;
    }

    public function testAWarningSilencedWithAtIsNotLogged(): void
    {
        $this->install(verbose: true);

        // What provision.php does to a directory the running user does not own.
        $result = @chmod(sys_get_temp_dir() . '/no-such-dir-' . bin2hex(random_bytes(4)), 0770);

        $this->assertFalse($result);
        $this->assertSame([], $this->logs->getRecords());
    }

    public function testInDebugModeAWarningIsLoggedAsAWarningNotAnError(): void
    {
        $this->install(verbose: true);

        trigger_error('Something odd', E_USER_WARNING);

        $this->assertTrue($this->logs->hasWarningThatContains('Something odd'));
        $this->assertFalse($this->logs->hasErrorRecords());
    }

    public function testInDebugModeNoticesAndDeprecationsKeepTheirLevel(): void
    {
        $this->install(verbose: true);

        trigger_error('A notice', E_USER_NOTICE);
        trigger_error('Old call', E_USER_DEPRECATED);

        $this->assertTrue($this->logs->hasNoticeThatContains('A notice'));
        $this->assertTrue($this->logs->hasInfoThatContains('Old call'));
        $this->assertFalse($this->logs->hasErrorRecords());
    }

    public function testInProductionAWarningIsNotLogged(): void
    {
        $this->install(verbose: false);

        trigger_error('Something odd', E_USER_WARNING);

        $this->assertSame([], $this->logs->getRecords());
    }

    public function testAUserErrorIsStillAnError(): void
    {
        $this->install(verbose: true);

        (new PhpErrorHandler(true))(E_USER_ERROR, 'Broken', __FILE__, __LINE__);

        $this->assertTrue($this->logs->hasErrorThatContains('Broken'));
    }

    public function testAFatalLevelIsLoggedAndThrownInEitherMode(): void
    {
        foreach ([true, false] as $verbose) {
            try {
                (new PhpErrorHandler($verbose))(E_ERROR, 'Fatal thing', __FILE__, __LINE__);
                $this->fail('A fatal level must throw');
            } catch (ErrorException $e) {
                $this->assertSame(E_ERROR, $e->getSeverity());
            }
        }
        $this->assertCount(2, array_filter(
            $this->logs->getRecords(),
            static fn($r): bool => $r->message === 'Fatal thing'
        ));
    }
}

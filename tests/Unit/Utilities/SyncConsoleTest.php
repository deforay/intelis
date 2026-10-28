<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\SyncConsole;
use App\Utilities\SyncConsoleWait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The console every `composer sync` script talks through: a wait on the STS shows
 * that it is waiting, on a terminal and in a cron log, and adds nothing to a quick
 * call or a web run.
 */
final class SyncConsoleTest extends TestCase
{
    private float $now = 1000.0;

    private function style(BufferedOutput $out): SymfonyStyle
    {
        return new SymfonyStyle(new ArrayInput([]), $out);
    }

    private function wait(BufferedOutput $out, ?\Closure $show = null): SyncConsoleWait
    {
        return new SyncConsoleWait($this->style($out), 'Waiting for the STS', $show, fn(): float => $this->now);
    }

    public function testAQuickCallShowsNothing(): void
    {
        $out = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $wait = $this->wait($out);
        $progress = $wait->track();

        $this->now += 0.5;
        $progress(0, 0, 0, 0);
        $wait->done();

        self::assertSame('', $out->fetch());
    }

    public function testATerminalSeesTheWaitAndThenAClearLine(): void
    {
        $out = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $wait = $this->wait($out);
        $progress = $wait->track();

        $this->now += 12;
        $progress(0, 0, 0, 0);
        $wait->done();

        $shown = $out->fetch();
        self::assertStringContainsString('Waiting for the STS... 12s', $shown);
        self::assertStringEndsWith("\r", $shown, 'the line is cleared for what follows');
    }

    public function testTheLineSaysWhatIsMoving(): void
    {
        $wait = $this->wait(new BufferedOutput());
        $progress = $wait->track();

        $progress(0, 0, 3 * 1048576, 1048576);
        self::assertSame('Sending to the STS: 1.0 MB of 3.0 MB, 4s', $wait->text($this->now + 4));

        $progress(0, 2516582, 3 * 1048576, 3 * 1048576);
        self::assertSame('Receiving from the STS: 2.4 MB, 9s', $wait->text($this->now + 9));
    }

    public function testRequestsSentTogetherShareOneLine(): void
    {
        $wait = $this->wait(new BufferedOutput());

        ($wait->track('vl'))(0, 1048576, 0, 0);
        ($wait->track('eid'))(0, 1048576, 0, 0);

        self::assertSame('Receiving from the STS: 2.0 MB, 3s', $wait->text($this->now + 3));
    }

    public function testALogGetsALineEveryFifteenSecondsNotARedraw(): void
    {
        $out = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);
        $wait = $this->wait($out);
        $progress = $wait->track();

        for ($second = 1; $second <= 40; $second++) {
            $this->now += 1;
            $progress(0, 0, 0, 0);
        }
        $wait->done();

        $lines = array_values(array_filter(explode("\n", $out->fetch())));
        self::assertSame([' Waiting for the STS... 15s', ' Waiting for the STS... 30s'], $lines);
    }

    public function testAProgressBarCanCarryTheWait(): void
    {
        $out = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $shown = [];
        $wait = $this->wait($out, function (string $text) use (&$shown): void {
            $shown[] = $text;
        });
        $progress = $wait->track();

        $this->now += 2;
        $progress(0, 0, 2048, 1024);
        $wait->done();

        self::assertSame(['Sending to the STS: 1 KB of 2 KB, 2s'], $shown);
        self::assertSame('', $out->fetch(), 'no line of its own over the bar');
    }

    public function testAWebRunPrintsNothing(): void
    {
        $console = SyncConsole::none();
        ob_start();

        $console->title('Receiving test requests from the STS');
        $console->line('Checking the connection to the STS...');
        $progress = $console->progress(null, 'Saving');
        $progress->setProgress(10);
        $progress->finish();
        $wait = $console->wait();
        ($wait->track())(0, 10, 0, 0);
        $wait->done();
        $printed = ob_get_clean();

        self::assertSame('', $printed, 'a web run answers with JSON, not console lines');
        self::assertNull($console->io());
    }

    public function testDetailsNeedVerbose(): void
    {
        $quiet = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL);
        (new SyncConsole($this->style($quiet)))->detail('Download finished in 1.14s.');
        $verbose = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
        (new SyncConsole($this->style($verbose)))->detail('Download finished in 1.14s.');

        self::assertSame('', $quiet->fetch());
        self::assertStringContainsString('Download finished in 1.14s.', $verbose->fetch());
    }

    public function testACountWithoutATotalStillShowsOnATerminal(): void
    {
        $out = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $progress = (new SyncConsole($this->style($out)))->progress(null, 'Saving');

        $progress->setProgress(320);
        $progress->finish();

        self::assertStringContainsString('320', $out->fetch());
    }

    public function testNothingToWorkThroughDrawsNoBar(): void
    {
        $out = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $progress = (new SyncConsole($this->style($out)))->progress(null, 'Saving');

        $progress->finish();

        self::assertSame('', $out->fetch());
    }
}

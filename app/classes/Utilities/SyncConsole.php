<?php

namespace App\Utilities;

use Throwable;
use GuzzleHttp\Exception\ResponseException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * How every script of `composer sync` talks to whoever runs it.
 *
 * The scripts run one after another, so the operator reads them as one run. Each
 * printed its own way: bare lines, a dot spinner, a progress bar, and nothing at
 * all while it waited on the STS. One lab's run sat silent for seven minutes. So
 * every script uses this, and the run reads the same from start to end:
 *
 *  - title() once per script, section() once per test type
 *  - line() for each step, before it starts, so a wait always says what it waits on
 *  - wait() while a request to the STS is out: a live line of time, and of bytes
 *    once they move
 *  - progress() while records are worked through
 *  - ok(), warn() and fail() for outcomes. A failure says what happens next.
 *  - detail() for timings and sizes, shown only with -v
 *
 * On a terminal the live lines redraw in place. Cron output goes to a log, where a
 * redrawn line is a pile of partial lines, so there they are written as plain
 * lines every LOG_SECONDS. A web run (the sync button) has no console: everything
 * here is then a no-op.
 */
final class SyncConsole
{
    public function __construct(private readonly ?SymfonyStyle $io)
    {
    }

    /** The console of a script run from the command line, with -v read from argv. */
    public static function cli(): self
    {
        return new self(new SymfonyStyle(
            new ArgvInput(),
            new ConsoleOutput(MiscUtility::verbosityFromArgv($_SERVER['argv'] ?? []))
        ));
    }

    /** For a script that also serves the web: no console there. */
    public static function none(): self
    {
        return new self(null);
    }

    public function io(): ?SymfonyStyle
    {
        return $this->io;
    }

    public function title(string $text): void
    {
        $this->io?->title($text);
    }

    public function section(string $text): void
    {
        $this->io?->section($text);
    }

    /** @param string|list<string> $text */
    public function line(string|array $text): void
    {
        $this->io?->text($text);
    }

    /** Timings, sizes and server hints: for whoever asked with -v. */
    public function detail(string $text): void
    {
        if ($this->io?->isVerbose()) {
            $this->io->comment($text);
        }
    }

    public function ok(string $text): void
    {
        $this->io?->success($text);
    }

    public function warn(string $text): void
    {
        $this->io?->warning($text);
    }

    public function fail(string $text): void
    {
        $this->io?->error($text);
    }

    /**
     * After a failed call to the STS: the STS's error ID and where to look it up,
     * when its answer carries one. The STS logs every error under that ID, and the
     * lab's message alone is often the reason without the detail.
     *
     * @param string $endpointUrl the URL that was called, under /remote/ on the STS
     * @param mixed $answer what came back: the body, ApiService::post()'s
     *        array with a 'body', or the exception of an async call
     */
    public function stsError(string $endpointUrl, mixed $answer): void
    {
        $errorId = self::stsErrorId($answer);
        if ($errorId === null) {
            return;
        }
        $base = strstr($endpointUrl, '/remote/', true) ?: rtrim($endpointUrl, '/');
        $this->line(sprintf(
            _translate('STS error ID: %s. Look it up on the STS: %s'),
            $errorId,
            $base . '/admin/monitoring/log-files.php?q=' . rawurlencode($errorId)
        ));
    }

    /** The error ID in an STS error answer ({"error": {"error_id": "ERR-..."}}), or null. */
    public static function stsErrorId(mixed $answer): ?string
    {
        if ($answer instanceof ResponseException) {
            $body = $answer->getResponse()->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $answer = $body->getContents();
        } elseif ($answer instanceof Throwable) {
            return null;
        } elseif (is_array($answer)) {
            $answer = $answer['body'] ?? null;
        }
        if (!is_string($answer) || $answer === '') {
            return null;
        }
        $data = json_decode($answer, true);
        $errorId = is_array($data) && is_array($data['error'] ?? null) ? ($data['error']['error_id'] ?? null) : null;
        return is_string($errorId) && preg_match('/^ERR-[0-9A-Z]{4}-[0-9A-Z]{4}$/', $errorId) ? $errorId : null;
    }

    /**
     * A request to the STS is out. Call done() on the result once it is back. With
     * $into, a terminal shows the wait as that progress bar's message.
     */
    public function wait(?string $label = null, ?SyncConsoleProgress $into = null): SyncConsoleWait
    {
        $show = $into?->isLive() ? $into->message(...) : null;
        return new SyncConsoleWait($this->io, $label ?? _translate('Waiting for the STS'), $show);
    }

    /** Records being worked through; $max null when the total is not known. */
    public function progress(?int $max, string $what): SyncConsoleProgress
    {
        return new SyncConsoleProgress($this->io, $max, $what);
    }
}

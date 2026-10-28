<?php

namespace App\Utilities;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Records being worked through (SyncConsole::progress()): one bar for every sync
 * script on a terminal, and a line every SyncConsoleWait::LOG_SECONDS in a log.
 *
 * The total is not always known: a lab that pulls a whole window reads its requests
 * as they stream in. The bar then counts without a percentage.
 */
final class SyncConsoleProgress
{
    private ?ProgressBar $bar = null;

    private int $done = 0;

    private bool $started = false;

    private float $lastLogged;

    public function __construct(
        private readonly ?SymfonyStyle $io,
        private readonly ?int $max,
        private readonly string $what
    ) {
        $this->lastLogged = microtime(true);
        if ($io === null || !$io->isDecorated()) {
            return;
        }
        $this->bar = $io->createProgressBar($max ?? 0);
        if ($max === null) {
            $this->bar->setFormat(' %current% [%bar%] %elapsed% ' . _translate('elapsed') . '  %message%');
        } else {
            $this->bar->setFormat(
                ' %current%/%max% [%bar%] %percent:3s%%  %elapsed% ' . _translate('elapsed') . ', %left%  %message%'
            );
            $this->bar->setPlaceholderFormatter('left', static fn(ProgressBar $bar): string => $bar->getProgress() === 0
                ? _translate('working out the time left')
                : sprintf(_translate('about %s left'), Helper::formatTime($bar->getRemaining())));
        }
        $this->bar->setMessage($what);
    }

    public function advance(int $step = 1): void
    {
        $this->setProgress($this->done + $step);
    }

    public function setProgress(int $done): void
    {
        $this->done = $done;
        if ($this->bar !== null) {
            // Drawn from the first record on: nothing to work through draws nothing.
            if (!$this->started) {
                $this->bar->start();
                $this->started = true;
            }
            $this->bar->setProgress($done);
            return;
        }
        if ($this->io === null || microtime(true) - $this->lastLogged < SyncConsoleWait::LOG_SECONDS) {
            return;
        }
        $this->lastLogged = microtime(true);
        $this->io->text($this->max === null
            ? sprintf(_translate('%s: %d so far'), $this->what, $done)
            : sprintf(_translate('%s: %d of %d'), $this->what, $done, $this->max));
    }

    /** What the bar is doing now, such as the step it waits on. */
    public function message(string $message): void
    {
        $this->bar?->setMessage($message);
        $this->draw();
    }

    /** Takes the bar off the screen, so a line can be printed above it. */
    public function clear(): void
    {
        if ($this->started) {
            $this->bar?->clear();
        }
    }

    /** Puts the bar back after clear(). */
    public function display(): void
    {
        $this->draw();
    }

    private function draw(): void
    {
        if ($this->bar === null) {
            return;
        }
        if (!$this->started) {
            $this->bar->start();
            $this->started = true;
        }
        $this->bar->display();
    }

    /** Leaves the bar where it got to, on a line of its own. The caller prints the outcome. */
    public function finish(): void
    {
        if ($this->bar === null || !$this->started) {
            $this->bar = null;
            return;
        }
        $this->bar->setMessage('');
        $this->bar->display();
        $this->io?->newLine(2);
        $this->bar = null;
    }

    /** Whether there is a bar on the screen: a terminal. A log gets plain lines. */
    public function isLive(): bool
    {
        return $this->bar !== null;
    }
}

<?php

namespace App\Utilities;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The live line while requests to the STS are out (SyncConsole::wait()).
 *
 * track() gives each request a progress callback for Guzzle (RequestOptions::PROGRESS),
 * which curl calls while it waits, sends and receives, so the line moves even while
 * the STS is still working on its answer. Several requests sent together share one
 * line. A wait shorter than QUIET_SECONDS shows nothing, so the usual quick call adds
 * no output.
 */
final class SyncConsoleWait
{
    public const QUIET_SECONDS = 1.0;

    /** How often the line is redrawn on a terminal. */
    public const REDRAW_SECONDS = 0.5;

    /** How often a line is written to a log. */
    public const LOG_SECONDS = 15.0;

    /** @var array<string, int> */
    private array $downloaded = [];

    /** @var array<string, int> */
    private array $uploaded = [];

    /** @var array<string, int> */
    private array $toUpload = [];

    private readonly float $start;

    private float $lastShown;

    private int $drawnWidth = 0;

    private bool $shown = false;

    /**
     * @param null|\Closure(string): void $show where a terminal shows the line instead
     *     of a line of its own, such as the message of a progress bar already on screen
     * @param null|\Closure(): float $clock for tests
     */
    public function __construct(
        private readonly ?SymfonyStyle $io,
        private readonly string $label,
        private readonly ?\Closure $show = null,
        private readonly ?\Closure $clock = null
    ) {
        $this->start = $this->now();
        $this->lastShown = $this->start;
    }

    /** A Guzzle progress callback for one request of this wait. */
    public function track(string $request = ''): callable
    {
        return function ($downloadTotal, $downloaded, $uploadTotal, $uploaded) use ($request): void {
            $this->downloaded[$request] = (int) $downloaded;
            $this->uploaded[$request] = (int) $uploaded;
            $this->toUpload[$request] = (int) $uploadTotal;
            $this->tick();
        };
    }

    /** Redraws the line if it is due. track() calls it; so can a caller's own loop. */
    public function tick(): void
    {
        if ($this->io === null) {
            return;
        }
        $now = $this->now();
        if ($now - $this->start < self::QUIET_SECONDS) {
            return;
        }
        $decorated = $this->io->isDecorated();
        $every = $decorated ? self::REDRAW_SECONDS : self::LOG_SECONDS;
        if ($this->drawnWidth > 0 || !$decorated || $this->shown) {
            if ($now - $this->lastShown < $every) {
                return;
            }
        }
        $this->lastShown = $now;
        $text = ' ' . $this->text($now);
        if ($decorated && $this->show !== null) {
            ($this->show)(trim($text));
            $this->shown = true;
        } elseif ($decorated) {
            $this->io->write("\r" . str_pad($text, $this->drawnWidth));
            $this->drawnWidth = max($this->drawnWidth, strlen($text));
        } else {
            $this->io->writeln($text);
        }
    }

    /** Clears the live line, so what follows starts on a clean line. */
    public function done(): void
    {
        if ($this->io !== null && $this->drawnWidth > 0) {
            $this->io->write("\r" . str_repeat(' ', $this->drawnWidth) . "\r");
            $this->drawnWidth = 0;
        }
    }

    /** What the line says at $now: sending, receiving, or still waiting. */
    public function text(float $now): string
    {
        $seconds = (int) ($now - $this->start);
        $toUpload = array_sum($this->toUpload);
        $uploaded = array_sum($this->uploaded);
        $downloaded = array_sum($this->downloaded);
        if ($toUpload > 0 && $uploaded < $toUpload && $downloaded === 0) {
            return sprintf(
                _translate('Sending to the STS: %s of %s, %ds'),
                self::size($uploaded),
                self::size($toUpload),
                $seconds
            );
        }
        if ($downloaded > 0) {
            return sprintf(_translate('Receiving from the STS: %s, %ds'), self::size($downloaded), $seconds);
        }
        return sprintf('%s... %ds', $this->label, $seconds);
    }

    public static function size(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return max(1, (int) round($bytes / 1024)) . ' KB';
        }
        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }

    private function now(): float
    {
        return $this->clock !== null ? ($this->clock)() : microtime(true);
    }
}

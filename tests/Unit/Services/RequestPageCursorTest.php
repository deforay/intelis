<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RequestPageCursor;
use PHPUnit\Framework\TestCase;

/**
 * Where a paged pull of a window has got to: kept in a file so the same pull run
 * again resumes from its page, cleared when the window is done.
 */
final class RequestPageCursorTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/request-pages-' . getmypid() . '-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testThePullRunAgainResumesFromItsPage(): void
    {
        $key = RequestPageCursor::key('vl', '2026-08-14', null);
        (new RequestPageCursor(null, $this->file))->advance($key, '2026-08-20 10:00:00|42');

        $again = new RequestPageCursor(null, $this->file);

        self::assertSame('2026-08-20 10:00:00|42', $again->get($key));
        self::assertNull($again->get(RequestPageCursor::key('vl', '2026-08-01', null)), 'another window starts over');
        self::assertNull($again->get(RequestPageCursor::key('eid', '2026-08-14', null)));
    }

    public function testAFinishedWindowStartsOverNextTime(): void
    {
        $key = RequestPageCursor::key('vl', null, 'VL26080519MHWZ');
        $cursor = new RequestPageCursor(null, $this->file);
        $cursor->advance($key, '2026-08-20 10:00:00|42');

        $cursor->finish($key);

        self::assertNull((new RequestPageCursor(null, $this->file))->get($key));
    }

    public function testTheSameMarkerTwiceStopsThePull(): void
    {
        $cursor = new RequestPageCursor(null, $this->file);
        $key = RequestPageCursor::key('vl', '2026-08-14', null);

        self::assertTrue($cursor->advance($key, '2026-08-20 10:00:00|42'));
        self::assertFalse($cursor->advance($key, '2026-08-20 10:00:00|42'), 'else it asks for the same page forever');
    }

    /**
     * Each saved page moves the lab's last pull time, where the scheduled window
     * starts from. A window started again from there would skip the rows between
     * its last page and the new start, so the start is held until the end.
     */
    public function testAnUnfinishedScheduledWindowHoldsItsStart(): void
    {
        $key = RequestPageCursor::scheduledKey('vl', '2026-10-20');
        $cursor = new RequestPageCursor(null, $this->file);
        $cursor->begin($key);
        $cursor->advance($key, '2026-10-21 10:00:00|42');

        $nextRun = new RequestPageCursor(null, $this->file);

        self::assertSame('2026-10-20', $nextRun->heldStart('vl'));
        self::assertSame('2026-10-21 10:00:00|42', $nextRun->get($key), 'and carries on from its page');
        self::assertNull($nextRun->heldStart('eid'), 'another module starts where it would');
    }

    public function testAScheduledWindowIsHeldFromItsFirstPull(): void
    {
        // The first pull (the waiting requests) moves the last pull time too.
        $key = RequestPageCursor::scheduledKey('vl', '2026-10-20');
        (new RequestPageCursor(null, $this->file))->begin($key);

        $nextRun = new RequestPageCursor(null, $this->file);

        self::assertSame('2026-10-20', $nextRun->heldStart('vl'));
        self::assertSame('', $nextRun->get($key), 'no page yet: the window starts at its first');
    }

    public function testBeginKeepsThePageAWindowReached(): void
    {
        $key = RequestPageCursor::scheduledKey('vl', '2026-10-20');
        $cursor = new RequestPageCursor(null, $this->file);
        $cursor->advance($key, '2026-10-21 10:00:00|42');

        $cursor->begin($key);

        self::assertSame('2026-10-21 10:00:00|42', (new RequestPageCursor(null, $this->file))->get($key));
    }

    public function testAFinishedScheduledWindowHoldsNothing(): void
    {
        $key = RequestPageCursor::scheduledKey('vl', '2026-10-20');
        $cursor = new RequestPageCursor(null, $this->file);
        $cursor->begin($key);
        $cursor->advance($key, '2026-10-21 10:00:00|42');

        $cursor->finish($key);

        self::assertNull((new RequestPageCursor(null, $this->file))->heldStart('vl'));
    }

    public function testAWindowSomeoneAskedForHoldsNoScheduledStart(): void
    {
        $cursor = new RequestPageCursor(null, $this->file);
        $cursor->advance(RequestPageCursor::key('vl', '2026-08-14', null), '2026-08-20 10:00:00|42');

        self::assertNull((new RequestPageCursor(null, $this->file))->heldStart('vl'));
    }

    public function testADryRunKeepsNothing(): void
    {
        $cursor = new RequestPageCursor();
        $key = RequestPageCursor::key('vl', '2026-08-14', null);

        self::assertTrue($cursor->advance($key, '2026-08-20 10:00:00|42'));
        self::assertSame('2026-08-20 10:00:00|42', $cursor->get($key), 'it still pages');
        self::assertFileDoesNotExist($this->file);
    }
}

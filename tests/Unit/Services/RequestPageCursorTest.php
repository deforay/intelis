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
        (new RequestPageCursor($this->file))->advance($key, '2026-08-20 10:00:00|42');

        $again = new RequestPageCursor($this->file);

        self::assertSame('2026-08-20 10:00:00|42', $again->get($key));
        self::assertNull($again->get(RequestPageCursor::key('vl', '2026-08-01', null)), 'another window starts over');
        self::assertNull($again->get(RequestPageCursor::key('eid', '2026-08-14', null)));
    }

    public function testAFinishedWindowStartsOverNextTime(): void
    {
        $key = RequestPageCursor::key('vl', null, 'VL26080519MHWZ');
        $cursor = new RequestPageCursor($this->file);
        $cursor->advance($key, '2026-08-20 10:00:00|42');

        $cursor->finish($key);

        self::assertNull((new RequestPageCursor($this->file))->get($key));
    }

    public function testTheSameMarkerTwiceStopsThePull(): void
    {
        $cursor = new RequestPageCursor($this->file);
        $key = RequestPageCursor::key('vl', '2026-08-14', null);

        self::assertTrue($cursor->advance($key, '2026-08-20 10:00:00|42'));
        self::assertFalse($cursor->advance($key, '2026-08-20 10:00:00|42'), 'else it asks for the same page forever');
    }

    public function testADryRunKeepsNothing(): void
    {
        $cursor = new RequestPageCursor(null);
        $key = RequestPageCursor::key('vl', '2026-08-14', null);

        self::assertTrue($cursor->advance($key, '2026-08-20 10:00:00|42'));
        self::assertSame('2026-08-20 10:00:00|42', $cursor->get($key), 'it still pages');
        self::assertFileDoesNotExist($this->file);
    }
}

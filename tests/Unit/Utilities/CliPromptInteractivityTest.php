<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\CliPromptUtility;
use PHPUnit\Framework\TestCase;

/**
 * The one thing about the prompt layer that a scheduled run depends on.
 *
 * bin/interface.php now offers to run the setup wizard when interfacing is not
 * configured, and that offer is gated on this method alone. Getting it wrong in
 * the permissive direction is not a cosmetic fault: crunz runs that import every
 * minute, and a prompt in a cron slot blocks until something kills it, having
 * imported nothing -- every minute, from then on. So both halves of the gate are
 * pinned here rather than left to be re-derived.
 */
final class CliPromptInteractivityTest extends TestCase
{
    private string|false $previous;

    protected function setUp(): void
    {
        $this->previous = getenv('INTELIS_NONINTERACTIVE');
    }

    protected function tearDown(): void
    {
        if ($this->previous === false) {
            putenv('INTELIS_NONINTERACTIVE');
        } else {
            putenv('INTELIS_NONINTERACTIVE=' . $this->previous);
        }
    }

    public function testSetupDrivenRunsAreNeverAskedAQuestion(): void
    {
        putenv('INTELIS_NONINTERACTIVE=1');

        $this->assertFalse(
            CliPromptUtility::isInteractive(),
            'INTELIS_NONINTERACTIVE=1 means nobody is watching, terminal or not.'
        );
    }

    public function testAPipedRunIsNotInteractiveEvenWithoutTheFlag(): void
    {
        putenv('INTELIS_NONINTERACTIVE');

        // A pipe on stdin is exactly the shape a cron run has: no flag set, and
        // no one there. PHPUnit's own stdin cannot stand in for it, because it is
        // a terminal whenever the suite is run by hand.
        $pipe = fopen('php://memory', 'r');

        try {
            $this->assertFalse(
                CliPromptUtility::isInteractive($pipe),
                'Without a terminal on stdin there is nobody to answer, flag or no flag.'
            );
        } finally {
            fclose($pipe);
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Services\SampleStatusDetailsService;
use App\Utilities\ExportJobUtility;
use PHPUnit\Framework\TestCase;

/**
 * A background export runs in a worker with only the slice of the requester's
 * session that ExportJobUtility carries over. Whatever an export checks to
 * authorize the user has to be in that slice, or the worker refuses a user the
 * page let in.
 */
final class ExportJobSessionTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $this->savedSession = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->savedSession;
    }

    public function testADashboardUserCanStillExportSampleStatusInTheWorker(): void
    {
        // Sees VL on the dashboard, has no Sample Status Report privilege
        $_SESSION = [
            'userId' => 'u-1',
            'roleId' => 2,
            'privileges' => ['index.php' => 'index.php'],
            'modules' => ['vl' => 'vl'],
            'unrelated' => 'left behind',
        ];
        $this->assertTrue(SampleStatusDetailsService::canView('vl'), 'The page lets this user in');

        $_SESSION = ExportJobUtility::sessionSnapshot();

        $this->assertTrue(SampleStatusDetailsService::canView('vl'), 'The worker must let the same user in');
        $this->assertFalse(SampleStatusDetailsService::canView('eid'));
        $this->assertArrayNotHasKey('unrelated', $_SESSION);
    }

    public function testTheExportsOwnEntriesAreCarried(): void
    {
        $_SESSION = ['userId' => 'u-1', 'highViralResult' => 'SELECT 1', 'other' => 'x'];

        $snapshot = ExportJobUtility::sessionSnapshot(['highViralResult']);

        $this->assertSame('SELECT 1', $snapshot['highViralResult']);
        $this->assertArrayNotHasKey('other', $snapshot);
    }
}

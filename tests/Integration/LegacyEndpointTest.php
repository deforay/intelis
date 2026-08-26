<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\RedirectException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Throwable;
use Tests\Support\LegacyAppHarness;

/**
 * Driving one of the procedural pages under app/ end to end.
 *
 * These files are the write paths, and nothing has ever tested one. They are not
 * functions, so the only way to run one is the way the application runs it:
 * LegacyRequestHandler requires the file inside a closure with $db and $general in
 * scope and output buffered. This drives that handler directly -- no HTTP, no Slim,
 * no middleware -- against a real database.
 *
 * A page ends in MiscUtility::redirect(), which under CLI throws RedirectException
 * rather than exiting. That is what lets a test see the whole outcome: the rows
 * written, the message left in the session, and where the user was sent, which for
 * these pages is a decision in its own right.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class LegacyEndpointTest extends TestCase
{
    private const DATABASE = 'intelis_endpoint_test';

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    protected function setUp(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        LegacyAppHarness::boot(self::DATABASE, ['r_vl_test_reasons', 'activity_log']);
        LegacyAppHarness::withSession();
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /**
     * @return array{redirect: string|null, db: DatabaseService}
     */
    private function drive(string $path, array $post): array
    {
        $request = LegacyAppHarness::withPost($post, $path);

        $db = LegacyAppHarness::db();
        $handler = new LegacyRequestHandler($db, ContainerRegistry::get(CommonService::class));

        $redirect = null;
        try {
            $handler->handle($request);
        } catch (Throwable $e) {
            // The handler wraps anything a page throws in a SystemException, so the
            // redirect arrives as the cause rather than at the top. Anything else is
            // a genuine failure and belongs in the test result, not swallowed here.
            $redirect = self::redirectWithin($e)?->getUrl()
                ?? throw $e;
        }

        return ['redirect' => $redirect, 'db' => $db];
    }

    private static function redirectWithin(?Throwable $e): ?RedirectException
    {
        while ($e instanceof Throwable) {
            if ($e instanceof RedirectException) {
                return $e;
            }
            $e = $e->getPrevious();
        }

        return null;
    }

    #[RunInSeparateProcess]
    public function testSavingANewReferenceRowWritesItAndSendsTheUserBack(): void
    {
        ['redirect' => $redirect, 'db' => $db] = $this->drive(
            '/vl/reference/save-vl-test-reasons-helper.php',
            [
                'testReasonName' => 'Suspected treatment failure',
                'testReasonStatus' => 'active',
            ]
        );

        $row = $db->rawQueryOne('SELECT * FROM r_vl_test_reasons ORDER BY test_reason_id DESC LIMIT 1');

        self::assertSame('Suspected treatment failure', $row['test_reason_name'] ?? null);
        self::assertSame('active', $row['test_reason_status'] ?? null);
        // A new row is created unsynced, so the STS picks it up on the next sync.
        self::assertSame(0, (int) ($row['data_sync'] ?? -1));
        self::assertSame('/vl/reference/vl-test-reasons.php', $redirect);
        self::assertNotEmpty($_SESSION['alertMsg'] ?? '');
    }

    /**
     * The page decides between insert and update on whether an id came in, and the id
     * arrives base64 encoded. Getting that wrong inserts a duplicate instead of
     * editing, which is invisible until a reference list has two of everything.
     */
    #[RunInSeparateProcess]
    public function testSavingWithAnIdUpdatesInPlaceRatherThanInserting(): void
    {
        // Seeded rather than saved through the page first, because
        // LegacyRequestHandler requires a page with require_once: driving the same
        // one twice in a process silently does nothing the second time. One drive
        // per test, and the starting state is set up around it.
        $db = LegacyAppHarness::db();
        $db->insert('r_vl_test_reasons', [
            'test_reason_name' => 'Routine monitoring',
            'test_reason_status' => 'active',
            'parent_reason' => 0,
            'data_sync' => 1,
        ]);
        $id = (int) $db->getInsertId();
        self::assertGreaterThan(0, $id);

        $this->drive('/vl/reference/save-vl-test-reasons-helper.php', [
            'testReasonId' => base64_encode((string) $id),
            'testReasonName' => 'Routine monitoring (revised)',
            'testReasonStatus' => 'inactive',
        ]);

        $rows = $db->rawQuery('SELECT * FROM r_vl_test_reasons');
        self::assertCount(1, $rows, 'an edit must not leave a second row behind');
        self::assertSame('Routine monitoring (revised)', $rows[0]['test_reason_name']);
        self::assertSame('inactive', $rows[0]['test_reason_status']);
    }

    /**
     * An empty name writes nothing, and still redirects. Worth pinning: the page
     * decides this silently, so a change that started inserting blank reference rows
     * would show up nowhere else.
     */
    #[RunInSeparateProcess]
    public function testAnEmptyNameWritesNothingButStillRedirects(): void
    {
        ['redirect' => $redirect, 'db' => $db] = $this->drive(
            '/vl/reference/save-vl-test-reasons-helper.php',
            ['testReasonName' => '   ', 'testReasonStatus' => 'active']
        );

        self::assertSame([], $db->rawQuery('SELECT * FROM r_vl_test_reasons'));
        self::assertSame('/vl/reference/vl-test-reasons.php', $redirect);
    }

    /** The save is recorded for audit, against the user in session. */
    #[RunInSeparateProcess]
    public function testTheSaveIsRecordedInTheActivityLog(): void
    {
        ['db' => $db] = $this->drive('/vl/reference/save-vl-test-reasons-helper.php', [
            'testReasonName' => 'Clinical failure',
            'testReasonStatus' => 'active',
        ]);

        $log = $db->rawQueryOne('SELECT * FROM activity_log ORDER BY log_id DESC LIMIT 1');

        self::assertSame('VL Test Reason details', $log['event_type'] ?? null);
        self::assertSame('vl-reference', $log['resource'] ?? null);
        self::assertStringContainsString('Clinical failure', (string) ($log['action'] ?? ''));
        self::assertSame('1', (string) ($log['user_id'] ?? ''));
    }
}

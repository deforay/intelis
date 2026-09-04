<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REJECTED;

/**
 * The bulk status grid, driven against a real table.
 *
 * Accepted is what the printing, emailing and dispatch queries all read as
 * "this sample has a result", so a row holding the status without a result
 * drops out of every one of them. This endpoint set the status from the request
 * with nothing checked, which is how a selection that included samples still
 * awaiting a result left them marked finished.
 *
 * What is pinned here is the shape of the answer as much as the guard: a
 * selection is a list of separate patients, so the samples that can take the
 * status get it and the ones that cannot are named back to the user. Refusing
 * the whole selection would hold up everyone else's result for the sake of one.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class BulkStatusGuardTest extends TestCase
{
    private const DATABASE = 'intelis_bulk_status_test';

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

        LegacyAppHarness::boot(self::DATABASE, ['form_eid', 'activity_log']);
        LegacyAppHarness::withSession();
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @return int the new row's eid_id */
    private function seedSample(string $sampleCode, ?string $result): int
    {
        $db = LegacyAppHarness::db();
        $db->insert('form_eid', [
            'sample_code' => $sampleCode,
            'result' => $result,
            'result_status' => RECEIVED_AT_TESTING_LAB,
            'data_sync' => 1,
        ]);

        return (int) $db->getInsertId();
    }

    /** @return array<string, mixed> the endpoint's decoded reply */
    private function drive(array $post): array
    {
        $request = LegacyAppHarness::withPost($post, '/eid/results/update-status.php');
        $handler = new LegacyRequestHandler(
            LegacyAppHarness::db(),
            ContainerRegistry::get(CommonService::class)
        );

        $body = (string) $handler->handle($request)->getBody();

        return json_decode($body, true) ?? [];
    }

    private function statusOf(int $id): int
    {
        return (int) LegacyAppHarness::db()
            ->rawQueryOne('SELECT result_status FROM form_eid WHERE eid_id = ?', [$id])['result_status'];
    }

    /**
     * The whole point of the change: one sample without a result does not cost
     * the others in the selection their status change.
     */
    #[RunInSeparateProcess]
    public function testAcceptingAMixedSelectionAcceptsOnlyTheSamplesHoldingAResult(): void
    {
        $resulted = $this->seedSample('EID0001', 'Positive');
        $empty = $this->seedSample('EID0002', null);
        $blank = $this->seedSample('EID0003', '   ');

        $reply = $this->drive([
            'id' => implode(',', [$resulted, $empty, $blank]),
            'status' => (string) ACCEPTED,
        ]);

        self::assertSame(ACCEPTED, $this->statusOf($resulted));
        self::assertSame(RECEIVED_AT_TESTING_LAB, $this->statusOf($empty));
        // Whitespace is not a result: TRIM is what every reporting query applies.
        self::assertSame(RECEIVED_AT_TESTING_LAB, $this->statusOf($blank));

        self::assertSame(1, $reply['updated'] ?? null);
        self::assertSame(['EID0002', 'EID0003'], $reply['skipped'] ?? null);
    }

    /** Named, not counted: a count is not something anyone can go and act on. */
    #[RunInSeparateProcess]
    public function testTheReplyNamesTheSamplesItCouldNotAccept(): void
    {
        $empty = $this->seedSample('EID0002', null);

        $reply = $this->drive(['id' => (string) $empty, 'status' => (string) ACCEPTED]);

        self::assertStringContainsString('EID0002', $reply['message'] ?? '');
        self::assertSame(0, $reply['updated'] ?? null);
    }

    /**
     * The guard is about Accepted alone. Rejecting a sample that never produced a
     * result is ordinary lab work, and blocking it would be a worse bug than the
     * one being fixed.
     */
    #[RunInSeparateProcess]
    public function testASampleWithNoResultCanStillBeRejected(): void
    {
        $empty = $this->seedSample('EID0002', null);

        $reply = $this->drive([
            'id' => (string) $empty,
            'status' => (string) REJECTED,
            'rejectedReason' => '1',
        ]);

        self::assertSame(REJECTED, $this->statusOf($empty));
        self::assertSame([], $reply['skipped'] ?? null);
    }

    /**
     * A sample that already holds a result keeps taking the status it always did,
     * so the guard cannot have narrowed ordinary approval.
     */
    #[RunInSeparateProcess]
    public function testASelectionThatAllHoldResultsIsAcceptedWhole(): void
    {
        $first = $this->seedSample('EID0001', 'Positive');
        $second = $this->seedSample('EID0004', 'Negative');

        $reply = $this->drive([
            'id' => implode(',', [$first, $second]),
            'status' => (string) ACCEPTED,
        ]);

        self::assertSame(ACCEPTED, $this->statusOf($first));
        self::assertSame(ACCEPTED, $this->statusOf($second));
        self::assertSame(2, $reply['updated'] ?? null);
        self::assertSame([], $reply['skipped'] ?? null);
    }
}

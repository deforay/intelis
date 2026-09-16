<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\MiscUtility;
use App\Utilities\ApiTrackingStorageUtility;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * What addApiTracking() keeps for a call: the track_api_requests row, and the
 * request and response bodies under var/track-api.
 *
 * Every lab asks the STS for requests, commands, metadata and a token on every
 * sync run, whether or not there is anything to hand over. Writing a row and two
 * files for each of those filled an STS serving 41 labs with 14,000 files an hour
 * and kept its disks busy most of the time. These tests hold the rules that stop
 * that: an empty poll leaves nothing unless asked to keep its row, and a body
 * that is empty once encoded is never written.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiTrackingStorageTest extends TestCase
{
    private const DATABASE = 'intelis_api_tracking_test';

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
        LegacyAppHarness::boot(self::DATABASE, ['track_api_requests']);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    public function testCallThatMovedDataKeepsRowAndBothBodies(): void
    {
        $txn = $this->track(['labId' => 7], ['requests' => [['sample_code' => 'S1']]]);

        self::assertSame(1, $this->rowCount($txn));
        self::assertTrue($this->bodyExists('requests', $txn));
        self::assertTrue($this->bodyExists('responses', $txn));
    }

    public function testEmptyPollLeavesNothing(): void
    {
        $txn = $this->track(['labId' => 7], ['requests' => []], emptyPoll: true);

        self::assertSame(0, $this->rowCount($txn));
        self::assertFalse($this->bodyExists('requests', $txn));
        self::assertFalse($this->bodyExists('responses', $txn));
    }

    public function testEmptyPollCanKeepItsRowWithoutBodies(): void
    {
        $txn = $this->track(['labId' => 7], ['requests' => []], emptyPoll: true, keepRow: true);

        self::assertSame(1, $this->rowCount($txn));
        self::assertFalse($this->bodyExists('requests', $txn));
        self::assertFalse($this->bodyExists('responses', $txn));
    }

    public function testEmptyBodiesAreNotWritten(): void
    {
        $txn = $this->track('{}', null);

        self::assertSame(1, $this->rowCount($txn));
        self::assertFalse($this->bodyExists('requests', $txn));
        self::assertFalse($this->bodyExists('responses', $txn));
    }

    #[RunInSeparateProcess]
    public function testAllCaptureRecordsEmptyPolls(): void
    {
        define('SYSTEM_CONFIG', ['system' => ['api_tracking_bodies' => 'all']]);

        $txn = $this->track(['labId' => 7], ['commands' => [], 'status' => 'success'], emptyPoll: true);

        self::assertSame(1, $this->rowCount($txn));
        self::assertTrue($this->bodyExists('requests', $txn));
        self::assertTrue($this->bodyExists('responses', $txn));
    }

    #[RunInSeparateProcess]
    public function testOffKeepsRowsButNoBodies(): void
    {
        define('SYSTEM_CONFIG', ['system' => ['api_tracking_bodies' => 'off']]);

        $txn = $this->track(['labId' => 7], ['requests' => [['sample_code' => 'S1']]]);

        self::assertSame(1, $this->rowCount($txn));
        self::assertFalse($this->bodyExists('requests', $txn));
        self::assertFalse($this->bodyExists('responses', $txn));
    }

    private function track(mixed $request, mixed $response, bool $emptyPoll = false, bool $keepRow = false): string
    {
        /** @var CommonService $general */
        $general = ContainerRegistry::get(CommonService::class);
        $txn = MiscUtility::generateULID();

        $general->addApiTracking(
            $txn,
            'intelis-system',
            0,
            'requests',
            'vl',
            '/remote/v2/requests.php',
            $request,
            $response,
            'json',
            7,
            emptyPoll: $emptyPoll,
            keepRow: $keepRow
        );

        return $txn;
    }

    private function rowCount(string $txn): int
    {
        return (int) LegacyAppHarness::db()->rawQueryOne(
            'SELECT COUNT(*) AS n FROM track_api_requests WHERE transaction_id = ?',
            [$txn]
        )['n'];
    }

    private function bodyExists(string $folder, string $txn): bool
    {
        $requestedOn = LegacyAppHarness::db()->rawQueryOne(
            'SELECT requested_on FROM track_api_requests WHERE transaction_id = ?',
            [$txn]
        )['requested_on'] ?? date('Y-m-d H:i:s');

        $pattern = ApiTrackingStorageUtility::dayDirectory($folder, (string) $requestedOn)
            . DIRECTORY_SEPARATOR . $txn . '.json*';
        return glob($pattern) !== [];
    }
}

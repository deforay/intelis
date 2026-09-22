<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\STS\RequestReceiptsService;
use InvalidArgumentException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Throwable;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * A lab's receipt for the requests it pulled: saved ones confirmed, failed ones
 * back to pending and recorded, and only within the lab's own scope.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class RequestReceiptsTest extends TestCase
{
    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    /** The table exists only in the migration, which this also checks runs. */
    private static function createFailuresTable($db): void
    {
        $migration = (string) file_get_contents(ROOT_PATH . '/sys/migrations/5.7.75.sql');
        preg_match('/^CREATE TABLE IF NOT EXISTS `request_sync_failures` \(.*?^\) ENGINE=[^;]*;/ms', $migration, $sql);
        self::assertNotEmpty($sql, 'migration creates request_sync_failures');
        $db->rawQuery($sql[0]);
    }

    private function boot(array $extraTables = []): mixed
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot(
            'intelis_request_receipts_test_' . getmypid(),
            ['r_sample_status', 'form_vl', ...$extraTables]
        );
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Registered')");
        self::createFailuresTable($db);

        // Lab 7 pulls its own requests and those of facility 30, which it serves.
        $rows = [
            1 => ['u-1', 7, 10],
            2 => ['u-2', 7, 10],
            3 => ['u-3', 7, 10],
            4 => ['u-4', 8, 30],
            5 => ['u-5', 8, 11],
        ];
        foreach ($rows as $id => [$uniqueId, $labId, $facilityId]) {
            $db->insert('form_vl', [
                'vl_sample_id' => $id, 'unique_id' => $uniqueId, 'lab_id' => $labId, 'facility_id' => $facilityId,
                'data_sync' => RequestReceiptsService::IN_FLIGHT, 'vlsm_instance_id' => 'sts', 'result_status' => 6,
            ]);
        }
        return $db;
    }

    /** @return array<string, int> */
    private static function syncState($db): array
    {
        return array_map(
            'intval',
            array_column(
                $db->rawQuery('SELECT unique_id, data_sync FROM form_vl ORDER BY vl_sample_id'),
                'data_sync',
                'unique_id'
            )
        );
    }

    #[RunInSeparateProcess]
    public function testSavedRequestsAreConfirmedOnlyWhileStillInFlightAndInScope(): void
    {
        $db = $this->boot();
        // Edited on the STS while the request was out.
        $db->rawQuery('UPDATE form_vl SET data_sync = 0 WHERE unique_id = ?', ['u-2']);

        $counts = (new RequestReceiptsService($db))->apply(7, 'vl', ['u-1', 'u-2', 'u-4', 'u-5'], [], '30');

        self::assertSame(['confirmed' => 2, 'failed' => 0], $counts);
        self::assertSame(
            ['u-1' => 1, 'u-2' => 0, 'u-3' => 2, 'u-4' => 1, 'u-5' => 2],
            self::syncState($db),
            'u-2 keeps its edit pending, u-3 was not in the receipt, u-5 belongs to another lab'
        );
    }

    #[RunInSeparateProcess]
    public function testFailedRequestsGoBackToPendingAndAreRecordedUntilTheySave(): void
    {
        $db = $this->boot();
        $service = new RequestReceiptsService($db);

        $counts = $service->apply(7, 'vl', ['u-1'], [
            ['unique_id' => 'u-2', 'reason' => 'duplicate sample code'],
            ['unique_id' => 'u-1', 'reason' => 'listed twice'],
        ]);

        self::assertSame(['confirmed' => 1, 'failed' => 1], $counts, 'a request both saved and failed was saved');
        self::assertSame(0, self::syncState($db)['u-2'], 'sent again on the next pull');
        $failure = $db->rawQueryOne("SELECT * FROM request_sync_failures WHERE unique_id = 'u-2'");
        self::assertSame('duplicate sample code', $failure['reason']);
        self::assertSame(1, (int) $failure['attempts']);
        self::assertNull($db->rawQueryOne("SELECT * FROM request_sync_failures WHERE unique_id = 'u-1'"));

        // It fails again on the next pull, then saves on the one after.
        $db->rawQuery(
            'UPDATE form_vl SET data_sync = ? WHERE unique_id = ?',
            [RequestReceiptsService::IN_FLIGHT, 'u-2']
        );
        $service->apply(7, 'vl', [], [['unique_id' => 'u-2', 'reason' => 'still duplicate']]);
        $failure = $db->rawQueryOne("SELECT * FROM request_sync_failures WHERE unique_id = 'u-2'");
        self::assertSame(2, (int) $failure['attempts']);
        self::assertSame('still duplicate', $failure['reason']);

        $db->rawQuery(
            'UPDATE form_vl SET data_sync = ? WHERE unique_id = ?',
            [RequestReceiptsService::IN_FLIGHT, 'u-2']
        );
        $service->apply(7, 'vl', ['u-2'], []);
        self::assertSame(1, self::syncState($db)['u-2']);
        self::assertNull($db->rawQueryOne("SELECT * FROM request_sync_failures WHERE unique_id = 'u-2'"));
    }

    #[RunInSeparateProcess]
    public function testAFailureForARequestAlreadyConfirmedIsNotRecorded(): void
    {
        $db = $this->boot();
        $service = new RequestReceiptsService($db);

        // Two receipts for the same pull reach the STS out of order: the one that
        // saved it (sent on a retry) first, then the older one from the outbox that
        // said it failed.
        $service->apply(7, 'vl', ['u-1'], []);
        $counts = $service->apply(7, 'vl', [], [['unique_id' => 'u-1', 'reason' => 'old news']]);

        self::assertSame(['confirmed' => 0, 'failed' => 0], $counts);
        self::assertSame(1, self::syncState($db)['u-1'], 'still confirmed');
        self::assertNull(
            $db->rawQueryOne("SELECT * FROM request_sync_failures WHERE unique_id = 'u-1'"),
            'not flagged as needing attention'
        );
    }

    #[RunInSeparateProcess]
    public function testAFailureForAnotherLabsRequestIsNotRecorded(): void
    {
        $db = $this->boot();

        $counts = (new RequestReceiptsService($db))->apply(7, 'vl', [], [['unique_id' => 'u-5', 'reason' => 'x']]);

        self::assertSame(['confirmed' => 0, 'failed' => 0], $counts);
        self::assertSame(2, self::syncState($db)['u-5']);
        self::assertNull($db->rawQueryOne('SELECT * FROM request_sync_failures'));
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private static function post(array $body, ?string $token): array
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/remote/v2/request-receipts.php')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('User-Agent', 'intelis-tests')
            ->withBody((new StreamFactory())->createStream((string) json_encode($body)));
        if ($token !== null) {
            $request = $request->withHeader('Authorization', "Bearer $token");
        }
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        try {
            $response = $handler->handle($request);
            return ['status' => 200, 'body' => json_decode((string) $response->getBody(), true) ?? []];
        } catch (Throwable $e) {
            return ['status' => (int) $e->getCode(), 'body' => ['error' => $e->getMessage()]];
        }
    }

    private const RECEIPT = [
        'labId' => 7, 'testType' => 'vl', 'saved' => ['u-1'], 'failed' => [['unique_id' => 'u-3', 'reason' => 'x']],
    ];

    /** The handler loads the page with require_once, so each test makes one call. */
    private function bootEndpoint(): mixed
    {
        $db = $this->boot([
            'facility_details', 'testing_lab_health_facilities_map', 'track_api_requests', 'global_config',
            'system_config', 's_vlsm_instance',
        ]);
        $db->insert('facility_details', [
            'facility_id' => 7, 'facility_name' => 'Lab Seven', 'facility_type' => 2, 'status' => 'active',
            'sts_token' => 'lab-seven-token', 'sts_token_expiry' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        return $db;
    }

    #[RunInSeparateProcess]
    public function testTheEndpointAppliesAReceiptSentWithTheLabsToken(): void
    {
        $db = $this->bootEndpoint();

        $response = self::post(self::RECEIPT, 'lab-seven-token');

        self::assertSame(200, $response['status'], (string) json_encode($response['body']));
        self::assertSame(['status' => 'success', 'confirmed' => 1, 'failed' => 1], $response['body']);
        self::assertSame(1, self::syncState($db)['u-1']);
        self::assertSame(0, self::syncState($db)['u-3']);
    }

    #[RunInSeparateProcess]
    public function testTheEndpointRejectsAWrongToken(): void
    {
        $db = $this->bootEndpoint();

        self::assertSame(401, self::post(self::RECEIPT, 'wrong-token')['status']);
        self::assertSame(2, self::syncState($db)['u-1'], 'nothing applied');
    }

    #[RunInSeparateProcess]
    public function testTheEndpointRejectsAMissingToken(): void
    {
        $db = $this->bootEndpoint();

        self::assertSame(401, self::post(self::RECEIPT, null)['status']);
        self::assertSame(2, self::syncState($db)['u-1'], 'nothing applied');
    }

    #[RunInSeparateProcess]
    public function testTheEndpointRejectsAReceiptWithoutALab(): void
    {
        $db = $this->bootEndpoint();

        self::assertSame(400, self::post(['labId' => null] + self::RECEIPT, 'lab-seven-token')['status']);
        self::assertSame(2, self::syncState($db)['u-1'], 'nothing applied');
    }

    #[RunInSeparateProcess]
    public function testAnUnknownTestTypeOrAnOversizedReceiptIsRejected(): void
    {
        $db = $this->boot();
        $service = new RequestReceiptsService($db);

        try {
            $service->apply(7, 'not-a-test', ['u-1'], []);
            self::fail('unknown test type accepted');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $tooMany = array_map(static fn(int $i): string => "id-$i", range(1, RequestReceiptsService::MAX_IDS + 1));
        $service->apply(7, 'vl', $tooMany, []);
    }
}

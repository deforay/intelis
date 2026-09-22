<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\ApiService;
use App\Services\CommonService;
use App\Services\RequestReceiptsClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The lab side of request receipts: a receipt the STS takes is gone, one it
 * cannot take right now waits in the outbox for the next run, and one it refuses
 * is not kept.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class RequestReceiptsClientTest extends TestCase
{
    private bool $booted = false;

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param list<int> $statuses the STS's answers, in order */
    private function client(array $statuses): RequestReceiptsClient
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot(
            'intelis_request_receipts_client_test_' . getmypid(),
            ['global_config', 's_vlsm_instance', 'system_config']
        );
        $this->booted = true;
        $migration = (string) file_get_contents(ROOT_PATH . '/sys/migrations/5.7.75.sql');
        $pattern = '/^CREATE TABLE IF NOT EXISTS `request_receipt_outbox` \(.*?^\) ENGINE=[^;]*;/ms';
        preg_match($pattern, $migration, $sql);
        $db->rawQuery($sql[0]);

        $stack = HandlerStack::create(new MockHandler(array_map(
            static fn(int $status): Response => new Response($status, [], '{}'),
            $statuses
        )));
        $stack->push(Middleware::mapRequest(function ($request) {
            $body = (string) $request->getBody();
            if (str_starts_with($body, "\x1f\x8b")) {
                $body = (string) gzdecode($body);
            }
            $this->sent[] = json_decode($body, true);
            return $request;
        }));
        $api = new ApiService(
            ContainerRegistry::get(CommonService::class),
            new Client(['handler' => $stack, 'http_errors' => true]),
            maxRetries: 0
        );
        return new RequestReceiptsClient($db, $api);
    }

    private static function outboxCount(): int
    {
        $row = LegacyAppHarness::db()->rawQueryOne('SELECT COUNT(*) AS n FROM request_receipt_outbox');
        return (int) ($row['n'] ?? 0);
    }

    #[RunInSeparateProcess]
    public function testADeliveredReceiptIsNotKept(): void
    {
        $client = $this->client([200]);

        $delivered = $client->send('https://sts.test', 7, 'vl', ['u-1'], [['unique_id' => 'u-2', 'reason' => 'x']]);

        self::assertTrue($delivered);
        self::assertSame(0, self::outboxCount());
        self::assertSame(
            [[
                'labId' => 7, 'testType' => 'vl',
                'saved' => ['u-1'], 'failed' => [['unique_id' => 'u-2', 'reason' => 'x']],
            ]],
            $this->sent
        );
    }

    #[RunInSeparateProcess]
    public function testAnUndeliveredReceiptWaitsForTheNextRun(): void
    {
        // Down for the receipt and for the first retry, back for the second.
        $client = $this->client([503, 503, 200]);

        self::assertFalse($client->send('https://sts.test', 7, 'vl', ['u-1'], []));
        self::assertSame(1, self::outboxCount());

        self::assertSame(0, $client->flushOutbox('https://sts.test'));
        $row = LegacyAppHarness::db()->rawQueryOne('SELECT attempts FROM request_receipt_outbox');
        self::assertSame(1, (int) $row['attempts']);

        self::assertSame(1, $client->flushOutbox('https://sts.test'));
        self::assertSame(0, self::outboxCount());
        self::assertSame(['u-1'], $this->sent[2]['saved']);
    }

    #[RunInSeparateProcess]
    public function testAReceiptTheStsRefusesIsNotKept(): void
    {
        $client = $this->client([400]);

        self::assertFalse($client->send('https://sts.test', 7, 'vl', ['u-1'], []));
        self::assertSame(0, self::outboxCount(), 'sending it again would get the same answer');
    }

    public function testALargeReceiptIsSplitBelowTheStsCap(): void
    {
        $count = RequestReceiptsClient::MAX_IDS_PER_RECEIPT + 10;
        $saved = array_map(static fn(int $i): string => "u-$i", range(1, $count));
        $failed = [['unique_id' => 'f-1', 'reason' => 'x']];

        $receipts = RequestReceiptsClient::split(7, 'vl', $saved, $failed);

        self::assertCount(2, $receipts);
        self::assertCount(RequestReceiptsClient::MAX_IDS_PER_RECEIPT, $receipts[0]['saved']);
        self::assertSame([], $receipts[0]['failed']);
        self::assertCount(10, $receipts[1]['saved']);
        self::assertSame($failed, $receipts[1]['failed']);
    }
}

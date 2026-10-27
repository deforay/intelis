<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\STS\RequestsService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;

/**
 * The whole window, which every lab that does not page asks for, written out as
 * it is read (STS\RequestsService::writeRequests).
 *
 * A lab's window of 31,000 rows ran the STS out of its 512 MB: every row was held
 * in memory, then encoded more than once. The lab got a 500 on every pull until
 * the window moved past those rows. The answer now costs about one chunk of rows,
 * whatever the window holds, and is the same JSON the lab has always read.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class RequestWindowStreamingTest extends TestCase
{
    private const SINCE = '2026-08-01';

    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(): mixed
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        require_once ROOT_PATH . '/app/system/version.php';
        $db = LegacyAppHarness::boot('intelis_request_streaming_test_' . getmypid(), [
            'r_sample_status', 'form_vl', 'form_tb', 'tb_tests', 'facility_details',
            'testing_lab_health_facilities_map', 'track_api_requests', 'global_config', 'system_config',
            's_vlsm_instance',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Registered')");
        $db->insert('global_config', [
            'display_name' => 'Sync interval', 'name' => 'data_sync_interval', 'value' => '30',
        ]);
        $db->insert('facility_details', [
            'facility_id' => 7, 'facility_name' => 'Lab Seven', 'facility_type' => 2, 'status' => 'active',
            'sts_token' => 'lab-seven-token', 'sts_token_expiry' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        return $db;
    }

    /** @param array<string, mixed> $extra */
    private static function vl(int $id, string $modified = '2026-08-05 10:00:00', array $extra = []): void
    {
        LegacyAppHarness::db()->insert('form_vl', $extra + [
            'vl_sample_id' => $id, 'unique_id' => "u-$id", 'sample_code' => "S-$id", 'lab_id' => 7,
            'facility_id' => 10 + ($id % 3), 'data_sync' => 0, 'last_modified_datetime' => $modified,
            'vlsm_instance_id' => 'sts', 'result_status' => 6, 'patient_first_name' => "Émilie $id",
        ]);
    }

    private static function tb(int $id): void
    {
        $db = LegacyAppHarness::db();
        $db->insert('form_tb', [
            'tb_id' => $id, 'unique_id' => "t-$id", 'sample_code' => "T-$id", 'lab_id' => 7,
            'facility_id' => 10, 'data_sync' => 0, 'last_modified_datetime' => '2026-08-05 10:00:00',
            'vlsm_instance_id' => 'sts', 'result_status' => 6,
        ]);
        $db->insert('tb_tests', ['tb_id' => $id, 'test_result' => "result $id"]);
    }

    private static function sts(): RequestsService
    {
        return ContainerRegistry::get(RequestsService::class);
    }

    /**
     * What writeRequests() writes, decoded, with what it returns.
     *
     * @return array{0: mixed, 1: array<string, mixed>, 2: string}
     */
    private static function written(string $testType, int $chunkSize = RequestsService::WRITE_CHUNK): array
    {
        $out = fopen('php://memory', 'w+b');
        $meta = self::sts()->writeRequests($out, $testType, 7, null, null, self::SINCE, chunkSize: $chunkSize);
        rewind($out);
        $json = (string) stream_get_contents($out);
        return [json_decode($json, true, flags: JSON_THROW_ON_ERROR), $meta, $json];
    }

    /** @return list<string> */
    private static function uniqueIds(array $requests): array
    {
        $ids = array_column(array_values($requests), 'unique_id');
        sort($ids);
        return $ids;
    }

    // ---- The same answer as before ---------------------------------------------

    #[RunInSeparateProcess]
    public function testAListWindowIsWhatGetRequestsReturned(): void
    {
        $this->boot();
        foreach ([5, 1, 9, 3, 7] as $id) {
            self::vl($id);
        }
        // An in-flight row is sent as pending, never as 2.
        self::vl(4, extra: ['data_sync' => RequestsService::IN_FLIGHT]);
        self::vl(2, '2026-07-01 10:00:00');

        $before = self::sts()->getRequests('vl', 7, null, null, self::SINCE);
        [$requests, $meta, $json] = self::written('vl', chunkSize: 2);

        self::assertTrue(array_is_list($requests), 'VL requests stay a JSON array');
        $byPk = static function (array $rows): array {
            usort($rows, static fn(array $a, array $b): int => $a['vl_sample_id'] <=> $b['vl_sample_id']);
            return $rows;
        };
        self::assertSame($byPk(json_decode(json_encode($before['requests']), true)), $byPk($requests));
        self::assertSame(0, $requests[array_search('u-4', array_column($requests, 'unique_id'), true)]['data_sync']);
        self::assertStringContainsString('"patient_first_name":"Émilie 1"', $json, 'unicode is written as it was');
        self::assertEqualsCanonicalizing($before['sampleIds'], $meta['sampleIds']);
        self::assertEqualsCanonicalizing($before['facilityIds'], $meta['facilityIds']);
        self::assertSame(6, $meta['count']);
    }

    #[RunInSeparateProcess]
    public function testAKeyedWindowIsWhatGetRequestsReturned(): void
    {
        $this->boot();
        foreach ([3, 1, 2] as $id) {
            self::tb($id);
        }

        $before = self::sts()->getRequests('tb', 7, null, null, self::SINCE);
        [$requests, $meta, $json] = self::written('tb', chunkSize: 2);

        self::assertStringStartsWith('{"1":', $json, 'TB requests stay an object keyed by id');
        self::assertEquals(json_decode(json_encode($before['requests']), true), $requests);
        self::assertSame('result 2', $requests['2']['data_from_tests'][0]['test_result']);
        self::assertSame(3, $meta['count']);
    }

    #[RunInSeparateProcess]
    public function testAnEmptyWindowIsAnEmptyArrayWhateverTheModule(): void
    {
        $this->boot();
        self::vl(1, '2026-07-01 10:00:00');

        self::assertSame('[]', self::written('vl')[2]);
        self::assertSame('[]', self::written('tb')[2]);
        self::assertSame(0, self::written('vl')[1]['count']);
    }

    #[RunInSeparateProcess]
    public function testChunksMissNoRowAndRepeatNone(): void
    {
        $this->boot();
        for ($id = 1; $id <= 23; $id++) {
            self::vl($id);
        }

        foreach ([1, 5, 23, 24, 500] as $chunkSize) {
            [$requests] = self::written('vl', $chunkSize);
            $expected = array_map(static fn(int $id): string => "u-$id", range(1, 23));
            sort($expected);
            self::assertSame($expected, self::uniqueIds($requests), "chunks of $chunkSize");
        }
    }

    #[RunInSeparateProcess]
    public function testAWriteThatFailsStopsThePullBeforeAnythingIsMarkedSent(): void
    {
        $db = $this->boot();
        self::vl(1);
        // A stream that takes no bytes, as a full disk would.
        $out = fopen('php://memory', 'rb');

        try {
            self::sts()->writeRequests($out, 'vl', 7, null, null, self::SINCE);
            self::fail('a write that failed went unnoticed');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Could not write', $e->getMessage());
        }
        self::assertSame(0, (int) $db->rawQueryOne('SELECT data_sync FROM form_vl')['data_sync']);
    }

    // ---- Through requests.php -------------------------------------------------------

    /**
     * Posts to requests.php as a lab would, and reads the answer as the emitter
     * does: a piece at a time, each passed to $read.
     *
     * @param callable(string): void $read
     * @return string what requests.php printed, which should be nothing
     */
    private static function post(array $body, callable $read): string
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/remote/v2/requests.php')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer lab-seven-token')
            ->withBody((new StreamFactory())->createStream((string) json_encode($body)));
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        ob_start();
        try {
            $response = $handler->handle($request);
        } finally {
            $printed = (string) ob_get_clean();
        }
        $answer = $response->getBody();
        $answer->rewind();
        while (!$answer->eof()) {
            $read($answer->read(4096));
        }
        return $printed;
    }

    #[RunInSeparateProcess]
    public function testRequestsPhpAnswersAnOlderLabWithTheFieldsItAlwaysHad(): void
    {
        $db = $this->boot();
        for ($id = 1; $id <= 3; $id++) {
            self::vl($id);
        }

        $json = '';
        $printed = self::post(
            ['labId' => 7, 'testType' => 'vl', 'syncSinceDate' => self::SINCE],
            static function (string $piece) use (&$json): void {
                $json .= $piece;
            }
        );
        $answer = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        // In the response, not printed: the middleware adds its headers to it.
        self::assertSame('', $printed);

        self::assertSame(['status', 'requests', 'testType', 'labId', 'syncSinceDate'], array_keys($answer));
        self::assertSame(['success', 'vl', 7, self::SINCE], [
            $answer['status'], $answer['testType'], $answer['labId'], $answer['syncSinceDate'],
        ]);
        self::assertSame(['u-1', 'u-2', 'u-3'], self::uniqueIds($answer['requests']));
        self::assertSame(
            [1, 1, 1],
            array_map('intval', array_column($db->rawQuery('SELECT data_sync FROM form_vl'), 'data_sync')),
            'the rows sent are marked as sent, as before'
        );
    }

    /**
     * The defect itself. On the code before this change, the window below runs the
     * process out of memory: getRequests() held every row, and the answer was then
     * encoded for the API tracking body and again for the response.
     */
    #[RunInSeparateProcess]
    public function testABigWindowFitsInTheMemoryOfAFewRows(): void
    {
        $db = $this->boot();
        $address = str_repeat('Kissy Road, Freetown. ', 360);
        $values = [];
        for ($id = 1; $id <= 6000; $id++) {
            $values[] = "($id, 'u-$id', 'S-$id', 7, 10, 0, '2026-08-05 10:00:00', 'sts', 6, '$address')";
        }
        foreach (array_chunk($values, 500) as $chunk) {
            $db->rawQuery(
                'INSERT INTO form_vl (vl_sample_id, unique_id, sample_code, lab_id, facility_id, data_sync,'
                . ' last_modified_datetime, vlsm_instance_id, result_status, patient_address) VALUES '
                . implode(',', $chunk)
            );
        }
        // About 47 MB of addresses alone. The limit leaves room for a few chunks of
        // rows, not for the window.
        ini_set('memory_limit', (string) (memory_get_usage() + 40 * 1024 * 1024));

        $bytes = 0;
        $tail = '';
        self::post(
            ['labId' => 7, 'testType' => 'vl', 'syncSinceDate' => self::SINCE],
            static function (string $piece) use (&$bytes, &$tail): void {
                $bytes += strlen($piece);
                $tail = substr($tail . $piece, -200);
            }
        );

        self::assertGreaterThan(47_000_000, $bytes, 'the whole window was sent');
        self::assertStringEndsWith('"testType":"vl","labId":7,"syncSinceDate":"' . self::SINCE . '"}', $tail);
        self::assertSame(
            6000,
            (int) $db->rawQueryOne('SELECT COUNT(*) AS n FROM form_vl WHERE data_sync = 1')['n']
        );
    }
}

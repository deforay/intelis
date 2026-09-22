<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\SystemException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;

/**
 * The STS endpoint a lab pushes results to (remote/v2/results.php), driven the way
 * the app runs it. A request it refuses must store nothing and answer with a status
 * the lab's sender reads as a failure (ResultSyncAcknowledgement::fromResponse).
 *
 * One drive per test: the handler uses require_once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class StsResultsEndpointTest extends TestCase
{
    private const LAB = 7;

    private const OTHER_LAB = 8;

    private const TOKEN = 'lab-7-token';

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
        $database = 'intelis_sts_results_endpoint_test_' . getmypid();
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['vl' => true]]);
        }
        $db = LegacyAppHarness::boot($database, [
            'system_config', 'global_config', 's_vlsm_instance', 'r_sample_status', 'form_vl', 'roles',
            'user_details', 'r_vl_sample_rejection_reasons', 'facility_details', 'track_api_requests',
            'specimen_manifests',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Registered'), (7, 'Accepted')");
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'sts-instance']);
        $db->insert('system_config', [
            'display_name' => 'Instance type', 'name' => 'sc_user_type', 'value' => 'remoteuser',
        ]);
        foreach ([self::LAB => self::TOKEN, self::OTHER_LAB => 'lab-8-token'] as $labId => $token) {
            $db->insert('facility_details', [
                'facility_id' => $labId, 'facility_name' => "Lab $labId", 'facility_type' => 2, 'status' => 'active',
                'sts_token' => $token, 'sts_token_expiry' => '2099-01-01 00:00:00',
            ]);
        }
        $db->insert('form_vl', [
            'unique_id' => 'u-1', 'remote_sample_code' => 'R-u-1', 'lab_id' => self::LAB, 'facility_id' => 1,
            'vlsm_instance_id' => 'sts-instance', 'result_status' => 6, 'data_sync' => 1,
            'last_modified_datetime' => '2026-09-01 10:00:00',
        ]);
        return $db;
    }

    private static function vlResult(): array
    {
        return [
            'unique_id' => 'u-1', 'remote_sample_code' => 'R-u-1', 'sample_code' => 'L-u-1', 'lab_id' => self::LAB,
            'facility_id' => 1, 'vlsm_instance_id' => 'lab-instance', 'result' => '40', 'result_status' => 7,
        ];
    }

    private static function body(array $overrides = []): string
    {
        return json_encode(
            $overrides + ['labId' => self::LAB, 'testType' => 'vl', 'results' => [self::vlResult()]],
            JSON_THROW_ON_ERROR
        );
    }

    /** @return array{0: int, 1: mixed} status and decoded body */
    private static function drive(string $body, ?string $token = self::TOKEN): array
    {
        $_SERVER['HTTP_HOST'] = 'tests.local';
        $_SERVER['REQUEST_URI'] = '/remote/v2/results.php';
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/remote/v2/results.php')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream($body));
        if ($token !== null) {
            $request = $request->withHeader('Authorization', "Bearer $token");
        }
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        try {
            $response = $handler->handle($request);
        } catch (SystemException $e) {
            // The error middleware turns this into the response status.
            return [(int) $e->getCode(), null];
        }
        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private static function assertNothingStored(mixed $db): void
    {
        $row = $db->rawQueryOne("SELECT result, result_status FROM form_vl WHERE unique_id = 'u-1'");
        self::assertNull($row['result']);
        self::assertSame(6, (int) $row['result_status']);
    }

    #[RunInSeparateProcess]
    public function testTheRightTokenStoresTheResultAndAcknowledgesIt(): void
    {
        $db = $this->boot();

        [$status, $ack] = self::drive(self::body());

        self::assertSame(200, $status);
        self::assertSame(['L-u-1'], $ack);
        self::assertSame('40', $db->rawQueryOne("SELECT result FROM form_vl WHERE unique_id = 'u-1'")['result']);
    }

    #[RunInSeparateProcess]
    public function testNoTokenIsRefused(): void
    {
        $db = $this->boot();
        self::assertSame(401, self::drive(self::body(), null)[0]);
        self::assertNothingStored($db);
    }

    #[RunInSeparateProcess]
    public function testAWrongTokenIsRefused(): void
    {
        $db = $this->boot();
        self::assertSame(401, self::drive(self::body(), 'not-the-token')[0]);
        self::assertNothingStored($db);
    }

    #[RunInSeparateProcess]
    public function testAnotherLabsTokenIsRefused(): void
    {
        $db = $this->boot();
        self::assertSame(401, self::drive(self::body(), 'lab-8-token')[0]);
        self::assertNothingStored($db);
    }

    #[RunInSeparateProcess]
    public function testAnExpiredTokenIsRefused(): void
    {
        $db = $this->boot();
        $db->rawQuery("UPDATE facility_details SET sts_token_expiry = '2020-01-01 00:00:00' WHERE facility_id = 7");
        self::assertSame(401, self::drive(self::body())[0]);
        self::assertNothingStored($db);
    }

    #[RunInSeparateProcess]
    public function testNoLabIdIsAMalformedRequest(): void
    {
        $db = $this->boot();
        self::assertSame(400, self::drive(self::body(['labId' => null]))[0]);
        self::assertNothingStored($db);
    }

    #[RunInSeparateProcess]
    public function testALabIdThatIsNotANumberIsAMalformedRequest(): void
    {
        $db = $this->boot();
        self::assertSame(400, self::drive(self::body(['labId' => 'lab seven']))[0]);
        self::assertNothingStored($db);
    }

    #[RunInSeparateProcess]
    public function testNoTestTypeIsAMalformedRequest(): void
    {
        $db = $this->boot();
        self::assertSame(400, self::drive(self::body(['testType' => null]))[0]);
        self::assertNothingStored($db);
    }

    #[RunInSeparateProcess]
    public function testABodyThatIsNotJsonIsAMalformedRequest(): void
    {
        $db = $this->boot();
        self::assertSame(400, self::drive('{"labId": 7, "testType": "vl", "results": [')[0]);
        self::assertNothingStored($db);
    }
}

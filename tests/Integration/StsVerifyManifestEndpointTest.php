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
use Tests\Support\TestRun;

/**
 * The STS endpoint a lab asks whether a manifest code is one it may activate
 * (remote/v2/verify-manifest.php).
 *
 * Labs pasted a TB manifest code on the CD4 Add Samples page and were told only
 * "No manifest found". The answer for a code of another test type stays
 * 'not-found', so older labs behave as before, and adds
 * `manifestTestType` so newer labs can name the right module.
 *
 * One drive per test: the handler uses require_once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class StsVerifyManifestEndpointTest extends TestCase
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
        $database = TestRun::database('intelis_sts_verify_manifest_test');
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'database' => ['db' => $database],
                'modules' => ['cd4' => true, 'tb' => true],
            ]);
        }
        $db = LegacyAppHarness::boot($database, [
            'system_config', 'global_config', 's_vlsm_instance', 'roles', 'user_details',
            'facility_details', 'track_api_requests', 'specimen_manifests',
        ]);
        $this->booted = true;
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
        $db->insert('specimen_manifests', [
            'manifest_code' => 'TB26112315P0KH', 'module' => 'tb', 'lab_id' => self::LAB, 'added_by' => 'u1',
        ]);
        $db->insert('specimen_manifests', [
            'manifest_code' => 'CD426112301ABCD', 'module' => 'cd4', 'lab_id' => self::OTHER_LAB, 'added_by' => 'u1',
        ]);
        return $db;
    }

    /**
     * The handler's PSR response does not carry http_response_code(), so tests
     * read the body only.
     *
     * @return array{0: int, 1: mixed} status and decoded body
     */
    private static function drive(string $manifestCode, string $testType): array
    {
        $_SERVER['HTTP_HOST'] = 'tests.local';
        $_SERVER['REQUEST_URI'] = '/remote/v2/verify-manifest.php';
        $body = json_encode([
            'labId' => self::LAB, 'testType' => $testType,
            'manifestCode' => $manifestCode, 'manifestHash' => 'none',
        ], JSON_THROW_ON_ERROR);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/remote/v2/verify-manifest.php')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->withBody((new StreamFactory())->createStream($body));
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        try {
            $response = $handler->handle($request);
        } catch (SystemException $e) {
            return [(int) $e->getCode(), null];
        }
        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    #[RunInSeparateProcess]
    public function testATbCodeOnTheCd4PageNamesTb(): void
    {
        $this->boot();

        [, $body] = self::drive('TB26112315P0KH', 'cd4');

        self::assertSame('not-found', $body['status'], 'Older labs read the status, so it stays not-found.');
        self::assertSame('tb', $body['manifestTestType']);
    }

    #[RunInSeparateProcess]
    public function testAnUnknownCodeNamesNoTestType(): void
    {
        $this->boot();

        [, $body] = self::drive('NOSUCHCODE', 'cd4');

        self::assertSame('not-found', $body['status']);
        self::assertArrayNotHasKey('manifestTestType', $body);
    }

    #[RunInSeparateProcess]
    public function testAnotherLabsManifestOfThisTestTypeIsStillWrongLab(): void
    {
        $this->boot();

        [, $body] = self::drive('CD426112301ABCD', 'cd4');

        self::assertSame('wrong-lab', $body['status']);
        self::assertSame('Lab 8', $body['labName']);
        self::assertArrayNotHasKey('manifestTestType', $body);
    }
}

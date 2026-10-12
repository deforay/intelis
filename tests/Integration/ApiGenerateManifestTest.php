<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;

/**
 * The InteLIS Mobile manifest endpoint puts only the samples it was asked for
 * into the new manifest.
 *
 * It used to filter by sampleCode alone: a request that named its samples by
 * uniqueId selected every sample the user could see, wrote all of them into the
 * manifest (with the requested lab), and only then failed.
 *
 * One drive per test: the handler uses require_once. Set INTELIS_TEST_DB_HOST/
 * _PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiGenerateManifestTest extends TestCase
{
    private const DATABASE = 'intelis_api_manifest_test';

    private const TOKEN = 'test-api-token';

    private const USER = 'user-api-1';

    private const TABLES = [
        's_vlsm_instance', 'system_config', 'global_config',
        'roles', 'user_details', 'user_facility_map', 'facility_details',
        'geographical_divisions', 'batch_details', 'r_sample_status',
        'r_funding_sources', 'r_implementation_partners', 'track_api_requests',
        'form_vl', 'r_vl_sample_rejection_reasons', 'r_vl_sample_type', 'r_vl_test_reasons',
        'specimen_manifests',
    ];

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

        // ApiService reads VERSION at construction; the app defines it in bootstrap.
        require_once ROOT_PATH . '/app/system/version.php';

        // Config lookups are memoised on disk; a stale entry from an earlier run
        // would answer for this database.
        self::clearFileCache();

        // Named per process: two runs against one MySQL would drop each other's fixtures.
        LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), self::TABLES);
        $this->seedInstanceAndUser();
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    private static function clearFileCache(): void
    {
        $dir = CACHE_PATH . DIRECTORY_SEPARATOR . 'file_cache';
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }

    private function seedInstanceAndUser(): void
    {
        $db = LegacyAppHarness::db();
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'test-instance']);
        // form_vl.result_status is a foreign key; the other form tables carry none.
        $db->insert('r_sample_status', ['status_id' => 7, 'status_name' => 'Accepted', 'status' => 'active']);
        $db->insert('system_config', ['display_name' => 'Instance type', 'name' => 'sc_user_type', 'value' => 'vlsm']);
        $db->insert('roles', [
            'role_id' => 1,
            'role_name' => 'Lab',
            'role_code' => 'lab',
            'access_type' => 'testing-lab',
        ]);
        $db->insert('user_details', [
            'user_id' => self::USER,
            'user_name' => 'API User',
            'login_id' => 'api',
            'api_token' => self::TOKEN,
            'status' => 'active',
            'role_id' => 1,
            'app_access' => 'yes',
        ]);
        // The user sees facility 1 only; a row at facility 2 must never come back.
        $db->insert('user_facility_map', ['user_id' => self::USER, 'facility_id' => 1]);
        foreach ([1, 2] as $facilityId) {
            $db->insert('facility_details', [
                'facility_id' => $facilityId,
                'facility_name' => "Facility $facilityId",
                'status' => 'active',
            ]);
        }
    }

    /** @param array<string, mixed> $overrides */
    private function seedVl(string $artNo, int $facilityId, array $overrides = []): int
    {
        $db = LegacyAppHarness::db();
        $db->insert('form_vl', $overrides + [
            'vlsm_instance_id' => 'test-instance',
            'unique_id' => bin2hex(random_bytes(8)),
            'sample_code' => 'VL-' . $artNo,
            'app_sample_code' => 'APP-' . $artNo,
            'facility_id' => $facilityId,
            'lab_id' => 1,
            'patient_art_no' => $artNo,
            'result_status' => 7,
            'sample_collection_date' => '2026-08-01 09:00:00',
            'last_modified_datetime' => '2026-08-02 09:00:00',
            'request_created_by' => self::USER,
        ]);

        return (int) $db->getInsertId();
    }

    private function drive(string $path, array $body): array
    {
        $_SERVER['HTTP_HOST'] = 'tests.local';
        $_SERVER['REQUEST_URI'] = $path;

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $path)
            ->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode($body, JSON_THROW_ON_ERROR)));

        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $response = $handler->handle($request);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private static function row(string $table, string $key, int $id): array
    {
        return LegacyAppHarness::db()->rawQueryOne("SELECT * FROM $table WHERE $key = ?", [$id]) ?? [];
    }

    /** @return array<string, ?string> sample code => manifest code */
    private static function manifests(): array
    {
        $rows = LegacyAppHarness::db()->rawQuery(
            'SELECT sample_code, sample_package_code FROM form_vl ORDER BY sample_code'
        );
        return array_column($rows, 'sample_package_code', 'sample_code');
    }

    #[RunInSeparateProcess]
    public function testSampleCodesPutOnlyThoseSamplesInTheManifest(): void
    {
        $this->seedVl('ART-1', 1);
        $this->seedVl('ART-2', 1);

        $payload = $this->drive('/api/v1.1/generate-manifest.php', [
            'testType' => 'vl',
            'labId' => 1,
            'sampleCode' => ['VL-ART-1'],
        ]);

        self::assertSame('success', $payload['status']);
        self::assertNotSame('', $payload['manifestHash']);
        $manifests = self::manifests();
        self::assertSame($payload['manifestCode'], $manifests['VL-ART-1']);
        self::assertNull($manifests['VL-ART-2']);
    }

    #[RunInSeparateProcess]
    public function testUniqueIdsPutOnlyThoseSamplesInTheManifest(): void
    {
        $this->seedVl('ART-1', 1, ['unique_id' => 'uid-1']);
        $this->seedVl('ART-2', 1, ['unique_id' => 'uid-2']);

        $payload = $this->drive('/api/v1.1/generate-manifest.php', [
            'testType' => 'vl',
            'labId' => 1,
            'uniqueId' => ['uid-2'],
        ]);

        self::assertSame('success', $payload['status']);
        $manifests = self::manifests();
        self::assertNull($manifests['VL-ART-1']);
        self::assertSame($payload['manifestCode'], $manifests['VL-ART-2']);
    }

    #[RunInSeparateProcess]
    public function testAnUnknownUniqueIdTouchesNoSample(): void
    {
        $this->seedVl('ART-1', 1);
        $this->seedVl('ART-2', 1);

        $payload = $this->drive('/api/v1.1/generate-manifest.php', [
            'testType' => 'vl',
            'labId' => 1,
            'uniqueId' => ['no-such-sample'],
        ]);

        self::assertSame('Failed', $payload['status']);
        self::assertSame(['VL-ART-1' => null, 'VL-ART-2' => null], self::manifests());
    }

    #[RunInSeparateProcess]
    public function testIdentifierListsWithNothingUsableTouchNoSample(): void
    {
        $this->seedVl('ART-1', 1);
        $this->seedVl('ART-2', 1);

        $this->drive('/api/v1.1/generate-manifest.php', [
            'testType' => 'vl',
            'labId' => 1,
            'uniqueId' => [null],
            'sampleCode' => [[]],
        ]);

        self::assertSame(['VL-ART-1' => null, 'VL-ART-2' => null], self::manifests());
        $manifestCount = LegacyAppHarness::db()->rawQueryOne('SELECT COUNT(*) n FROM specimen_manifests')['n'];
        self::assertSame(0, (int) $manifestCount);
    }

    #[RunInSeparateProcess]
    public function testAQuoteInASampleCodeTouchesNoSample(): void
    {
        $this->seedVl('ART-1', 1);

        $this->drive('/api/v1.1/generate-manifest.php', [
            'testType' => 'vl',
            'labId' => 1,
            'sampleCode' => ["x') OR ('1'='1"],
        ]);

        self::assertSame(['VL-ART-1' => null], self::manifests());
    }

    #[RunInSeparateProcess]
    public function testARequestOverlappingAnotherLeavesItsSamplesAlone(): void
    {
        $db = LegacyAppHarness::db();
        $db->insert('specimen_manifests', [
            'manifest_code' => 'VLFIRST',
            'module' => 'vl',
            'lab_id' => 1,
            'number_of_samples' => 2,
            'manifest_status' => 'pending',
        ]);
        $firstId = (int) $db->getInsertId();
        $this->seedVl('ART-1', 1);
        $this->seedVl('ART-2', 1);

        // Another request is mid-way through taking both samples: linked, not yet
        // committed. This one starts now, so its first read finds them free.
        $other = self::holdSamplesOnManifest($firstId, 'VLFIRST', ['VL-ART-1', 'VL-ART-2']);

        $payload = $this->drive('/api/v1.1/generate-manifest.php', [
            'testType' => 'vl',
            'labId' => 1,
            'sampleCode' => ['VL-ART-1', 'VL-ART-2'],
        ]);
        self::assertSame(0, self::finish($other), 'The request never waited on the held samples.');

        // It waits for the other to commit and leaves both samples where they went,
        // rather than taking them onto a second manifest.
        self::assertSame('success', $payload['status']);
        self::assertArrayNotHasKey('manifestCode', $payload);
        self::assertSame('VLFIRST', $payload['data']['existingManifests'][0]['manifestCode']);
        self::assertSame(
            ['VL-ART-1', 'VL-ART-2'],
            array_column($payload['data']['existingManifests'][0]['samples'], 'sampleCode')
        );
        self::assertSame(['VL-ART-1' => 'VLFIRST', 'VL-ART-2' => 'VLFIRST'], self::manifests());
        $manifestCount = $db->rawQueryOne('SELECT COUNT(*) n FROM specimen_manifests')['n'];
        self::assertSame(1, (int) $manifestCount);
    }

    /**
     * Link the samples to a manifest in a transaction of another connection, and
     * hold it open until the request under test waits on them.
     *
     * @param list<string> $sampleCodes
     * @return array{0: resource, 1: array<int, resource>}
     */
    private static function holdSamplesOnManifest(int $manifestId, string $manifestCode, array $sampleCodes): array
    {
        $script = <<<'PHP'
            $db = new mysqli(getenv('H'), getenv('U'), getenv('P'), getenv('D'), (int) getenv('O'));
            $db->begin_transaction();
            $codes = json_decode(getenv('S'), true);
            $in = implode(',', array_fill(0, count($codes), '?'));
            $stmt = $db->prepare(
                "UPDATE form_vl SET sample_package_id = ?, sample_package_code = ? WHERE sample_code IN ($in)"
            );
            $stmt->execute([(int) getenv('M'), getenv('C'), ...$codes]);
            fwrite(STDOUT, "held\n");
            // Commit once a request is waiting on this connection's locks, so the
            // request under test is certain to have started while they were held.
            // Exits 3 when no such wait appears: then the overlap was never tested.
            $waiting = 'SELECT COUNT(*) FROM performance_schema.data_lock_waits w
                JOIN performance_schema.threads t ON t.THREAD_ID = w.BLOCKING_THREAD_ID
                WHERE t.PROCESSLIST_ID = CONNECTION_ID()';
            $waited = false;
            for ($i = 0; $i < 1000 && !$waited; $i++) {
                $waited = (int) $db->query($waiting)->fetch_row()[0] > 0;
                $waited || usleep(20000);
            }
            $db->commit();
            exit($waited ? 0 : 3);
            PHP;
        $env = [
            'H' => (string) getenv('INTELIS_TEST_DB_HOST'),
            'U' => (string) getenv('INTELIS_TEST_DB_USER'),
            'P' => (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
            'O' => (string) (getenv('INTELIS_TEST_DB_PORT') ?: 3306),
            'D' => self::DATABASE . '_' . getmypid(),
            'M' => (string) $manifestId,
            'C' => $manifestCode,
            'S' => json_encode($sampleCodes, JSON_THROW_ON_ERROR),
        ];
        $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        self::assertIsResource($process);
        $held = fgets($pipes[1]);
        if ($held !== "held\n") {
            // Read only on failure: reading it waits for the process to exit.
            self::fail('Could not hold the samples: ' . stream_get_contents($pipes[2]));
        }

        return [$process, $pipes];
    }

    /** @param array{0: resource, 1: array<int, resource>} $held */
    private static function finish(array $held): int
    {
        [$process, $pipes] = $held;
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);

        return proc_close($process);
    }
}

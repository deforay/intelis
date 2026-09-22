<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\ApiService;
use App\Services\CommonService;
use App\Services\SmartConnectService;
use App\Utilities\LoggerUtility;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * A lab whose Smart Connect enrollment is refused.
 *
 * Every sync module and the reference-data upload asked for a token, so one refused
 * enrollment was tried again for each, and each try logged the same failure three
 * times: the HTTP client, enroll(), then the upload. One error per run says it.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class SmartConnectEnrollmentLogTest extends TestCase
{
    private bool $booted = false;
    private string $file = '';

    /** @var list<array<string, mixed>> */
    private array $history = [];

    protected function tearDown(): void
    {
        if ($this->file !== '' && is_file($this->file)) {
            unlink($this->file);
        }
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param list<Response> $answers */
    private function smartConnect(array $answers, ?string $storedToken = null): SmartConnectService
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $database = 'intelis_smart_connect_log_test_' . getmypid();
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database]]);
        }
        $db = LegacyAppHarness::boot($database, ['system_config', 'global_config', 's_vlsm_instance']);
        $this->booted = true;
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'lab-instance', 'sc_api_token' => $storedToken]);
        $db->insert('global_config', ['name' => 'vldashboard_url', 'value' => 'https://sc.example']);
        $db->insert('global_config', ['name' => 'smart_connect_enrollment_key', 'value' => 'wrong-key']);

        $stack = HandlerStack::create(new MockHandler($answers));
        $stack->push(Middleware::history($this->history));
        $general = ContainerRegistry::get(CommonService::class);
        $api = new ApiService($general, new Client(['handler' => $stack]), maxRetries: 0);

        $this->file = (string) tempnam(sys_get_temp_dir(), 'sc-upload-');
        file_put_contents($this->file, '{}');

        return new SmartConnectService($db, $general, $api);
    }

    private static function logsOf(callable $run): TestHandler
    {
        $handler = new TestHandler();
        $logger = LoggerUtility::getLogger();
        $logger->pushHandler($handler);
        try {
            $run();
        } finally {
            $logger->popHandler();
        }
        return $handler;
    }

    /** @return list<string> */
    private function paths(): array
    {
        return array_map(static fn(array $t): string => $t['request']->getUri()->getPath(), $this->history);
    }

    private static function errors(TestHandler $logs): array
    {
        return array_values(array_filter(
            $logs->getRecords(),
            static fn($record): bool => $record->level->value >= \Monolog\Level::Error->value
        ));
    }

    #[RunInSeparateProcess]
    public function testARefusedEnrollmentIsOneErrorForTheWholeRun(): void
    {
        $sc = $this->smartConnect([new Response(401, [], '{"status":"error","message":"Invalid enrollment key"}')]);

        $logs = self::logsOf(function () use ($sc): void {
            foreach (['vl', 'eid', 'covid19'] as $module) {
                $result = $sc->uploadFile("https://sc.example/api/v2/$module", 'file', $this->file, [], true);
                self::assertNull($result['httpStatusCode'], 'nothing is uploaded without a token');
            }
        });

        $this->assertSame(['/api/v2/enroll'], $this->paths(), 'enrollment is tried once per run, not per module');
        $errors = self::errors($logs);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('401', $errors[0]->message);
        $this->assertStringContainsString('Invalid enrollment key', $errors[0]->message);
    }

    #[RunInSeparateProcess]
    public function testARevokedTokenThatCannotBeRenewedIsOneError(): void
    {
        $sc = $this->smartConnect([
            new Response(401, [], '{"status":"error","message":"Token revoked"}'),
            new Response(401, [], '{"status":"error","message":"Invalid enrollment key"}'),
        ], storedToken: 'old-token');

        $logs = self::logsOf(function () use ($sc): void {
            $sc->uploadFile('https://sc.example/api/v2/vl', 'file', $this->file, [], true);
            $sc->uploadFile('https://sc.example/api/v2/eid', 'file', $this->file, [], true);
        });

        $this->assertSame(['/api/v2/vl', '/api/v2/enroll'], $this->paths());
        $this->assertCount(1, self::errors($logs));
    }

    #[RunInSeparateProcess]
    public function testAnEnrollmentAnswerWithoutATokenIsAnError(): void
    {
        $sc = $this->smartConnect([new Response(201, [], '{"status":"success","data":{}}')]);

        $logs = self::logsOf(fn() => $sc->uploadFile('https://sc.example/api/v2/vl', 'file', $this->file, [], true));

        $this->assertCount(1, self::errors($logs), 'a silent null token left nothing to go on');
    }

    #[RunInSeparateProcess]
    public function testASuccessfulEnrollmentUploadsAndLogsNoError(): void
    {
        $sc = $this->smartConnect([
            new Response(201, [], '{"status":"success","data":{"token":"new-token"}}'),
            new Response(200, [], '{"status":"success"}'),
        ]);

        $logs = self::logsOf(function () use ($sc): void {
            $result = $sc->uploadFile('https://sc.example/api/v2/vl', 'file', $this->file, [], true);
            self::assertSame(200, $result['httpStatusCode']);
        });

        $this->assertSame(['/api/v2/enroll', '/api/v2/vl'], $this->paths());
        $this->assertSame('Bearer new-token', $this->history[1]['request']->getHeaderLine('Authorization'));
        $this->assertSame([], self::errors($logs));
    }
}

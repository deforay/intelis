<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ApiService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\NetworkException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;
use App\Utilities\LoggerUtility;
use Monolog\Handler\TestHandler;

/** @phpstan-type HttpTransaction array{request: RequestInterface, response: ?ResponseInterface, error: mixed, options: array<mixed>} */
final class ApiServiceHttpTest extends TestCase
{
    /** @var \ArrayObject<int, HttpTransaction> */
    private \ArrayObject $history;

    /** @param list<Response|Throwable> $results */
    private function service(array $results, int $maxRetries = 0): ApiService
    {
        // Bypass only the database-backed identity lookup. Use the production
        // client and middleware, replacing the transport to avoid external calls.
        $service = (new ReflectionClass(ApiService::class))->newInstanceWithoutConstructor();
        $retryOptions = [
            'maxRetries' => $maxRetries,
            'delayMultiplier' => 0,
            'jitterFactor' => 0.0,
            'maxRetryDelay' => 0,
        ];
        foreach ($retryOptions as $name => $value) {
            (new ReflectionProperty(ApiService::class, $name))->setValue($service, $value);
        }
        $client = (new ReflectionMethod(ApiService::class, 'createApiClient'))->invoke($service);
        self::assertInstanceOf(Client::class, $client);
        $stack = $client->getConfig('handler');
        self::assertInstanceOf(HandlerStack::class, $stack);
        $stack->setHandler(new MockHandler($results));
        $this->history = new \ArrayObject();
        $history = $this->history;
        $stack->push(Middleware::history($history));
        (new ReflectionProperty(ApiService::class, 'client'))->setValue($service, $client);
        $service->setHeaders(['X-Instance-ID' => '', 'X-Requestor-Version' => 'test']);
        return $service;
    }

    private function requestAt(int $index): RequestInterface
    {
        $transaction = $this->history[$index];
        self::assertIsArray($transaction);
        return $transaction['request'];
    }

    public function testCompressedPostPreservesAuthenticationAndResponseHeaders(): void
    {
        $service = $this->service([new Response(200, ['X-Sync-Hint' => 'continue'], '{"ok":true}')]);
        $service->setBearerToken('test-token');
        $payload = ['data' => str_repeat('x', 1200)];
        $result = $service->post('https://sts.example/sync', $payload, gzip: true, returnWithStatusCode: true);
        self::assertIsArray($result);
        self::assertSame(200, $result['httpStatusCode']);
        self::assertSame('continue', $result['headers']['x-sync-hint']);
        self::assertSame('{"ok":true}', $result['body']);
        $request = $this->requestAt(0);
        self::assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
        self::assertSame('gzip', $request->getHeaderLine('Content-Encoding'));
        $decoded = gzdecode((string) $request->getBody());
        self::assertIsString($decoded);
        self::assertSame($payload, json_decode($decoded, true));
    }

    #[DataProvider('retryableStatuses')]
    public function testTransientResponsesAreRetried(int $status): void
    {
        $service = $this->service([new Response($status), new Response(200, [], 'ok')], 1);
        self::assertSame('ok', $service->post('https://sts.example/sync', ['id' => 1]));
        self::assertCount(2, $this->history);
        self::assertSame(
            $this->requestAt(0)->getHeaderLine('X-Request-ID'),
            $this->requestAt(1)->getHeaderLine('X-Request-ID')
        );
    }

    /** @return list<array{int}> */
    public static function retryableStatuses(): array
    {
        return [[429], [500], [503]];
    }

    public function testRetriesStopAtConfiguredLimitAndPreserveFinalResponse(): void
    {
        $service = $this->service([new Response(503), new Response(503, ['Retry-After' => '60'], 'busy')], 1);
        $result = $service->post('https://sts.example/sync', [], returnWithStatusCode: true);
        self::assertCount(2, $this->history);
        self::assertSame(['httpStatusCode' => 503, 'body' => 'busy', 'headers' => ['retry-after' => '60']], $result);
    }

    public function testAuthenticationFailureIsNotRetried(): void
    {
        $service = $this->service([new Response(401, [], 'unauthorized')], 3);
        self::assertSame(
            ['httpStatusCode' => 401, 'body' => 'unauthorized'],
            $service->getHealth('https://sts.example/health', true)
        );
        self::assertCount(1, $this->history);
    }

    public function testConnectionFailureCanRecoverWithinRetryLimit(): void
    {
        $failure = new ConnectException('Connection refused', new Request('POST', 'https://sts.example/sync'));
        $service = $this->service([$failure, new Response(200, [], 'ok')], 1);
        self::assertSame('ok', $service->post('https://sts.example/sync', []));
        self::assertCount(2, $this->history);
    }

    public function testAmbiguousPostNetworkFailureIsNotReplayed(): void
    {
        $failure = new NetworkException('Response lost after sending', new Request('POST', 'https://sts.example/sync'));
        $service = $this->service([$failure], 3);
        self::assertNull($service->post('https://sts.example/sync', ['id' => 1]));
        self::assertCount(1, $this->history);
    }

    public function testReadOnlyNetworkFailureCanBeRetried(): void
    {
        $failure = new NetworkException('Response lost', new Request('GET', 'https://sts.example/health'));
        $service = $this->service([$failure, new Response(200, [], 'ok')], 1);
        self::assertSame('ok', $service->getHealth('https://sts.example/health'));
        self::assertCount(2, $this->history);
    }

    public function testLocalRequestFailureDoesNotCallRemovedResponseMethodsOrRetry(): void
    {
        $failure = new RequestException('Cannot read request body', new Request('POST', 'https://sts.example/sync'));
        $service = $this->service([$failure], 3);
        self::assertSame(
            ['httpStatusCode' => 500, 'body' => null, 'headers' => []],
            $service->post('https://sts.example/sync', [], returnWithStatusCode: true)
        );
        self::assertCount(1, $this->history);
    }

    public function testMultipartUploadPreservesCompressedFileAndErrorStatus(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'intelis-upload-');
        self::assertNotFalse($file);
        $payload = '{"sample":"test"}';
        file_put_contents($file, $payload);
        try {
            $service = $this->service([new Response(413, [], 'too large')]);
            $result = $service->postFile('https://sts.example/upload', 'file', $file, ['labId' => '42'], true, true);
            self::assertSame(['httpStatusCode' => 413, 'body' => 'too large'], $result);
            $request = $this->requestAt(0);
            self::assertStringStartsWith('multipart/form-data;', $request->getHeaderLine('Content-Type'));
            $body = (string) $request->getBody();
            self::assertStringContainsString('name="labId"', $body);
            self::assertStringContainsString("\r\n\r\n42\r\n", $body);
            $compressed = gzencode($payload);
            self::assertIsString($compressed);
            self::assertStringContainsString($compressed, $body);
        } finally {
            unlink($file);
        }
    }

    public function testUploadTransportFailureHasNoHttpStatus(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'intelis-upload-');
        self::assertNotFalse($file);
        file_put_contents($file, '{}');
        try {
            $failure = new RequestException('Cannot read body', new Request('POST', 'https://sts.example/upload'));
            $service = $this->service([$failure]);
            self::assertSame(
                ['httpStatusCode' => null, 'body' => null],
                $service->postFile('https://sts.example/upload', 'file', $file, [], true, true)
            );
        } finally {
            unlink($file);
        }
    }

    /** Records what the application logger is given while $run runs. */
    private function logsOf(callable $run): TestHandler
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

    public function testAnErrorAnswerTheCallerReadsIsOneWarningWithoutATrace(): void
    {
        $service = $this->service([new Response(401, [], '{"message":"Invalid enrollment key"}')]);

        $logs = $this->logsOf(function () use ($service): void {
            $result = $service->post('https://sc.example/api/v2/enroll', [], returnWithStatusCode: true);
            self::assertSame(401, $result['httpStatusCode']);
        });

        // The caller has the status and decides what the failure means; this layer
        // repeating it as an error, with a trace through Guzzle, only doubled the log.
        self::assertFalse($logs->hasErrorRecords() || $logs->hasCriticalRecords());
        self::assertCount(1, $logs->getRecords());
        self::assertTrue($logs->hasWarningThatContains('HTTP 401'));
        self::assertTrue($logs->hasWarningThatContains('Invalid enrollment key'));
        self::assertArrayNotHasKey('stacktrace', $logs->getRecords()[0]->context);
    }

    public function testAnErrorAnswerForACallerThatOnlyGetsTheBodyIsStillAnError(): void
    {
        $service = $this->service([new Response(500, [], 'boom')]);

        $logs = $this->logsOf(fn() => $service->post('https://sts.example/sync', []));

        self::assertTrue($logs->hasErrorThatContains('Unable to post to https://sts.example/sync'));
    }

    public function testNoAnswerAtAllIsStillAnError(): void
    {
        $failure = new ConnectException('Connection refused', new Request('POST', 'https://sts.example/sync'));
        $service = $this->service([$failure]);

        $logs = $this->logsOf(fn() => $service->post('https://sts.example/sync', [], returnWithStatusCode: true));

        self::assertTrue($logs->hasErrorThatContains('Connection refused'));
    }

    public function testAnUploadErrorAnswerTheCallerReadsIsOneWarning(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'intelis-upload-');
        self::assertNotFalse($file);
        file_put_contents($file, '{}');
        try {
            $service = $this->service([new Response(401, [], 'unauthorized')]);
            $logs = $this->logsOf(
                fn() => $service->postFile('https://sc.example/api/v2/vl', 'file', $file, [], true, true)
            );
            self::assertFalse($logs->hasErrorRecords() || $logs->hasCriticalRecords());
            self::assertTrue($logs->hasWarningThatContains('HTTP 401'));
        } finally {
            unlink($file);
        }
    }

    public function testLegacyScalarHeadersAreConvertedToStrings(): void
    {
        $service = $this->service([new Response(200, [], 'ok')]);
        $service->setHeaders(['X-Lab-ID' => 42, 'X-Optional' => null, 'X-Flags' => [true, 2]]);
        self::assertSame('ok', $service->post('https://sts.example/sync', []));
        $request = $this->requestAt(0);
        self::assertSame('42', $request->getHeaderLine('X-Lab-ID'));
        self::assertSame('', $request->getHeaderLine('X-Optional'));
        self::assertSame(['1', '2'], $request->getHeader('X-Flags'));
    }

    public function testAsyncSetupFailureStillReturnsARejectedPromise(): void
    {
        $service = $this->service([]);
        $service->setHeaders(['X-Invalid' => "value\r\nInjected: true"]);
        $promise = $service->post('https://sts.example/sync', [], async: true);
        self::assertInstanceOf(PromiseInterface::class, $promise);
        $reason = null;
        $promise->otherwise(static function (mixed $failure) use (&$reason): void {
            self::assertInstanceOf(Throwable::class, $failure);
            $reason = $failure;
        })->wait();
        self::assertInstanceOf(\InvalidArgumentException::class, $reason);
        self::assertCount(0, $this->history);
    }

    public function testSynchronousSetupFailureStillReturnsNull(): void
    {
        $service = $this->service([]);
        $service->setHeaders(['X-Invalid' => "value\r\nInjected: true"]);
        self::assertNull($service->post('https://sts.example/sync', [], returnWithStatusCode: true));
        self::assertCount(0, $this->history);
    }

    public function testConnectivityFallsBackToGetWhenHeadIsRejected(): void
    {
        $service = $this->service([new Response(405), new Response(200)]);
        self::assertTrue($service->checkConnectivity('https://sts.example/health'));
        self::assertSame('HEAD', $this->requestAt(0)->getMethod());
        self::assertSame('GET', $this->requestAt(1)->getMethod());
    }

    public function testAsyncBatchPreservesSuccessesAndThrowableRejections(): void
    {
        $service = $this->service([new Response(200, [], 'ok'), new Response(401, [], 'denied')]);
        $success = $service->post('https://sts.example/sync', ['module' => 'vl'], async: true);
        $failure = $service->post('https://sts.example/sync', ['module' => 'eid'], async: true);
        self::assertInstanceOf(PromiseInterface::class, $success);
        self::assertInstanceOf(PromiseInterface::class, $failure);
        $results = Utils::settle(['vl' => $success, 'eid' => $failure])->wait();
        self::assertSame('fulfilled', $results['vl']['state']);
        self::assertInstanceOf(ResponseInterface::class, $results['vl']['value']);
        self::assertSame('ok', (string) $results['vl']['value']->getBody());
        self::assertSame('rejected', $results['eid']['state']);
        self::assertInstanceOf(ResponseException::class, $results['eid']['reason']);
        self::assertSame(401, $results['eid']['reason']->getResponse()->getStatusCode());
    }
}

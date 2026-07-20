<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\HttpHandlers\InterfaceApi\SubmitResultsHandler;
use App\Middlewares\Api\InterfaceRequestGuardMiddleware;
use App\Services\InterfaceApi\InterfaceConnectionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * The request is rejected before the handler reaches a service, so the handler is
 * built without its constructor: if validation let anything through, these tests
 * would fail on an uninitialised service rather than quietly pass.
 */
final class SubmitResultsHandlerTest extends TestCase
{
    private const VALID_ROW = [
        'id' => 4821,
        'order_id' => 'RVL1223167772',
        'test_id' => 'RVL1223167772',
        'results' => '1250',
        'test_unit' => 'cp/mL',
        'machine_used' => 'Abbott m2000',
    ];

    public function testNonJsonRequestIsRejected(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/interface/results')
            ->withHeader('Content-Type', 'text/plain')
            ->withParsedBody(['results' => [self::VALID_ROW]]);

        [$status, $payload] = $this->invoke($request);

        self::assertSame(415, $status);
        self::assertSame('unsupported_media_type', $payload['error']['code']);
    }

    /**
     * The lab is taken from the credential. A payload that tries to name its own
     * facility must be refused outright rather than silently ignored.
     */
    #[DataProvider('facilitySelectorProvider')]
    public function testPayloadCannotChooseItsOwnFacility(string $field): void
    {
        $row = self::VALID_ROW + [$field => 999];
        [$status, $payload] = $this->invoke($this->jsonRequest(['results' => [$row]]));

        self::assertSame(400, $status);
        self::assertSame('unexpected_field', $payload['error']['code']);
    }

    /** @return list<array{string}> */
    public static function facilitySelectorProvider(): array
    {
        return [['lab_id'], ['facility_id'], ['facilityId'], ['labId']];
    }

    public function testUnexpectedTopLevelFieldIsRejected(): void
    {
        [$status, $payload] = $this->invoke($this->jsonRequest([
            'results' => [self::VALID_ROW],
            'labId' => 999,
        ]));

        self::assertSame(400, $status);
        self::assertSame('unexpected_field', $payload['error']['code']);
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('malformedBodyProvider')]
    public function testMalformedBodiesAreRejected(array $body): void
    {
        [$status, $payload] = $this->invoke($this->jsonRequest($body));

        self::assertSame(400, $status);
        self::assertSame('invalid_request', $payload['error']['code']);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function malformedBodyProvider(): array
    {
        $missingField = self::VALID_ROW;
        unset($missingField['order_id']);

        return [
            'no results key' => [[]],
            'empty batch' => [['results' => []]],
            'not a list' => [['results' => ['a' => self::VALID_ROW]]],
            'row is not an object' => [['results' => ['just a string']]],
            'missing required field' => [['results' => [$missingField]]],
            'id is not an integer' => [['results' => [['id' => 'abc'] + self::VALID_ROW]]],
        ];
    }

    public function testBatchOverTheItemLimitIsRejected(): void
    {
        $rows = array_fill(0, InterfaceConnectionService::RESULTS_MAX_ITEMS + 1, self::VALID_ROW);
        [$status, $payload] = $this->invoke($this->jsonRequest(['results' => $rows]));

        self::assertSame(413, $status);
        self::assertSame('batch_too_large', $payload['error']['code']);
    }

    public function testAValidBatchWithoutACredentialIsUnauthorised(): void
    {
        [$status, $payload] = $this->invoke($this->jsonRequest(['results' => [self::VALID_ROW]]));

        self::assertSame(401, $status);
        self::assertSame('invalid_credential', $payload['error']['code']);
    }

    public function testOversizedResultsBodyIsRejectedByTheGuard(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/interface/results')
            ->withHeader('Content-Length', (string) (InterfaceConnectionService::RESULTS_MAX_BODY_BYTES + 1));

        $response = (new InterfaceRequestGuardMiddleware())->process($request, $this->neverCalled());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(413, $response->getStatusCode());
        self::assertSame('payload_too_large', $payload['error']['code']);
    }

    public function testResultsWithinTheSizeLimitPassTheGuard(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/interface/results')
            ->withHeader('Content-Length', '2048');

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };

        $response = (new InterfaceRequestGuardMiddleware())->process($request, $handler);

        self::assertSame(204, $response->getStatusCode());
    }

    /** @param array<string, mixed> $body */
    private function jsonRequest(array $body): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/interface/results')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($body);
    }

    /** @return array{int, array<string, mixed>} */
    private function invoke(ServerRequestInterface $request): array
    {
        $handler = (new ReflectionClass(SubmitResultsHandler::class))->newInstanceWithoutConstructor();
        $response = $handler($request);

        return [
            $response->getStatusCode(),
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    private function neverCalled(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('The guard should have rejected this request.');
            }
        };
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\HttpHandlers\InterfaceApi\SubmitActivityHandler;
use App\Services\InterfaceApi\InterfaceConnectionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Validation runs before the handler reaches its service, so the handler is built
 * without its constructor: anything slipping past validation would fail here on an
 * uninitialised service rather than quietly passing.
 */
final class SubmitActivityHandlerTest extends TestCase
{
    private const VALID_EVENT = [
        'event_id' => '8f14e45f-ceea-467a-9c1c-7b8a1d2f3c4d',
        'event_type' => 'instrument.connection_failed',
        'event_category' => 'failure',
        'occurred_at' => '2026-07-20 11:34:04',
    ];

    public function testNonJsonRequestIsRejected(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/interface/activity')
            ->withHeader('Content-Type', 'text/plain')
            ->withParsedBody(['events' => [self::VALID_EVENT]]);

        [$status, $payload] = $this->invoke($request);

        self::assertSame(415, $status);
        self::assertSame('unsupported_media_type', $payload['error']['code']);
    }

    /**
     * The tool records a lab identifier of its own. It must not be able to file
     * activity against a lab other than the one its credential is bound to.
     */
    #[DataProvider('labSelectorProvider')]
    public function testEventCannotChooseItsOwnLab(string $field): void
    {
        $event = self::VALID_EVENT + [$field => 42];
        [$status, $payload] = $this->invoke($this->jsonRequest(['events' => [$event]]));

        self::assertSame(400, $status);
        self::assertSame('unexpected_field', $payload['error']['code']);
    }

    /** @return list<array{string}> */
    public static function labSelectorProvider(): array
    {
        return [['lab_id'], ['facility_id'], ['installation_id'], ['received_via']];
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
        $missing = self::VALID_EVENT;
        unset($missing['occurred_at']);

        return [
            'no events key' => [[]],
            'empty batch' => [['events' => []]],
            'not a list' => [['events' => ['a' => self::VALID_EVENT]]],
            'event is not an object' => [['events' => ['a string']]],
            'missing occurred_at' => [['events' => [$missing]]],
        ];
    }

    public function testBatchOverTheItemLimitIsRejected(): void
    {
        $events = array_fill(0, InterfaceConnectionService::ACTIVITY_MAX_ITEMS + 1, self::VALID_EVENT);
        [$status, $payload] = $this->invoke($this->jsonRequest(['events' => $events]));

        self::assertSame(413, $status);
        self::assertSame('batch_too_large', $payload['error']['code']);
    }

    public function testAValidBatchWithoutACredentialIsUnauthorised(): void
    {
        [$status, $payload] = $this->invoke($this->jsonRequest(['events' => [self::VALID_EVENT]]));

        self::assertSame(401, $status);
        self::assertSame('invalid_credential', $payload['error']['code']);
    }

    /** @param array<string, mixed> $body */
    private function jsonRequest(array $body): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/interface/activity')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($body);
    }

    /** @return array{int, array<string, mixed>} */
    private function invoke(ServerRequestInterface $request): array
    {
        $handler = (new ReflectionClass(SubmitActivityHandler::class))->newInstanceWithoutConstructor();
        $response = $handler($request);

        return [
            $response->getStatusCode(),
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        ];
    }
}

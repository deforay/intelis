<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\HttpHandlers\InterfaceApi\SubmitUsageStatisticsHandler;
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
final class SubmitUsageStatisticsHandlerTest extends TestCase
{
    private const VALID_SUMMARY = [
        'aggregate_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'activity_date' => '2026-07-21',
        'total_tests' => 25,
        'successful_tests' => 23,
        'failed_tests' => 2,
        'revision' => 25,
    ];

    public function testNonJsonRequestIsRejected(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/interface/usage-statistics')
            ->withHeader('Content-Type', 'text/plain')
            ->withParsedBody(['summaries' => [self::VALID_SUMMARY]]);

        [$status, $payload] = $this->invoke($request);

        self::assertSame(415, $status);
        self::assertSame('unsupported_media_type', $payload['error']['code']);
    }

    /**
     * The lab is taken from the credential. A summary must not be able to file volume
     * against a lab, or on behalf of an installation, other than its own.
     */
    #[DataProvider('serverOwnedFieldProvider')]
    public function testSummaryCannotChooseItsOwnLabOrInstallation(string $field): void
    {
        $summary = self::VALID_SUMMARY + [$field => 42];
        [$status, $payload] = $this->invoke($this->jsonRequest(['summaries' => [$summary]]));

        self::assertSame(400, $status);
        self::assertSame('unexpected_field', $payload['error']['code']);
    }

    /** @return list<array{string}> */
    public static function serverOwnedFieldProvider(): array
    {
        return [
            ['lab_id'],
            ['facility_id'],
            ['installation_id'],
            ['source_installation_id'],
            ['received_via'],
            ['remote_uploaded_revision'],
        ];
    }

    /** Nothing identifying a sample or a patient has a field to arrive in. */
    #[DataProvider('patientDataFieldProvider')]
    public function testPatientDataHasNowhereToGo(string $field): void
    {
        $summary = self::VALID_SUMMARY + [$field => 'anything'];
        [$status, $payload] = $this->invoke($this->jsonRequest(['summaries' => [$summary]]));

        self::assertSame(400, $status);
        self::assertSame('unexpected_field', $payload['error']['code']);
    }

    /** @return list<array{string}> */
    public static function patientDataFieldProvider(): array
    {
        return [['sample_id'], ['order_id'], ['patient_id'], ['results'], ['raw_text']];
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
        $missingRevision = self::VALID_SUMMARY;
        unset($missingRevision['revision']);

        $missingDate = self::VALID_SUMMARY;
        unset($missingDate['activity_date']);

        return [
            'no summaries key' => [[]],
            'empty batch' => [['summaries' => []]],
            'not a list' => [['summaries' => ['a' => self::VALID_SUMMARY]]],
            'summary is not an object' => [['summaries' => ['a string']]],
            'missing revision' => [['summaries' => [$missingRevision]]],
            'missing activity_date' => [['summaries' => [$missingDate]]],
        ];
    }

    public function testBatchOverTheItemLimitIsRejected(): void
    {
        $summaries = array_fill(
            0,
            InterfaceConnectionService::USAGE_STATISTICS_MAX_ITEMS + 1,
            self::VALID_SUMMARY
        );
        [$status, $payload] = $this->invoke($this->jsonRequest(['summaries' => $summaries]));

        self::assertSame(413, $status);
        self::assertSame('batch_too_large', $payload['error']['code']);
    }

    public function testAValidBatchWithoutACredentialIsUnauthorised(): void
    {
        [$status, $payload] = $this->invoke($this->jsonRequest(['summaries' => [self::VALID_SUMMARY]]));

        self::assertSame(401, $status);
        self::assertSame('invalid_credential', $payload['error']['code']);
    }

    /** @param array<string, mixed> $body */
    private function jsonRequest(array $body): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/interface/usage-statistics')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($body);
    }

    /** @return array{int, array<string, mixed>} */
    private function invoke(ServerRequestInterface $request): array
    {
        $handler = (new ReflectionClass(SubmitUsageStatisticsHandler::class))->newInstanceWithoutConstructor();
        $response = $handler($request);

        return [
            $response->getStatusCode(),
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        ];
    }
}

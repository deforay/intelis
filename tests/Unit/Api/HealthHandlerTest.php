<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use App\HttpHandlers\Api\HealthHandler;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HealthHandlerTest extends TestCase
{
    public function testReachableDatabaseAnswersOkWithVersionAndServerTime(): void
    {
        $now = new DateTimeImmutable('2026-09-02T10:15:00+00:00');
        $response = HealthHandler::respond(true, '5.7.48', '1.5.0', $now);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $payload['status']);
        self::assertSame('5.7.48', $payload['version']);
        self::assertSame('2026-09-02T10:15:00+00:00', $payload['serverTime']);
        self::assertSame('1.5.0', $payload['minAppVersion']);
        self::assertSame('ok', $payload['database']);
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testUnreachableDatabaseAnswers503(): void
    {
        $response = HealthHandler::respond(false, '5.7.48', null);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('unavailable', $payload['status']);
        self::assertSame('unreachable', $payload['database']);
        self::assertNull($payload['minAppVersion']);
    }

    public function testMissingMinimumIsNullNotEmptyString(): void
    {
        $payload = json_decode((string) HealthHandler::respond(true, '5.7.48', null)->getBody(), true);

        self::assertArrayHasKey('minAppVersion', $payload);
        self::assertNull($payload['minAppVersion']);
    }
}

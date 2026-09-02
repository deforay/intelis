<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use App\Middlewares\Api\ApiAuthMiddleware;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class ApiAuthMiddlewareTest extends TestCase
{
    public function testRotatedTokenIsExposedUnderEveryKeyClientsRead(): void
    {
        $response = new Response(200);
        $response->getBody()->write('{"status":"success","data":[1,2]}');

        $rotated = ApiAuthMiddleware::withRotatedToken($response, 'fresh-token');
        $body = (string) $rotated->getBody();
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        // InteLIS Mobile reads `token`; older integrations read `new_token`.
        self::assertSame('fresh-token', $payload['token']);
        self::assertSame('fresh-token', $payload['new_token']);
        self::assertTrue($payload['token_updated']);
        self::assertSame('success', $payload['status']);
        self::assertSame([1, 2], $payload['data']);
    }

    public function testContentLengthMatchesTheRewrittenBody(): void
    {
        $response = (new Response(200))->withHeader('Content-Length', '3');
        $response->getBody()->write('{}');

        $rotated = ApiAuthMiddleware::withRotatedToken($response, 'fresh-token');
        $body = (string) $rotated->getBody();

        self::assertSame((string) strlen($body), $rotated->getHeaderLine('Content-Length'));
        self::assertJson($body);
    }

    public function testNonJsonBodyIsLeftUntouched(): void
    {
        $response = new Response(500);
        $response->getBody()->write('not json');

        $rotated = ApiAuthMiddleware::withRotatedToken($response, 'fresh-token');

        self::assertSame('not json', (string) $rotated->getBody());
    }
}

<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

/**
 * The v2 envelope. One shape for every v2 endpoint, with the HTTP status code
 * carrying the outcome:
 *
 *   200 {"status":"success","data":{...},"timestamp":...}
 *   4xx/5xx {"status":"failed","error":{"code":"...","message":"..."},"timestamp":...}
 *
 * v1.1 answers 200 for everything and puts the outcome in the body because the
 * deployed apps read it that way; v2 is where real status codes live.
 */
final class ApiV2Response
{
    /** @param array<string, mixed> $data */
    public static function success(array $data = [], int $status = 200): ResponseInterface
    {
        return self::json([
            'status' => 'success',
            'data' => $data,
            'timestamp' => time(),
        ], $status);
    }

    public static function error(string $code, string $message, int $status): ResponseInterface
    {
        return self::json([
            'status' => 'failed',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'timestamp' => time(),
        ], $status);
    }

    /** @param array<string, mixed> $payload */
    private static function json(array $payload, int $status): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }
}

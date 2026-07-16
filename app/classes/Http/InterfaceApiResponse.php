<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

final class InterfaceApiResponse
{
    /** @param array<string, mixed> $payload */
    public static function json(array $payload, int $status = 200): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }

    public static function error(string $code, string $message, int $status): ResponseInterface
    {
        return self::json([
            'status' => 'error',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}

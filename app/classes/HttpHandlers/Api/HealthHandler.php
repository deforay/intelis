<?php

declare(strict_types=1);

namespace App\HttpHandlers\Api;

use App\Services\CommonService;
use App\Services\DatabaseService;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Throwable;

/**
 * GET /api/v1.1/health
 *
 * Unauthenticated liveness probe for InteLIS Mobile and for monitoring. Answers
 * 200 with the server version when the database round-trips, 503 otherwise.
 * The maintenance bounce and the bootstrap guard in public/api/index.php cover
 * the two cases where this handler cannot run at all.
 *
 * minAppVersion comes from the global_config row named min_app_version and is
 * null until an administrator sets one; a client must treat null as "no minimum".
 */
final readonly class HealthHandler
{
    public const MIN_APP_VERSION_CONFIG = 'min_app_version';

    public function __construct(private DatabaseService $db, private CommonService $general)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $databaseReachable = false;
        $minAppVersion = null;
        try {
            $databaseReachable = $this->db->ping();
            if ($databaseReachable) {
                $minAppVersion = self::normaliseVersion($this->general->getGlobalConfig(self::MIN_APP_VERSION_CONFIG));
            }
        } catch (Throwable) {
            $databaseReachable = false;
        }

        return self::respond($databaseReachable, defined('VERSION') ? (string) VERSION : null, $minAppVersion);
    }

    public static function respond(
        bool $databaseReachable,
        ?string $version,
        ?string $minAppVersion,
        ?DateTimeImmutable $now = null
    ): ResponseInterface {
        $payload = [
            'status' => $databaseReachable ? 'ok' : 'unavailable',
            'version' => $version,
            'serverTime' => ($now ?? new DateTimeImmutable())->format(DATE_ATOM),
            'minAppVersion' => $minAppVersion,
            'database' => $databaseReachable ? 'ok' : 'unreachable',
        ];

        $response = new Response($databaseReachable ? 200 : 503);
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }

    private static function normaliseVersion(mixed $configured): ?string
    {
        if (!is_string($configured)) {
            return null;
        }
        $configured = trim($configured);

        return $configured === '' ? null : $configured;
    }
}

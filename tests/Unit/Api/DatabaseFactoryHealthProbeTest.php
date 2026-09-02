<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use App\Factories\DatabaseFactory;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Throwable;

/**
 * public/api/index.php answers /api/v1.1/health with a JSON 503 when the
 * database is down. That only works if the factory hands the failure back
 * instead of printing the HTML outage page and exiting, so this pins the
 * rethrow. Runs in its own process because the switch is a constant.
 */
final class DatabaseFactoryHealthProbeTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testConnectionFailureIsRethrownDuringAHealthProbe(): void
    {
        define('INTELIS_HEALTH_PROBE', true);

        // Port 1 on loopback refuses immediately; no server listens there.
        $container = new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                return ['database' => [
                    'host' => '127.0.0.1',
                    'port' => 1,
                    'username' => 'nobody',
                    'password' => 'nothing',
                    'db' => 'nowhere',
                ]];
            }

            public function has(string $id): bool
            {
                return $id === 'applicationConfig';
            }
        };

        $thrown = null;
        try {
            (new DatabaseFactory())($container);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown, 'The connection failure must reach the caller during a health probe.');
    }
}

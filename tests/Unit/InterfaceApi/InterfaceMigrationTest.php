<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use PHPUnit\Framework\TestCase;

final class InterfaceMigrationTest extends TestCase
{
    public function testFoundationIsIndependentAndDisabledByDefault(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 3) . '/sys/migrations/5.5.21.sql');

        self::assertIsString($migration);
        self::assertStringContainsString('interface_api_enabled', $migration);
        self::assertMatchesRegularExpression(
            "/VALUES\s*\([^;]*'interface_api_enabled',\s*'no'/s",
            $migration
        );
        self::assertStringContainsString('UNIQUE KEY `uniq_interface_source_installation`', $migration);
        self::assertStringNotContainsString('`sts_token`', $migration);
        self::assertStringNotContainsString('`vlsm_instance_id`', $migration);
    }
}

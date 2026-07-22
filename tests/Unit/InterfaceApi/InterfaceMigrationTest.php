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

    public function testReconnectMigrationIsAdditiveAndServerOwned(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 3) . '/sys/migrations/5.5.22.sql');

        self::assertIsString($migration);
        self::assertStringContainsString('`purpose` VARCHAR(16) NOT NULL DEFAULT \'new\'', $migration);
        self::assertStringContainsString('`target_installation_id` CHAR(36) NULL', $migration);
        self::assertStringContainsString('`credential_version` INT NOT NULL DEFAULT 1', $migration);
        self::assertStringContainsString('`reconnected_at` DATETIME NULL', $migration);
    }

    /**
     * The same event can arrive from the importer and over the API, so the unique key
     * is what keeps a second delivery from becoming a second row.
     */
    public function testActivityMigrationDedupesAndKeepsTheLabServerOwned(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 3) . '/sys/migrations/5.5.23.sql');

        self::assertIsString($migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `instrument_activity_log`', $migration);
        self::assertStringContainsString('UNIQUE KEY `uniq_instrument_activity_event_uid`', $migration);
        self::assertStringContainsString('`lab_id` INT NOT NULL', $migration);
        self::assertStringContainsString("SET `value` = '5.5.23'", $migration);
    }

    /**
     * A summary is unique per lab rather than globally, so two labs generating the
     * same identifier stay separate instead of overwriting one another.
     */
    public function testUsageStatisticsAreUniquePerLabAndServerOwned(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 3) . '/sys/migrations/5.5.27.sql');

        self::assertIsString($migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `instrument_usage_statistics_daily`', $migration);
        self::assertStringContainsString(
            'UNIQUE KEY `uniq_instrument_usage_lab_aggregate` (`lab_id`, `aggregate_uid`)',
            $migration
        );
        self::assertStringContainsString('`lab_id` INT NOT NULL', $migration);
        self::assertStringContainsString('`revision` INT UNSIGNED NOT NULL', $migration);
        self::assertStringContainsString("SET `value` = '5.5.27'", $migration);
    }

    /**
     * migrate.php runs every statement through MysqliDb::rawAddPrefix(), whose regex
     * rewrites `UPDATE <word>` into `UPDATE ``<word>```. That turns ON UPDATE
     * CURRENT_TIMESTAMP into a syntax error which appears only under the migrator and
     * never via the mysql client, so it is invisible until an install fails. Timestamps
     * in migrations are stamped from PHP instead.
     */
    public function testNoMigrationUsesOnUpdateCurrentTimestamp(): void
    {
        $migrations = glob(dirname(__DIR__, 3) . '/sys/migrations/5.5.*.sql') ?: [];
        self::assertNotEmpty($migrations);

        foreach ($migrations as $path) {
            $contents = file_get_contents($path);
            self::assertIsString($contents, "Could not read $path");

            // Comments warning about the pattern are the point; only real DDL matters.
            $ddl = preg_replace('/^\s*--.*$/m', '', $contents);
            self::assertDoesNotMatchRegularExpression(
                '/\bON\s+UPDATE\s+CURRENT_TIMESTAMP\b/i',
                (string) $ddl,
                basename($path) . ' uses ON UPDATE CURRENT_TIMESTAMP, which migrate.php mangles'
            );
        }
    }

    /** The word telemetry is not used anywhere in the application. */
    public function testActivityIsNotCalledTelemetry(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root . '/sys/migrations/5.5.23.sql',
            $root . '/sys/migrations/5.5.27.sql',
            $root . '/app/classes/Services/InstrumentActivityService.php',
            $root . '/app/classes/Services/InstrumentUsageStatisticsService.php',
            $root . '/app/classes/HttpHandlers/InterfaceApi/SubmitUsageStatisticsHandler.php',
            $root . '/app/classes/HttpHandlers/InterfaceApi/SubmitActivityHandler.php',
            $root . '/app/classes/Services/InterfaceApi/InterfaceConnectionService.php',
            $root . '/app/classes/Services/InterfaceApi/InterfaceInstallationService.php',
        ];

        foreach ($paths as $path) {
            $contents = file_get_contents($path);
            self::assertIsString($contents, "Could not read $path");
            self::assertStringNotContainsStringIgnoringCase('telemetry', $contents, basename($path));
        }
    }
}

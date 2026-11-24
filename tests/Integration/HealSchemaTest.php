<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli;
use PHPUnit\Framework\TestCase;
use SchemaRepairPlan;
use Tests\Support\TestRun;

/**
 * bin/heal-schema.php's repairs, run against real MySQL.
 *
 * Builds a database from sql/init.sql, removes tables and columns from it, and
 * checks the plan puts back exactly what a fresh install has, foreign keys
 * included, without touching the rows already there.
 */
final class HealSchemaTest extends TestCase
{
    private const DATABASE = 'intelis_heal_schema_test';

    private static ?mysqli $db = null;

    private static string $database = '';

    public static function setUpBeforeClass(): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');
        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }
        require_once dirname(__DIR__, 2) . '/bin/lib/SchemaRepairPlan.php';

        self::$database = TestRun::database(self::DATABASE);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');
        $db = new mysqli($host, $user, $password, null, (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306));
        $db->query('DROP DATABASE IF EXISTS `' . self::$database . '`');
        $db->query('CREATE DATABASE `' . self::$database . '`');
        $db->select_db(self::$database);
        self::loadSql($db, (string) file_get_contents(dirname(__DIR__, 2) . '/sql/init.sql'));
        self::$db = $db;
    }

    public static function tearDownAfterClass(): void
    {
        self::$db?->query('DROP DATABASE IF EXISTS `' . self::$database . '`');
        self::$db?->close();
        self::$db = null;
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run.');
        }
    }

    public function testTheRepairRestoresWhatAFreshInstallHas(): void
    {
        $db = self::$db;
        $fresh = $this->columns();
        $freshKeys = $this->foreignKeys();
        $this->assertArrayHasKey('covid19_tests', $freshKeys, 'The fixture needs a table with a foreign key.');

        $db->query(
            'INSERT INTO `instruments` (`instrument_id`, `machine_name`, `status`)'
                . " VALUES ('heal-probe', 'Probe', 'active')"
        );
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        $db->query('DROP TABLE `covid19_tests`');
        $db->query('DROP TABLE `request_pull_state`');
        $db->query('SET FOREIGN_KEY_CHECKS = 1');
        $db->query('ALTER TABLE `instruments` DROP COLUMN `instrument_make`, DROP COLUMN `added_on`');

        $repair = new SchemaRepairPlan((string) file_get_contents(dirname(__DIR__, 2) . '/sql/init.sql'));
        $plan = $repair->build($this->columns());
        $this->assertCount(3, $plan);

        foreach ($plan as $step) {
            if ($step['create']) {
                $db->query('SET FOREIGN_KEY_CHECKS = 0');
                $db->query($step['sql']);
                $db->query('SET FOREIGN_KEY_CHECKS = 1');
            } else {
                $db->query($step['sql']);
            }
        }

        $this->assertSame($fresh, $this->columns());
        $this->assertSame($freshKeys, $this->foreignKeys());
        $this->assertSame([], $repair->build($this->columns()), 'A second run finds nothing to do.');
        $row = $db->query("SELECT `machine_name` FROM `instruments` WHERE `instrument_id` = 'heal-probe'")
            ->fetch_assoc();
        $this->assertSame('Probe', $row['machine_name'] ?? null, 'The rows already there are untouched.');
    }

    /** @return array<string, list<string>> */
    private function columns(): array
    {
        $columns = [];
        $result = self::$db->query(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, COLUMN_NAME'
        );
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $columns[(string) $row['TABLE_NAME']][] = (string) $row['COLUMN_NAME'];
        }
        return $columns;
    }

    /** @return array<string, list<string>> table => "constraint -> referenced table" */
    private function foreignKeys(): array
    {
        $keys = [];
        $result = self::$db->query(
            'SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME'
        );
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $keys[(string) $row['TABLE_NAME']][] = $row['CONSTRAINT_NAME'] . ' -> ' . $row['REFERENCED_TABLE_NAME'];
        }
        return $keys;
    }

    private static function loadSql(mysqli $db, string $sql): void
    {
        if ($db->multi_query($sql)) {
            do {
                if ($result = $db->store_result()) {
                    $result->free();
                }
            } while ($db->more_results() && $db->next_result());
        }
    }
}

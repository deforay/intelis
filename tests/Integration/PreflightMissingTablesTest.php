<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\MigrationRunnerFunctions;

/**
 * What `intelis check` actually reports when a table is gone.
 *
 * The classification is unit-tested on its own, and that is not enough on its
 * own: deleting the call, or softening PF_FAIL back to PF_WARN, leaves those
 * tests green while the check goes back to passing an installation whose module
 * lost the table it writes to. That is the exact failure being fixed, so it has
 * to be asserted where an operator would see it -- in the output and in the
 * exit code.
 *
 * bin/preflight.php is zero-dependency by design and locates everything from
 * its own directory, so it can simply be copied into a throwaway root beside a
 * fixture sql/init.sql and a config pointing at the test database, and run. The
 * other checks fail in there (no vendor, no composer.lock) and are ignored;
 * only the missing-table findings are read.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class PreflightMissingTablesTest extends TestCase
{
    private static ?string $root = null;

    public static function setUpBeforeClass(): void
    {
        if (MigrationRunnerFunctions::connect() === null) {
            return;
        }

        // The config written below carries the test database password in
        // plaintext, so the tree it lives in is the owner's alone -- on a shared
        // build host a world-readable temp directory hands that password to
        // every account on the machine.
        $root = sys_get_temp_dir() . '/intelis-preflight-root-' . getmypid();
        @mkdir($root, 0700, true);
        @mkdir($root . '/bin', 0700, true);
        @mkdir($root . '/sql', 0700, true);
        @mkdir($root . '/configs', 0700, true);
        $repo = dirname(__DIR__, 2);
        @mkdir($root . '/bin/lib', 0777, true);
        copy($repo . '/bin/preflight.php', $root . '/bin/preflight.php');
        copy($repo . '/bin/lib/help.php', $root . '/bin/lib/help.php');

        // preflight stops at the first thing that makes the rest meaningless,
        // and a root with no vendor tree is one of them -- it renders and exits
        // before reaching the database. The real ones are borrowed so the run
        // gets as far as the schema checks, which are what is under test.
        copy($repo . '/composer.json', $root . '/composer.json');
        copy($repo . '/composer.lock', $root . '/composer.lock');
        @symlink($repo . '/vendor', $root . '/vendor');

        file_put_contents(
            $root . '/configs/config.production.php',
            "<?php return ['database' => ["
                . "'host' => " . var_export((string) getenv('INTELIS_TEST_DB_HOST'), true) . ','
                . "'port' => " . var_export((string) (getenv('INTELIS_TEST_DB_PORT') ?: '3306'), true) . ','
                . "'username' => " . var_export((string) getenv('INTELIS_TEST_DB_USER'), true) . ','
                . "'password' => " . var_export((string) (getenv('INTELIS_TEST_DB_PASS') ?: ''), true) . ','
                . "'db' => " . var_export(MigrationRunnerFunctions::DATABASE, true)
                . ']];'
        );
        chmod($root . '/configs/config.production.php', 0600);

        self::$root = $root;

        // The parent exists and holds a row; both children are declared by the
        // fixture seed and absent from the database, which is the shape DRC is
        // in with covid19_tests.
        $db = MigrationRunnerFunctions::connect();
        $db->rawQuery('DROP TABLE IF EXISTS `pf_child_used`');
        $db->rawQuery('DROP TABLE IF EXISTS `pf_child_dormant`');
        $db->rawQuery('DROP TABLE IF EXISTS `pf_parent_used`');
        $db->rawQuery('DROP TABLE IF EXISTS `pf_parent_dormant`');
        // The populated parent is deliberately left with a row estimate of
        // zero, which is the state the exact probe exists for. Persistent
        // statistics with auto-recalc off, analysed while empty, record
        // n_rows = 0; the row inserted afterwards never updates them. So
        // information_schema reports an empty table while SELECT ... LIMIT 1
        // finds a row -- and an implementation that went back to reading the
        // estimate would call this module dormant and pass.
        $db->rawQuery(
            'CREATE TABLE `pf_parent_used` (`id` INT NOT NULL PRIMARY KEY)'
            . ' ENGINE=InnoDB STATS_PERSISTENT=1, STATS_AUTO_RECALC=0'
        );
        $db->rawQuery('ANALYZE TABLE `pf_parent_used`');
        $db->rawQuery('INSERT INTO `pf_parent_used` (`id`) VALUES (1)');
        $db->rawQuery('CREATE TABLE `pf_parent_dormant` (`id` INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$root === null) {
            return;
        }
        foreach (
            [
            '/bin/preflight.php',
            '/bin/lib/help.php',
            '/composer.json',
            '/composer.lock',
            '/vendor',
            '/sql/init.sql',
            '/configs/config.production.php',
            ] as $f
        ) {
            @unlink(self::$root . $f);
        }
        foreach (['/bin/lib', '/bin', '/sql', '/configs', ''] as $d) {
            @rmdir(self::$root . $d);
        }
    }

    protected function setUp(): void
    {
        if (self::$root === null) {
            $this->markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run.');
        }
    }

    /** Write the fixture seed and run the check the way an operator does. */
    private function check(string $initSql): array
    {
        file_put_contents(self::$root . '/sql/init.sql', $initSql);

        $output = [];
        $status = 0;
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(self::$root . '/bin/preflight.php') . ' 2>&1';
        exec($command, $output, $status);

        return [implode("\n", $output), $status];
    }

    private const PARENT_USED = "CREATE TABLE `pf_parent_used` (\n"
        . "  `id` int NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n";

    private const PARENT_DORMANT = "CREATE TABLE `pf_parent_dormant` (\n"
        . "  `id` int NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n";

    /**
     * The fixture is in the state the rest of this class depends on.
     *
     * If a future MySQL recalculates the estimate anyway, the parent stops
     * being stale-zero and the test below would pass against an implementation
     * that reads the estimate -- covering nothing while looking green.
     */
    public function testTheFixtureParentReportsZeroRowsWhileHoldingOne(): void
    {
        $db = MigrationRunnerFunctions::connect();

        $estimate = $db->rawQueryOne(
            'SELECT TABLE_ROWS AS n FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['pf_parent_used']
        );

        $this->assertSame(0, (int) ($estimate['n'] ?? -1), 'The estimate has to be the stale zero.');
        $this->assertNotNull(
            $db->rawQueryOne('SELECT 1 AS one FROM `pf_parent_used` LIMIT 1'),
            'while the table really does hold a row.'
        );
    }

    /** The DRC shape: the child is gone and its parent holds requests. */
    public function testAnAbsentTableWhoseParentHoldsRowsFailsTheCheck(): void
    {
        [$out, $status] = $this->check(
            self::PARENT_USED
            . "CREATE TABLE `pf_child_used` (\n"
            . "  `id` int NOT NULL,\n"
            . "  `parent_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`id`),\n"
            . "  CONSTRAINT `pf_c1` FOREIGN KEY (`parent_id`) REFERENCES `pf_parent_used` (`id`)\n"
            . ") ENGINE=InnoDB;\n"
        );

        // Matched with the severity attached. Asserting only that the line is
        // present would survive PF_FAIL being softened to PF_WARN, which is the
        // mutation this test exists to catch -- and the exit code cannot carry
        // it, because the throwaway root fails other checks of its own.
        $this->assertMatchesRegularExpression(
            '/FAIL\s+Missing tables in use/',
            $out,
            "The finding has to be a failure, not a note. Full output:\n$out"
        );
        $this->assertStringContainsString('pf_child_used', $out);
        $this->assertStringContainsString('pf_parent_used holds', $out, 'and it has to say what proves use.');
        $this->assertStringNotContainsString(
            'holds ~0 rows',
            $out,
            'The evidence that a table holds rows must not read as an empty table.'
        );
        $this->assertSame(1, $status, 'A module that lost its table must not exit 0.');
    }

    /** The same shape with an empty parent stays a warning. */
    public function testAnAbsentTableWhoseParentIsEmptyIsOnlyAWarning(): void
    {
        [$out] = $this->check(
            self::PARENT_DORMANT
            . "CREATE TABLE `pf_child_dormant` (\n"
            . "  `id` int NOT NULL,\n"
            . "  `parent_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`id`),\n"
            . "  CONSTRAINT `pf_c2` FOREIGN KEY (`parent_id`) REFERENCES `pf_parent_dormant` (`id`)\n"
            . ") ENGINE=InnoDB;\n"
        );

        // With the severity attached: softened to PF_OK the line survives this
        // assertion but vanishes from `intelis check --quiet`, which is what an
        // operator is asked to send.
        $this->assertMatchesRegularExpression(
            '/WARN\s+Missing tables/',
            $out,
            "An absent table is still worth saying. Full output:\n$out"
        );
        $this->assertStringNotContainsString(
            'Missing tables in use',
            $out,
            'An unused module must not be reported as broken; that is what makes the failure worth reading.'
        );
    }

    /**
     * A module removed entirely is still only a warning.
     *
     * Both the parent and the child are absent, which is what a deployment
     * that never enabled a module looks like. Probing the parent raises 1146,
     * and reading that as "could not be read" would turn every such install
     * red for tables it was never meant to have.
     */
    public function testAModuleWhoseParentIsAlsoAbsentStaysAWarning(): void
    {
        [$out] = $this->check(
            "CREATE TABLE `pf_absent_parent` (\n  `id` int NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n"
            . "CREATE TABLE `pf_absent_child` (\n"
            . "  `id` int NOT NULL,\n"
            . "  `parent_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`id`),\n"
            . "  CONSTRAINT `pf_c3` FOREIGN KEY (`parent_id`) REFERENCES `pf_absent_parent` (`id`)\n"
            . ") ENGINE=InnoDB;\n"
        );

        $this->assertStringNotContainsString(
            'Missing tables in use',
            $out,
            "A module that was never enabled must not be a failure. Full output:\n$out"
        );
    }

    /**
     * A table the seed itself fills is required whatever its parents say.
     *
     * roles_privileges_map is the real one: seeded, and referencing two tables
     * that are seeded too, so judging it by parent rows alone files a missing
     * ACL table as dormant while every privilege lookup fails on it.
     */
    public function testAnAbsentTableThatTheSeedFillsFailsWithoutConsultingParents(): void
    {
        [$out, $status] = $this->check(
            self::PARENT_DORMANT
            . "CREATE TABLE `pf_child_dormant` (\n"
            . "  `id` int NOT NULL,\n"
            . "  `parent_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`id`),\n"
            . "  CONSTRAINT `pf_c2` FOREIGN KEY (`parent_id`) REFERENCES `pf_parent_dormant` (`id`)\n"
            . ") ENGINE=InnoDB;\n"
            . "INSERT INTO `pf_child_dormant` (`id`, `parent_id`) VALUES (1, 1);\n"
        );

        $this->assertMatchesRegularExpression('/FAIL\s+Missing tables in use/', $out, "Full output:\n$out");
        $this->assertStringContainsString('sql/init.sql seeds it', $out);
        $this->assertSame(1, $status);
    }
}

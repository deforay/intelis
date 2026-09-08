<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use mysqli;
use PHPUnit\Framework\TestCase;

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

    /**
     * A database of this class's own, not the one the migration suites share.
     *
     * The fixture works by leaving real tables in particular states -- a
     * two-column form_covid19, no form_generic at all -- and the migration
     * tests use those same names for their own purposes. Sharing a schema
     * would make one suite or the other fail depending on the order PHPUnit
     * happened to run them in, which it did: MigrationRepairsStrandedSchemaTest
     * builds a form_covid19 and this class was dropping it.
     *
     * The name carries the process id for the same reason one step out. Two
     * suites running at once against one MySQL -- two terminals, or a watcher
     * beside a manual run -- would otherwise drop and recreate this database
     * underneath each other, and the fixture would be gone by the time the
     * assertions read it. That is not hypothetical either: it is where the
     * intermittent failure in this class came from.
     */
    private const DATABASE = 'intelis_preflight_test';

    private static ?DatabaseService $db = null;

    private static string $database = self::DATABASE;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');
        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }

        $port     = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        self::$database = self::DATABASE . '_' . getmypid();

        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::$database . '`');
        $bootstrap->query('CREATE DATABASE `' . self::$database . '`');
        $bootstrap->close();

        self::$db = new DatabaseService([
            'host' => $host, 'username' => $user, 'password' => $password,
            'db' => self::$database, 'port' => $port,
        ]);

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
                . "'db' => " . var_export(self::$database, true)
                . ']];'
        );
        chmod($root . '/configs/config.production.php', 0600);

        self::$root = $root;

        // Three modules in three states, so one run of the check exercises
        // every branch of the classification at once.
        $db = self::$db;

        // form_covid19 is deliberately left with a row estimate of zero, which
        // is the state the exact probe exists for. Persistent statistics with
        // auto-recalc off, analysed while empty, record n_rows = 0; the row
        // inserted afterwards never updates them. So information_schema reports
        // an empty table while SELECT ... LIMIT 1 finds a row -- and an
        // implementation that went back to reading the estimate would call the
        // module dormant and pass.
        $db->rawQuery(
            'CREATE TABLE `form_covid19` (`covid19_id` INT NOT NULL PRIMARY KEY)'
            . ' ENGINE=InnoDB STATS_PERSISTENT=1, STATS_AUTO_RECALC=0'
        );
        $db->rawQuery('ANALYZE TABLE `form_covid19`');
        $db->rawQuery('INSERT INTO `form_covid19` (`covid19_id`) VALUES (1)');

        // form_tb is present and empty: the module was never used.
        $db->rawQuery('CREATE TABLE `form_tb` (`tb_id` INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');

        // form_generic is left uncreated, along with its result table: the
        // module was removed rather than broken. The database is made fresh
        // above, so "never created" is a state this class controls rather than
        // one it inherits.
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db !== null) {
            self::$db->rawQuery('DROP DATABASE IF EXISTS `' . self::$database . '`');
            self::$db = null;
        }

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
     * If a future MySQL recalculates the estimate anyway, form_covid19 stops
     * being stale-zero and the test below would pass against an implementation
     * that reads the estimate -- covering nothing while looking green.
     */
    public function testTheRequestTableReportsZeroRowsWhileHoldingOne(): void
    {
        $db = self::$db;

        $estimate = $db->rawQueryOne(
            'SELECT TABLE_ROWS AS n FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['form_covid19']
        );

        $this->assertSame(0, (int) ($estimate['n'] ?? -1), 'The estimate has to be the stale zero.');
        $this->assertNotNull(
            $db->rawQueryOne('SELECT 1 AS one FROM `form_covid19` LIMIT 1'),
            'while the table really does hold a row.'
        );
    }

    /**
     * The other two module states are the states they claim to be.
     *
     * The removed-module case is the one that can rot without saying so: if
     * anything ever leaves a form_generic behind, that case silently becomes a
     * second copy of the unused-module case and the absent-request-table branch
     * stops being exercised at all.
     */
    public function testTheUnusedAndRemovedModulesAreSetUpAsClaimed(): void
    {
        $db = self::$db;

        $present = static fn(string $t): bool => $db->rawQueryOne(
            'SELECT 1 AS one FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$t]
        ) !== null;

        $this->assertTrue($present('form_tb'), 'The unused module keeps its request table.');
        $this->assertNull(
            $db->rawQueryOne('SELECT 1 AS one FROM `form_tb` LIMIT 1'),
            'and that table has to be empty, or it is not the unused case.'
        );
        $this->assertFalse($present('form_generic'), 'The removed module has no request table at all.');
    }

    /** The seed as it stands for all three modules plus one seeded table. */
    private function seed(): string
    {
        return "CREATE TABLE `form_covid19` (\n  `covid19_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`covid19_id`)\n) ENGINE=InnoDB;\n"
            . "CREATE TABLE `covid19_tests` (\n  `test_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`test_id`)\n) ENGINE=InnoDB;\n"
            . "CREATE TABLE `form_tb` (\n  `tb_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`tb_id`)\n) ENGINE=InnoDB;\n"
            . "CREATE TABLE `tb_tests` (\n  `test_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`test_id`)\n) ENGINE=InnoDB;\n"
            . "CREATE TABLE `form_generic` (\n  `generic_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`generic_id`)\n) ENGINE=InnoDB;\n"
            . "CREATE TABLE `generic_test_results` (\n  `result_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`result_id`)\n) ENGINE=InnoDB;\n"
            // Declared by the seed, never created here, and none of them
            // seeded or any module's result table -- so only PF_CORE_TABLES can
            // reach them. More than one, because a call site passing a subset of
            // the constant would satisfy a test that only ever checks a single
            // entry.
            . "CREATE TABLE `user_details` (\n  `user_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`user_id`)\n) ENGINE=InnoDB;\n"
            . "CREATE TABLE `user_login_history` (\n  `id` int NOT NULL,\n"
            . "  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n"
            . "CREATE TABLE `user_facility_map` (\n  `id` int NOT NULL,\n"
            . "  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n"
            . "CREATE TABLE `s_app_menu` (\n  `id` int NOT NULL,\n"
            . "  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n"
            . "CREATE TABLE `instruments` (\n  `instrument_id` int NOT NULL,\n"
            . "  PRIMARY KEY (`instrument_id`)\n) ENGINE=InnoDB;\n";
    }

    /**
     * The DRC shape: requests are there and the result table is gone.
     *
     * Matched with the severity attached. Asserting only that the line is
     * present would survive PF_FAIL being softened to PF_WARN, which is one of
     * the mutations this test exists to catch -- and the exit code cannot carry
     * it, because the throwaway root fails other checks of its own.
     */
    public function testAModuleWithRequestsAndNoResultTableFailsTheCheck(): void
    {
        [$out, $status] = $this->check($this->seed());

        $this->assertMatchesRegularExpression(
            '/FAIL\s+Missing tables in use/',
            $out,
            "The finding has to be a failure, not a note. Full output:\n$out"
        );
        $this->assertStringContainsString('covid19_tests', $out);
        $this->assertStringContainsString('form_covid19 holds', $out, 'and it has to say what proves use.');
        $this->assertStringNotContainsString(
            'holds ~0 rows',
            $out,
            'The evidence that a table holds rows must not read as an empty table.'
        );
        $this->assertSame(1, $status, 'A module that lost its table must not exit 0.');
    }

    /**
     * The other two modules in the same run stay warnings.
     *
     * tb_tests is absent with form_tb present and empty -- a module that was
     * never used. generic_test_results is absent with form_generic absent too
     * -- a module that was removed. Reporting either as broken is what makes
     * the covid19_tests failure worth reading.
     */
    public function testUnusedAndRemovedModulesStayWarnings(): void
    {
        [$out] = $this->check($this->seed());

        $this->assertMatchesRegularExpression(
            '/WARN\s+Missing tables/',
            $out,
            "An absent table is still worth saying. Full output:\n$out"
        );

        $failure = substr($out, (int) strpos($out, 'Missing tables in use'));
        $this->assertStringNotContainsString('tb_tests', $failure, 'A module never used is not broken.');
        $this->assertStringNotContainsString(
            'generic_test_results',
            $failure,
            'A module removed entirely is not broken either.'
        );
    }

    /**
     * A table nobody can log in without is a failure on its own.
     *
     * user_details is not seeded and is no module's result table, so both other
     * rules answer "no" for it. Without PF_CORE_TABLES reaching the classifier
     * it drops to a warning and `intelis check` passes an installation where
     * nobody can get in -- and passing the constant is a separate line from
     * using it, so it needs asserting here rather than in the unit tests.
     */
    public function testEveryAbsentCoreTableFails(): void
    {
        [$out, $status] = $this->check($this->seed());

        $this->assertMatchesRegularExpression('/FAIL\s+Missing tables in use/', $out, "Full output:\n$out");
        $this->assertStringContainsString('logging in or drawing a page reads it', $out);
        $this->assertSame(1, $status);

        // Each one named, not just the first. A call site handing the classifier
        // a subset of PF_CORE_TABLES would still fail one table and pass this
        // test if it only ever looked for one.
        $failure = substr($out, (int) strpos($out, 'Missing tables in use'));
        foreach (['user_details', 'user_login_history', 'user_facility_map', 's_app_menu', 'instruments'] as $t) {
            $this->assertStringContainsString(
                $t,
                $failure,
                "{$t} is in PF_CORE_TABLES and absent here, so the check has to name it."
            );
        }
    }

    /**
     * A table the seed itself fills is required whatever else is true.
     *
     * roles_privileges_map is the real one: seeded, and referencing two tables
     * that are seeded too, so nothing but this rule can reach it while every
     * privilege lookup in the application fails on it.
     */
    public function testAnAbsentTableThatTheSeedFillsFails(): void
    {
        [$out, $status] = $this->check(
            $this->seed()
            . "CREATE TABLE `pf_seeded_probe` (\n  `id` int NOT NULL,\n"
            . "  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n"
            . "INSERT INTO `pf_seeded_probe` (`id`) VALUES (1);\n"
        );

        $this->assertMatchesRegularExpression('/FAIL\s+Missing tables in use/', $out, "Full output:\n$out");
        $this->assertStringContainsString('sql/init.sql seeds it', $out);
        $this->assertSame(1, $status);
    }
}

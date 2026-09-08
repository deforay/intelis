<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Services\TestsService;
use PHPUnit\Framework\TestCase;

/**
 * How preflight decides a missing table is harmless.
 *
 * It used to decide by assertion: a table in sql/init.sql that is absent from
 * the database was reported as a warning reading "harmless if this instance
 * never used them". Nothing established that it never used them. DRC is missing
 * covid19_tests while form_covid19 holds 51,459 requests taken between 2021 and
 * 2024, so every COVID page there fails on "Table doesn't exist" the moment
 * anyone opens one -- and the check called it harmless.
 *
 * The first attempt at answering it read foreign keys out of sql/init.sql, on
 * the reasoning that a child table hangs off the table whose rows would show
 * the module in use. That reads like the right question and is not. Of the
 * twelve foreign keys the seed declares, three carry that meaning and the rest
 * hang off facility_details or batch_details, which every installation
 * populates -- so an instance that had dropped report_to_mail, a table the
 * application has not referenced in years, would have been failed for it.
 *
 * What actually means it is the test-type map: a module's requests are in its
 * form table and its per-test results in its child table. That map lives in
 * TestsService, and these tests hold preflight's transcription of it to
 * account.
 *
 * bin/preflight.php runs its own body on include -- it is a script, and
 * including it would connect to a database and print a report -- so what it
 * declares is lifted out with the tokenizer. What runs below is the shipped
 * code rather than a copy of it.
 */
final class PreflightSchemaReferencesTest extends TestCase
{
    private const WANTED = [
        'pf_parse_init_schema',
        'pf_parse_init_seeded',
        'pf_classify_missing_tables',
    ];

    public static function setUpBeforeClass(): void
    {
        if (function_exists('pf_classify_missing_tables')) {
            return;
        }

        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/bin/preflight.php');
        $tokens = token_get_all($source);
        $out    = "<?php\n";
        $n      = count($tokens);

        for ($i = 0; $i < $n; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
                continue;
            }
            if (!in_array($tokens[$j][1], self::WANTED, true)) {
                continue;
            }

            $depth   = 0;
            $started = false;
            $body    = '';
            for ($k = $i; $k < $n; $k++) {
                $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                $body .= $text;
                if ($text === '{') {
                    $depth++;
                    $started = true;
                } elseif ($text === '}') {
                    $depth--;
                    if ($started && $depth === 0) {
                        break;
                    }
                }
            }
            $out .= "\n" . $body . "\n";
        }

        // The two tables the decision is made from, as well as the functions. A
        // test that restates either list in its own words cannot notice an
        // entry being dropped from the one that runs.
        foreach (['PF_CORE_TABLES', 'PF_CHILD_RESULT_TABLES'] as $constant) {
            if (preg_match('/^const ' . $constant . ' = \[.*?\];$/ms', $source, $m) === 1) {
                $out .= "\n" . $m[0] . "\n";
            }
        }

        $tmp = sys_get_temp_dir() . '/intelis-preflight-fns-' . getmypid() . '.php';
        file_put_contents($tmp, $out);
        require $tmp;
        unlink($tmp);
    }

    /**
     * The transcription against the map it was transcribed from.
     *
     * preflight cannot call TestsService -- it runs before the application
     * boots, which is the point of it -- so the pairs are written out by hand.
     * This is what stops a fourth test type gaining a child result table and
     * quietly not being covered.
     */
    public function testTheChildTableMapMatchesTestsService(): void
    {
        $expected = [];
        foreach (TestsService::getTestTypes() as $meta) {
            $child = $meta['childResultTable'] ?? null;
            $form  = $meta['tableName'] ?? null;
            if (is_string($child) && $child !== '' && is_string($form) && $form !== '') {
                $expected[$child] = $form;
            }
        }

        $this->assertNotEmpty($expected, 'No test type declares a child result table; the map cannot be empty.');
        $this->assertSame(
            $expected,
            PF_CHILD_RESULT_TABLES,
            'preflight\'s copy of the test-type child tables has drifted from TestsService.'
        );
    }

    /** The case this was built for: requests present, result table gone. */
    public function testAModuleWithRequestsAndNoResultTableIsNeeded(): void
    {
        [$needed, $dormant] = pf_classify_missing_tables(
            ['covid19_tests'],
            [],
            [],
            ['covid19_tests' => 'form_covid19'],
            static fn(string $t): ?bool => $t === 'form_covid19',
        );

        $this->assertSame(['covid19_tests' => ['form_covid19']], $needed);
        $this->assertSame([], $dormant);
    }

    /** The same module on an instance that never enabled it. */
    public function testAModuleWithNoRequestsStaysAWarning(): void
    {
        [$needed, $dormant] = pf_classify_missing_tables(
            ['covid19_tests'],
            [],
            [],
            ['covid19_tests' => 'form_covid19'],
            static fn(string $t): ?bool => false,
        );

        $this->assertSame([], $needed, 'An unused module must not be reported as broken.');
        $this->assertSame(['covid19_tests'], $dormant);
    }

    /**
     * A table that is nobody's result table is not judged by anything else.
     *
     * This is the rule the foreign-key version got wrong. report_to_mail hangs
     * off batch_details, which every installation populates, and is referenced
     * nowhere in the application -- so parenthood would have failed every
     * instance that dropped it.
     */
    public function testATableThatIsNoModulesResultTableStaysAWarning(): void
    {
        [$needed, $dormant] = pf_classify_missing_tables(
            ['report_to_mail'],
            [],
            [],
            PF_CHILD_RESULT_TABLES,
            static fn(string $t): ?bool => true,
        );

        $this->assertSame([], $needed, 'A dead table over a populated one is not evidence of anything.');
        $this->assertSame(['report_to_mail'], $dormant);
    }

    /**
     * A table the application cannot run without is never filed as dormant.
     *
     * user_details is the case: its only foreign key points at roles, which the
     * seed fills, and it is no module's result table, so nothing else in the
     * check can reach it -- on an instance where nobody can log in.
     */
    public function testACoreTableIsNeededWithoutAnyOtherEvidence(): void
    {
        [$needed, $dormant] = pf_classify_missing_tables(
            ['user_details'],
            [],
            ['user_details' => true],
            [],
            static fn(string $t): ?bool => false,
        );

        $this->assertSame(
            ['user_details' => ['logging in or drawing a page reads it']],
            $needed,
            'A missing login table cannot be a note.'
        );
        $this->assertSame([], $dormant);
    }

    /** A table the seed fills is required by the seed filling it. */
    public function testASeededTableIsNeeded(): void
    {
        [$needed, $dormant] = pf_classify_missing_tables(
            ['roles_privileges_map'],
            ['roles_privileges_map' => true],
            [],
            [],
            static fn(string $t): ?bool => false,
        );

        $this->assertSame(['roles_privileges_map' => ['sql/init.sql seeds it']], $needed);
        $this->assertSame([], $dormant);
    }

    /**
     * An unreadable request table is reported, not read as an empty one.
     *
     * A probe that threw says less than one that returned no rows, so treating
     * the two alike would let a corrupt or permission-denied table pass as
     * evidence that the module was never used.
     */
    public function testARequestTableThatCouldNotBeReadIsEvidenceInItself(): void
    {
        [$needed, $dormant] = pf_classify_missing_tables(
            ['tb_tests'],
            [],
            [],
            ['tb_tests' => 'form_tb'],
            static fn(string $t): ?bool => null,
        );

        $this->assertSame(['tb_tests' => ['form_tb could not be read']], $needed);
        $this->assertSame([], $dormant);
    }

    /**
     * The real constant, and every table in it still on one of the two paths.
     *
     * Read from bin/preflight.php rather than restated here, because a second
     * copy of the list would keep passing while an entry was removed from the
     * one that runs -- and the removed entry is a table the application needs,
     * downgraded to a note.
     */
    public function testEveryCoreTableIsStillReachedByLoggingInOrDrawingAPage(): void
    {
        $this->assertTrue(
            defined('PF_CORE_TABLES'),
            'The constant was not lifted; the assertions below prove nothing.'
        );
        $this->assertNotEmpty(PF_CORE_TABLES);

        $repo = dirname(__DIR__, 3);

        // header.php is included by every page in the application, so what it
        // reaches is as unavoidable as what login reaches.
        $entry  = (string) file_get_contents($repo . '/app/login/loginProcess.php')
            . "\n" . (string) file_get_contents($repo . '/app/header.php');
        $source = $entry;

        // Only the methods those two actually call, rather than whole service
        // classes: CommonService alone mentions most of the schema, so
        // searching it entire would confirm anything put in front of it.
        foreach (self::reachableMethods($entry) as [$file, $method]) {
            // The property initialisers come too. A service commonly names its
            // table once, as `protected string $table = '...'`, and works
            // through $this->table thereafter -- AppMenuService does exactly
            // that -- so the method body alone never mentions the table it
            // reads.
            $body    = self::methodBody($repo . $file, $method);
            $source .= "\n" . $body . "\n" . self::scalarProperties($repo . $file, $body);
        }

        foreach (array_keys(PF_CORE_TABLES) as $table) {
            $this->assertStringContainsString(
                (string) $table,
                $source,
                "PF_CORE_TABLES claims logging in or drawing a page reads {$table}, "
                    . 'and nothing on either path mentions it any more.'
            );
        }
    }

    /** The ones that are invisible from the files that reach them. */
    public function testTheIndirectDependenciesAreInTheCoreSet(): void
    {
        $this->assertArrayHasKey(
            'user_login_history',
            PF_CORE_TABLES,
            'continuousFailedLogins() reads it before the password is checked.'
        );
        $this->assertArrayHasKey(
            'user_facility_map',
            PF_CORE_TABLES,
            'getUserFacilityMap() reads it on every successful login.'
        );
        $this->assertArrayHasKey(
            's_app_menu',
            PF_CORE_TABLES,
            'AppMenuService::getMenu() reads it from header.php, which every page includes.'
        );
    }

    /** header.php really is on every page, which is what puts s_app_menu here. */
    public function testEveryPageGoesThroughTheHeader(): void
    {
        $repo = dirname(__DIR__, 3);

        $this->assertStringContainsString(
            'AppMenuService',
            (string) file_get_contents($repo . '/app/header.php'),
            'The header stopped drawing the menu, so s_app_menu is no longer unavoidable.'
        );

        $pages = ['/app/dashboard/index.php', '/app/users/users.php', '/app/vl/requests/vl-requests.php'];
        foreach ($pages as $page) {
            if (!is_file($repo . $page)) {
                continue;
            }
            $this->assertStringContainsString(
                'header.php',
                (string) file_get_contents($repo . $page),
                "{$page} no longer includes header.php; the claim that every page does has weakened."
            );
        }
    }

    /** @return list<array{0: string, 1: string}> the service methods those entry points call */
    private static function reachableMethods(string $entry): array
    {
        $reachable = [
            'continuousFailedLogins' => '/app/classes/Services/UsersService.php',
            'recordLoginAttempt'     => '/app/classes/Services/UsersService.php',
            'getAllPrivileges'       => '/app/classes/Services/UsersService.php',
            'getUserFacilityMap'     => '/app/classes/Services/FacilitiesService.php',
            'activityLog'            => '/app/classes/Services/CommonService.php',
            'getSystemConfig'        => '/app/classes/Services/CommonService.php',
            'getGlobalConfig'        => '/app/classes/Services/CommonService.php',
            'getMenu'                => '/app/classes/Services/AppMenuService.php',
            'getInstrumentsCount'    => '/app/classes/Services/CommonService.php',
            'getNonAdminUsersCount'  => '/app/classes/Services/CommonService.php',
        ];

        $out = [];
        foreach ($reachable as $method => $file) {
            // Each one has to still be called, so a method dropped from the
            // entry points stops vouching for its table instead of silently
            // continuing to.
            if (str_contains($entry, $method . '(')) {
                $out[] = [$file, $method];
            }
        }

        return $out;
    }

    /**
     * The scalar properties this method actually reaches for, and no others.
     *
     * A service names its table once as `protected string $table = '...'` and
     * works through $this->table after that, so the method body alone never
     * mentions what it reads. Taking every property instead would keep vouching
     * for a table after the method stopped touching it -- the property would sit
     * there unused, the test would stay green, and PF_CORE_TABLES would go on
     * failing installations over a dependency that no longer exists.
     */
    private static function scalarProperties(string $file, string $methodBody): string
    {
        $source = (string) file_get_contents($file);

        preg_match_all('/\$this->([a-zA-Z_][a-zA-Z0-9_]*)\b(?!\()/', $methodBody, $used);
        $wanted = array_unique($used[1]);
        if ($wanted === []) {
            return '';
        }

        preg_match_all(
            '/^\s*(?:protected|private|public)\s+[^;(){}]*\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*\'[^\']*\'\s*;$/m',
            $source,
            $m,
            PREG_SET_ORDER
        );

        $out = [];
        foreach ($m as $property) {
            if (in_array($property[1], $wanted, true)) {
                $out[] = $property[0];
            }
        }

        return implode("\n", $out);
    }

    /** The body of one method, from its signature to the brace that closes it. */
    private static function methodBody(string $file, string $method): string
    {
        $source = (string) file_get_contents($file);
        $at     = strpos($source, ' function ' . $method . '(');
        if ($at === false) {
            return '';
        }

        $open  = strpos($source, '{', $at);
        $depth = 0;
        for ($i = (int) $open, $n = strlen($source); $i < $n; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $at, $i - $at + 1);
                }
            }
        }

        return '';
    }
}

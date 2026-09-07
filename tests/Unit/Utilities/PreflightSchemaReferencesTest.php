<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

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
 * A child table carries a foreign key to the table it hangs off, so init.sql
 * already records which table would have to be in use for its absence to
 * matter. Reading those is what turns the assertion into a question the check
 * can answer, which is what these tests cover.
 *
 * bin/preflight.php runs its own body on include -- it is a script, and
 * including it would connect to a database and print a report -- so the two
 * parsers are lifted out with the tokenizer. What runs below is the shipped
 * code rather than a copy of it.
 */
final class PreflightSchemaReferencesTest extends TestCase
{
    private const WANTED = [
        'pf_parse_init_references',
        'pf_parse_init_schema',
        'pf_parse_init_seeded',
        'pf_classify_missing_tables',
    ];

    public static function setUpBeforeClass(): void
    {
        if (function_exists('pf_parse_init_references')) {
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

        // The constant as well as the functions: a test that restates the core
        // set in its own words cannot notice an entry being removed from the
        // real one.
        if (preg_match('/^const PF_CORE_TABLES = \[.*?\];$/ms', $source, $const) === 1) {
            $out .= "\n" . $const[0] . "\n";
        }

        $tmp = sys_get_temp_dir() . '/intelis-preflight-fns-' . getmypid() . '.php';
        file_put_contents($tmp, $out);
        require $tmp;
        unlink($tmp);
    }

    private function parse(string $sql): array
    {
        $tmp = sys_get_temp_dir() . '/intelis-init-fixture-' . getmypid() . '.sql';
        file_put_contents($tmp, $sql);
        try {
            return pf_parse_init_references($tmp);
        } finally {
            unlink($tmp);
        }
    }

    /**
     * The real seed, not a fixture.
     *
     * The whole mechanism rests on init.sql actually declaring these foreign
     * keys in a shape the parser reads. A fixture would keep passing after the
     * seed was regenerated in some other style, and the check would go back to
     * calling every missing table harmless with nothing to say so.
     */
    public function testTheSeedYieldsTheParentOfTheTableThisWasBuiltFor(): void
    {
        $references = pf_parse_init_references(dirname(__DIR__, 3) . '/sql/init.sql');

        $this->assertSame(
            ['form_covid19'],
            $references['covid19_tests'] ?? null,
            'covid19_tests hangs off form_covid19; without that, DRC\'s missing table reads as harmless.'
        );
        $this->assertSame(['form_generic'], $references['generic_test_results'] ?? null);
        $this->assertGreaterThan(
            10,
            count($references),
            'Almost no table resolving to a parent means the seed changed shape and this check went quiet.'
        );
    }

    /** A table is not its own evidence of use. */
    public function testASelfReferenceIsNotRecorded(): void
    {
        $out = $this->parse(
            "CREATE TABLE `tree` (\n"
            . "  `id` int NOT NULL,\n"
            . "  `parent_id` int DEFAULT NULL,\n"
            . "  CONSTRAINT `tree_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `tree` (`id`)\n"
            . ") ENGINE=InnoDB;\n"
        );

        $this->assertArrayNotHasKey(
            'tree',
            $out,
            'A self-reference says nothing about whether the table was used, and it is gone with the table anyway.'
        );
    }

    /** Two keys onto the same parent are one parent. */
    public function testRepeatedParentsCollapse(): void
    {
        $out = $this->parse(
            "CREATE TABLE `child` (\n"
            . "  `a` int NOT NULL,\n"
            . "  `b` int NOT NULL,\n"
            . "  CONSTRAINT `c1` FOREIGN KEY (`a`) REFERENCES `parent` (`id`),\n"
            . "  CONSTRAINT `c2` FOREIGN KEY (`b`) REFERENCES `parent` (`id`)\n"
            . ") ENGINE=InnoDB;\n"
        );

        $this->assertSame(['parent'], $out['child'] ?? null);
    }

    /**
     * A REFERENCES outside a CREATE TABLE is not attributed to the last one.
     *
     * The parser is a line reader, so the closing paren is the only thing that
     * ends a table. If it ever stopped honouring that, an ALTER further down
     * the file would attach its parent to whichever table was read last, and
     * the check would judge one table's absence by another table's rows.
     */
    public function testAReferenceAfterTheTableClosesIsIgnored(): void
    {
        $out = $this->parse(
            "CREATE TABLE `child` (\n"
            . "  `a` int NOT NULL\n"
            . ") ENGINE=InnoDB;\n"
            . "ALTER TABLE `elsewhere` ADD FOREIGN KEY (`a`) REFERENCES `parent` (`id`);\n"
        );

        $this->assertSame([], $out, 'Nothing inside a CREATE TABLE referenced anything.');
    }

    /**
     * The severity decision itself, which is what the check is for.
     *
     * The parsing tests above would all stay green if this classification were
     * deleted or inverted, and an absent covid19_tests next to a populated
     * form_covid19 would go back to passing as a warning. So the invariant is
     * asserted here directly: evidence of use is a failure, and the absence of
     * evidence is not.
     */
    public function testAPopulatedParentMakesTheAbsenceAFailure(): void
    {
        [$inUse, $dormant] = pf_classify_missing_tables(
            ['covid19_tests', 'qc_covid19_tests'],
            ['covid19_tests' => ['form_covid19'], 'qc_covid19_tests' => ['qc_covid19']],
            [],
            [],
            static fn(string $t): ?bool => $t === 'form_covid19',
        );

        $this->assertSame(['covid19_tests' => ['form_covid19']], $inUse);
        $this->assertSame(['qc_covid19_tests'], $dormant, 'An empty parent is no evidence of use.');
    }

    /**
     * Rows the installer put there are not evidence.
     *
     * form_vl references r_sample_status, which sql/init.sql fills with sample
     * statuses on every install. Without this exclusion a COVID-only country
     * deployment carrying no form_vl at all would be told it HAS used VL, on
     * the strength of rows it never wrote -- the check painting red an install
     * that is working exactly as intended.
     */
    public function testSeedDataIsNotEvidenceOfUse(): void
    {
        [$inUse, $dormant] = pf_classify_missing_tables(
            ['form_vl'],
            ['form_vl' => ['r_sample_status']],
            ['r_sample_status' => true],
            [],
            static fn(string $t): ?bool => true,
        );

        $this->assertSame([], $inUse, 'Seed rows must never escalate an absent table to a failure.');
        $this->assertSame(['form_vl'], $dormant);
    }

    /** One real parent among seeded ones is still enough. */
    public function testAnUnseededPopulatedParentSurvivesAmongSeededOnes(): void
    {
        [$inUse] = pf_classify_missing_tables(
            ['child'],
            ['child' => ['r_seeded', 'form_real']],
            ['r_seeded' => true],
            [],
            static fn(string $t): ?bool => true,
        );

        $this->assertSame(
            ['child' => ['form_real']],
            $inUse,
            'Only the parent that proves use should be named as the reason.'
        );
    }

    /** A table with no foreign key cannot be judged, so it is not. */
    public function testATableWithNoParentStaysAWarning(): void
    {
        [$inUse, $dormant] = pf_classify_missing_tables(
            ['orphan'],
            [],
            [],
            [],
            static fn(string $t): ?bool => true,
        );

        $this->assertSame([], $inUse);
        $this->assertSame(['orphan'], $dormant);
    }

    /** The seed's own reference tables are recognised as seeded. */
    public function testTheSeedNamesItsOwnReferenceTables(): void
    {
        $seeded = pf_parse_init_seeded(dirname(__DIR__, 3) . '/sql/init.sql');

        $this->assertArrayHasKey('r_sample_status', $seeded, 'This is the row set that caused the exclusion.');
        $this->assertArrayNotHasKey('form_covid19', $seeded, 'A request table is never seeded, so its rows are real.');
        $this->assertArrayNotHasKey('form_generic', $seeded);
    }

    /**
     * A table the application cannot run without is never filed as dormant.
     *
     * user_details is the case: its only foreign key points at roles, which the
     * seed fills, so every parent is excluded as evidence and nothing is left
     * to judge it by -- on an instance where nobody can log in.
     */
    public function testACoreTableIsUsedWhateverItsParentsSay(): void
    {
        [$inUse, $dormant] = pf_classify_missing_tables(
            ['user_details'],
            ['user_details' => ['roles']],
            ['roles' => true],
            ['user_details' => true],
            static fn(string $t): ?bool => false,
        );

        $this->assertArrayHasKey('user_details', $inUse, 'A missing login table cannot be a note.');
        $this->assertSame([], $dormant);
    }

    /**
     * An unreadable parent is reported, not read as an empty one.
     *
     * A probe that threw says less than one that returned no rows, so treating
     * the two alike would let a corrupt or permission-denied parent pass as
     * evidence that the module was never used.
     */
    public function testAParentThatCouldNotBeReadIsEvidenceInItself(): void
    {
        [$inUse, $dormant] = pf_classify_missing_tables(
            ['child'],
            ['child' => ['parent']],
            [],
            [],
            static fn(string $t): ?bool => null,
        );

        $this->assertSame(['child' => ['parent could not be read']], $inUse);
        $this->assertSame([], $dormant);
    }

    /**
     * The real constant, and every table in it still on the login path.
     *
     * Read from bin/preflight.php rather than restated here, because a second
     * copy of the list would keep passing while an entry was removed from the
     * one that runs -- and the removed entry is a login dependency downgraded
     * to a note.
     *
     * The path is followed one call out of loginProcess.php, which is where the
     * dangerous half lives: user_login_history is queried by
     * continuousFailedLogins() before the password is checked and is not named
     * in the login file at all.
     */
    public function testEveryCoreTableIsStillOnTheLoginPath(): void
    {
        $this->assertTrue(
            defined('PF_CORE_TABLES'),
            'The constant was not lifted; the assertions below prove nothing.'
        );
        $this->assertNotEmpty(PF_CORE_TABLES);

        $repo = dirname(__DIR__, 3);
        $path = implode("\n", array_map(
            static fn(string $file): string => (string) file_get_contents($repo . $file),
            [
                '/app/login/loginProcess.php',
                '/app/classes/Services/UsersService.php',
                '/app/classes/Services/CommonService.php',
            ],
        ));

        foreach (array_keys(PF_CORE_TABLES) as $table) {
            $this->assertStringContainsString(
                (string) $table,
                $path,
                "PF_CORE_TABLES claims logging in reads {$table}, and nothing on that path mentions it any more."
            );
        }
    }

    /** The one that is invisible from the login file itself. */
    public function testTheIndirectLoginDependencyIsInTheCoreSet(): void
    {
        $this->assertArrayHasKey(
            'user_login_history',
            PF_CORE_TABLES,
            'continuousFailedLogins() reads it before the password is checked; its absence throws on every attempt.'
        );
        $this->assertArrayHasKey('user_details', PF_CORE_TABLES);
    }
}

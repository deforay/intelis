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
    private const WANTED = ['pf_parse_init_references', 'pf_parse_init_schema'];

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
}

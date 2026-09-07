<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;

/**
 * What bin/duplicate-indexes.php is willing to call a copy.
 *
 * The tool drops indexes and there is no undo, so the cost of the two mistakes
 * is nothing like symmetric: leaving a duplicate in place wastes some write
 * throughput, while dropping an index that was not a duplicate can take a query
 * from slow to impossible. These tests are almost entirely about the second.
 *
 * The signature originally compared columns, prefix lengths and uniqueness, and
 * that let three pairs of genuinely different indexes look identical:
 *
 *   - BTREE and FULLTEXT over one column. MATCH ... AGAINST needs the FULLTEXT
 *     one and errors outright without it.
 *   - (a) and (a DESC), which are different indexes on MySQL 8.
 *   - two functional indexes, which both report COLUMN_NAME as null and so both
 *     reduced to an empty column list.
 *
 * All three were reproduced against a live MySQL 8.4 before this was written.
 *
 * bin/duplicate-indexes.php runs its own body on include, so the functions are
 * lifted out with the tokenizer rather than copied here. What runs below is the
 * shipped code, byte for byte.
 */
final class DuplicateIndexSignatureTest extends TestCase
{
    private const WANTED = ['index_part_signature', 'index_signature'];

    public static function setUpBeforeClass(): void
    {
        if (function_exists('index_part_signature')) {
            return;
        }

        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/bin/duplicate-indexes.php');
        $tokens = token_get_all($source);

        $out = "<?php\n";
        for ($i = 0, $n = count($tokens); $i < $n; $i++) {
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

            $depth = 0;
            $started = false;
            $body = '';
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

        $tmp = sys_get_temp_dir() . '/intelis-dupidx-fns-' . getmypid() . '.php';
        file_put_contents($tmp, $out);
        require $tmp;
        unlink($tmp);
    }

    protected function setUp(): void
    {
        $this->assertTrue(
            function_exists('index_part_signature') && function_exists('index_signature'),
            'The tool no longer defines the functions this test lifts.'
        );
    }

    /** One key part as information_schema.STATISTICS reports it. */
    private static function part(
        ?string $column,
        ?int $subPart = null,
        ?string $collation = 'A',
        ?string $expression = null
    ): array {
        return [
            'COLUMN_NAME' => $column,
            'SUB_PART'    => $subPart,
            'COLLATION'   => $collation,
            'EXPRESSION'  => $expression,
        ];
    }

    /** A whole index, the way the grouping key is built. */
    private static function signature(array $parts, bool $unique = false, string $type = 'BTREE'): ?string
    {
        return index_signature(
            array_map(static fn(array $p): ?string => index_part_signature($p), $parts),
            $unique,
            $type
        );
    }

    /**
     * FULLTEXT is not a copy of BTREE.
     *
     * This is the one that turns a slow query into a broken one: only the
     * FULLTEXT index can answer MATCH ... AGAINST, and dropping it makes the
     * query an error rather than a table scan.
     */
    public function testFulltextIsNotACopyOfBtree(): void
    {
        $this->assertNotSame(
            self::signature([self::part('title')], false, 'BTREE'),
            self::signature([self::part('title', null, null)], false, 'FULLTEXT'),
            'A FULLTEXT index answers queries a BTREE over the same column cannot.'
        );
    }

    /** A descending index is not a copy of an ascending one. */
    public function testDescendingIsNotACopyOfAscending(): void
    {
        $this->assertNotSame(
            self::signature([self::part('name', null, 'A')]),
            self::signature([self::part('name', null, 'D')]),
            '(name DESC) exists to serve an ORDER BY that (name) cannot.'
        );
    }

    /**
     * Two different functional indexes are not copies of each other.
     *
     * Both report COLUMN_NAME as null, so a signature built from column names
     * alone reduced each of them to the empty string and matched them.
     */
    public function testDifferentExpressionsAreNotCopies(): void
    {
        $year  = self::signature([self::part(null, null, 'A', 'year(`d`)')]);
        $month = self::signature([self::part(null, null, 'A', 'month(`d`)')]);

        $this->assertNotSame($year, $month, 'year(d) and month(d) index different values.');
        $this->assertNotSame('', $year, 'A functional index must not reduce to an empty definition.');
    }

    /** The same expression twice is a genuine duplicate and must still be caught. */
    public function testTheSameExpressionTwiceIsACopy(): void
    {
        $this->assertSame(
            self::signature([self::part(null, null, 'A', 'year(`d`)')]),
            self::signature([self::part(null, null, 'A', 'year(`d`)')])
        );
    }

    /**
     * An index whose definition cannot be read is never compared.
     *
     * On MySQL before 8.0.13, and on MariaDB, STATISTICS has no EXPRESSION
     * column. Such a server has no functional indexes to confuse, but if a null
     * ever arrives the answer must be "unknown", not "matches everything else
     * that is also unknown".
     */
    public function testAnUnreadableDefinitionYieldsNoSignature(): void
    {
        $this->assertNull(
            index_part_signature(self::part(null, null, 'A', null)),
            'A functional key part with no expression is unreadable.'
        );
        $this->assertNull(
            self::signature([self::part('a'), self::part(null, null, 'A', null)]),
            'One unreadable part makes the whole index incomparable.'
        );
    }

    /** The distinctions the tool already drew must survive the added ones. */
    public function testPreviouslyHeldDistinctionsStillHold(): void
    {
        $this->assertNotSame(
            self::signature([self::part('a')], false),
            self::signature([self::part('a')], true),
            'A UNIQUE index carries a constraint a plain one does not.'
        );
        $this->assertNotSame(
            self::signature([self::part('a'), self::part('b')]),
            self::signature([self::part('b'), self::part('a')]),
            'Column order decides which queries an index can serve.'
        );
        $this->assertNotSame(
            self::signature([self::part('note')]),
            self::signature([self::part('note', 10)]),
            'A prefix index covers less than the whole column.'
        );
        $this->assertNotSame(
            self::signature([self::part('a')]),
            self::signature([self::part('a'), self::part('b')]),
            '(a, b) is not a copy of (a).'
        );
    }

    /** And a real duplicate is still a duplicate. */
    public function testAnActualCopyIsStillRecognised(): void
    {
        $this->assertSame(
            self::signature([self::part('a'), self::part('b')]),
            self::signature([self::part('a'), self::part('b')]),
            'Two plain BTREE indexes over (a, b) are the case this tool exists for.'
        );
    }
}

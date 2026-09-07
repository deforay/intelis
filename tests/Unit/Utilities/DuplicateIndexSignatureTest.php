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
    private const WANTED = ['index_part_signature', 'index_signature', 'group_indexes'];

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

    /**
     * A full row of information_schema.STATISTICS, as group_indexes() reads it.
     */
    private static function row(
        string $index,
        int $seq,
        ?string $column,
        array $overrides = []
    ): array {
        return $overrides + [
            'TABLE_NAME'   => 'probe',
            'INDEX_NAME'   => $index,
            'NON_UNIQUE'   => 1,
            'SEQ_IN_INDEX' => $seq,
            'COLUMN_NAME'  => $column,
            'SUB_PART'     => null,
            'COLLATION'    => 'A',
            'INDEX_TYPE'   => 'BTREE',
            'EXPRESSION'   => null,
        ];
    }

    /** The index names the tool would report as copies of something else. */
    private static function copiesFoundIn(array $rows): array
    {
        $unreadable = [];
        $found = [];
        foreach (group_indexes($rows, $unreadable) as $group) {
            if (count($group) > 1) {
                foreach ($group as $index) {
                    $found[] = $index['name'];
                }
            }
        }
        sort($found);
        return $found;
    }

    /**
     * The grouping the script acts on, not a reimplementation of it.
     *
     * The functions above can each be right while the caller keys its groups on
     * something looser, and the outcome is still a dropped index. This drives
     * `group_indexes()`, which is what bin/duplicate-indexes.php calls, with the
     * three pairs that used to collide -- and one pair that genuinely is a
     * duplicate, so the test cannot pass by the tool simply finding nothing.
     */
    public function testTheGroupingUsedByTheToolSeparatesDistinctIndexes(): void
    {
        $rows = [
            // BTREE and FULLTEXT over one column.
            self::row('title_btree', 1, 'title'),
            self::row('title_ft', 1, 'title', ['INDEX_TYPE' => 'FULLTEXT', 'COLLATION' => null]),
            // Ascending and descending over one column.
            self::row('name_asc', 1, 'name'),
            self::row('name_desc', 1, 'name', ['COLLATION' => 'D']),
            // Two different expressions.
            self::row('d_year', 1, null, ['EXPRESSION' => 'year(`d`)']),
            self::row('d_month', 1, null, ['EXPRESSION' => 'month(`d`)']),
            // A pair that really is redundant, so a tool that found nothing at
            // all would fail this test rather than pass it.
            self::row('dup_a', 1, 'a'),
            self::row('dup_b', 1, 'a'),
        ];

        $this->assertSame(
            ['dup_a', 'dup_b'],
            self::copiesFoundIn($rows),
            'Only the genuine pair may be grouped together.'
        );
    }

    /** Composites are grouped on the whole key, in order. */
    public function testTheGroupingComparesEveryKeyPartInOrder(): void
    {
        $rows = [
            self::row('ab1', 1, 'a'), self::row('ab1', 2, 'b'),
            self::row('ab2', 1, 'a'), self::row('ab2', 2, 'b'),
            self::row('ba', 1, 'b'),  self::row('ba', 2, 'a'),
            self::row('a_only', 1, 'a'),
            // Same columns, but the second is ordered the other way.
            self::row('ab_desc', 1, 'a'), self::row('ab_desc', 2, 'b', ['COLLATION' => 'D']),
        ];

        $this->assertSame(
            ['ab1', 'ab2'],
            self::copiesFoundIn($rows),
            '(b, a), (a) and (a, b DESC) are each a different index from (a, b).'
        );
    }

    /**
     * Two tables can hold the same index definition without either being a copy.
     *
     * Nothing is more ordinary than two tables both indexing `last_modified_datetime`,
     * and an index is only ever redundant against another on its own table.
     * DROP INDEX names a table, so a grouping that lost track of which one would
     * aim the drop at whichever table sorted first.
     */
    public function testIndexesOnDifferentTablesAreNeverCopiesOfEachOther(): void
    {
        $onFormVl = self::row('idx_lmd', 1, 'last_modified_datetime', ['TABLE_NAME' => 'form_vl']);
        $onFormEid = self::row('idx_lmd', 1, 'last_modified_datetime', ['TABLE_NAME' => 'form_eid']);

        $this->assertSame(
            [],
            self::copiesFoundIn([$onFormVl, $onFormEid]),
            'Identical definitions on two tables are two indexes, not one and a copy.'
        );

        $unreadable = [];
        $this->assertCount(
            2,
            group_indexes([$onFormVl, $onFormEid], $unreadable),
            'They must land in separate groups, one per table.'
        );
    }

    /** An index the server cannot fully describe is grouped with nothing. */
    public function testTheGroupingSetsAsideWhatItCannotRead(): void
    {
        $rows = [
            self::row('expr1', 1, null),
            self::row('expr2', 1, null),
            self::row('plain1', 1, 'a'),
            self::row('plain2', 1, 'a'),
        ];

        $unreadable = [];
        $groups = group_indexes($rows, $unreadable);

        $this->assertSame(
            ['probe' . "\0" . 'expr1', 'probe' . "\0" . 'expr2'],
            $unreadable,
            'Both functional indexes are unreadable without EXPRESSION and must be reported, not compared.'
        );
        $this->assertSame(
            ['plain1', 'plain2'],
            self::copiesFoundIn($rows),
            'and the readable duplicate alongside them is still found.'
        );
        $this->assertCount(1, $groups, 'The unreadable pair forms no group.');
    }
}

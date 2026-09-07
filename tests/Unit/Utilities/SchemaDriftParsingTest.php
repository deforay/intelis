<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;

/**
 * How the drift check reads DDL.
 *
 * Two failures here are silent by nature. A statement split in the wrong place
 * is not reported as unreadable -- the fragments parse as something, and the
 * check simply stops covering whatever was in the rest of the statement. And an
 * index declaration it cannot read becomes an index it never expected, so a
 * database missing one looks correct.
 *
 * Both were live. sql/init.sql has a column comment containing a semicolon, on
 * instrument_activity_log.installation_id, and splitting on semicolons blindly
 * cut that CREATE TABLE in half: its columns were read from the first fragment
 * and its six index declarations, which sit below the comment, were never seen.
 * Across the file that cost 27 columns and 11 indexes of coverage.
 *
 * bin/build/check-schema-drift.php runs its own body on include, so the
 * functions are lifted out with the tokenizer. What runs below is the shipped
 * code rather than a copy of it.
 */
final class SchemaDriftParsingTest extends TestCase
{
    private const WANTED = [
        'statements',
        'splitTopLevel',
        'ident',
        'parseIndexDeclaration',
        'balancedParenContent',
        'splitIndexedColumn',
        'renameIndexedColumn',
        'dropIndexedColumn',
        'applyStatement',
        'indexDefinition',
    ];

    public static function setUpBeforeClass(): void
    {
        if (function_exists('parseIndexDeclaration')) {
            return;
        }

        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/bin/build/check-schema-drift.php');
        $tokens = token_get_all($source);
        // The lifted functions are written without their file's imports, so the
        // ones they reach for have to come along.
        $out = "<?php\nuse PhpMyAdmin\\SqlParser\\Parser;\n"
            . "const NOT_A_COLUMN = ['primary', 'key', 'unique', 'index', 'constraint',"
            . " 'fulltext', 'spatial', 'foreign', 'check'];\n";
        $n = count($tokens);

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

        $tmp = sys_get_temp_dir() . '/intelis-drift-fns-' . getmypid() . '.php';
        file_put_contents($tmp, $out);
        require $tmp;
        unlink($tmp);
    }

    /** A semicolon inside a string does not end the statement. */
    public function testASemicolonInsideACommentDoesNotSplitTheStatement(): void
    {
        $sql = "CREATE TABLE `t` (\n"
             . "  `a` int NOT NULL,\n"
             . "  `b` char(36) DEFAULT NULL COMMENT 'one thing; and another',\n"
             . "  PRIMARY KEY (`a`),\n"
             . "  KEY `idx_b` (`b`)\n"
             . ") ENGINE=InnoDB;\n"
             . "INSERT INTO `t` VALUES (1);";

        $out = statements($sql);

        $this->assertCount(2, $out, 'The comment semicolon must not end the CREATE TABLE.');
        $this->assertStringContainsString('KEY `idx_b`', $out[0], 'The index declarations must survive the split.');
        $this->assertStringStartsWith('INSERT', $out[1]);
    }

    /** Escaped and doubled quotes inside strings are not mistaken for the end of one. */
    public function testQuotingEdgesInsideStrings(): void
    {
        $this->assertCount(2, statements("SELECT 'it''s fine; really'; SELECT 2"));
        $this->assertCount(2, statements("SELECT 'back\\\\slash; here'; SELECT 2"));
        $this->assertCount(2, statements('SELECT "double; quoted"; SELECT 2'));
    }

    /** Index declarations are read as definitions, with uniqueness and prefixes. */
    public function testIndexDeclarationsAreRead(): void
    {
        $this->assertSame(['PRIMARY', ['a'], true, true], parseIndexDeclaration('PRIMARY KEY (`a`)'));
        $this->assertSame(['u', ['a', 'b'], true, false], parseIndexDeclaration('UNIQUE KEY `u` (`a`,`b`)'));
        $this->assertSame(['k', ['a'], false, false], parseIndexDeclaration('KEY `k` (`a`)'));
        $this->assertSame([null, ['a'], false, false], parseIndexDeclaration('INDEX (`a`)'));
    }

    /**
     * A foreign key is not an index declaration.
     *
     * MySQL creates a backing index for one on its own, under a name nothing
     * declared. Treating the constraint as a declaration would report an index
     * the history never asked for on every install that has one.
     */
    public function testForeignKeysAndChecksAreNotIndexes(): void
    {
        $this->assertNull(parseIndexDeclaration('FOREIGN KEY (`a`) REFERENCES `o` (`id`)'));
        $this->assertNull(parseIndexDeclaration('CONSTRAINT `fk` FOREIGN KEY (`a`) REFERENCES `o` (`id`)'));
        $this->assertNull(parseIndexDeclaration('`a` int NOT NULL'));
    }

    /**
     * A prefix length does not swallow the rest of the key.
     *
     * The column list used to be captured with a pattern that stopped at the
     * first `)`, and a prefix length puts one INSIDE the list. It matched, so
     * nothing backtracked and nothing complained: `(`note`(10), `category`)`
     * came back as the single column `note`, prefix and second column both
     * gone. A database holding the real index then looked like it was missing
     * one, which is a correct schema reported as drifted.
     */
    public function testAPrefixLengthDoesNotTruncateTheColumnList(): void
    {
        $this->assertSame(
            ['idx', ['note(10)', 'category'], false, false],
            parseIndexDeclaration('KEY `idx` (`note`(10), `category`)')
        );
        $this->assertSame(
            ['idx', ['note(10)'], false, false],
            parseIndexDeclaration('KEY `idx` (`note`(10))')
        );
        $this->assertSame(
            ['idx', ['a', 'note(10)', 'b'], false, false],
            parseIndexDeclaration('KEY `idx` (`a`, `note`(10), `b`)')
        );
    }

    /** A sort direction is part of the syntax, not part of the column name. */
    public function testSortDirectionIsNotReadAsPartOfTheColumn(): void
    {
        $this->assertSame(['idx', ['a', 'b'], false, false], parseIndexDeclaration('KEY `idx` (`a` ASC, `b` DESC)'));
    }

    /**
     * A key part this cannot read makes the whole declaration unreadable.
     *
     * A functional index is not something the expected set can compare, and
     * salvaging a column name out of the first few characters of an expression
     * would put a column in it that the index does not have.
     */
    public function testAnExpressionKeyPartIsNotGuessedAt(): void
    {
        $this->assertNull(parseIndexDeclaration('KEY `idx` ((year(`d`)))'));
        $this->assertNull(parseIndexDeclaration('KEY `idx` (`a`, (month(`d`)))'));
    }

    /**
     * A column name this cannot read in full is not salvaged down to its first word.
     *
     * A quoted identifier may hold characters the pattern does not accept. Read
     * without an end anchor it matches the leading run of ordinary characters
     * and reports `my` for a column called `my col` -- a column name that looks
     * entirely plausible and belongs to no index, so the checker reports drift
     * against a database that is correct.
     */
    public function testAPartiallyReadableColumnNameIsRejectedRatherThanTruncated(): void
    {
        $this->assertNull(
            parseIndexDeclaration('KEY `idx` (`my col`)'),
            'Better unreadable than confidently wrong.'
        );
        $this->assertNull(parseIndexDeclaration('KEY `idx` (`a`, `my col`)'));
    }

    /**
     * A primary key is not a unique index over the same column.
     *
     * Both were reduced to "a:U", so a UNIQUE KEY satisfied a declared PRIMARY
     * KEY and a primary key that had gone missing was reported clean. Tables
     * carrying both over one column are exactly where that would hide.
     */
    public function testAPrimaryKeyIsDistinguishableFromAUniqueIndex(): void
    {
        $primary = parseIndexDeclaration('PRIMARY KEY (`id`)');
        $unique  = parseIndexDeclaration('UNIQUE KEY `u` (`id`)');

        $this->assertNotNull($primary);
        $this->assertNotNull($unique);
        $this->assertTrue($primary[3], 'PRIMARY KEY is a primary key.');
        $this->assertFalse($unique[3], 'UNIQUE KEY is not.');
        // Through the checker's own definition function, not a copy of it: the
        // comparison is what decides whether an index counts as present, and a
        // test that reimplements it would stay green while that changed.
        $this->assertNotSame(
            indexDefinition(['cols' => $primary[1], 'unique' => $primary[2], 'primary' => $primary[3]]),
            indexDefinition(['cols' => $unique[1], 'unique' => $unique[2], 'primary' => $unique[3]]),
            'The two must not reduce to the same definition, which is how one hid behind the other.'
        );
        $this->assertSame(
            'id:P',
            indexDefinition(['cols' => ['id'], 'unique' => true, 'primary' => true])
        );
        $this->assertSame(
            'id:U',
            indexDefinition(['cols' => ['id'], 'unique' => true, 'primary' => false])
        );
        $this->assertSame(
            'id:N',
            indexDefinition(['cols' => ['id'], 'unique' => false, 'primary' => false])
        );
    }

    /** Balanced extraction ignores parentheses inside quotes. */
    public function testBalancedExtractionIgnoresQuotedParentheses(): void
    {
        $this->assertSame('`a`', balancedParenContent('(`a`)', 0));
        $this->assertSame("'(' , `b`", balancedParenContent("('(' , `b`)", 0));
        $this->assertSame('`a`(10), `b`', balancedParenContent('(`a`(10), `b`)', 0));
        $this->assertNull(balancedParenContent('(`a`', 0), 'Unbalanced is unreadable, not empty.');
    }

    /**
     * A named ADD INDEX reaches the index parser.
     *
     * It did not. The branch that reads ADD COLUMN matched the word INDEX where
     * a column name goes, saw it was not a column, and stopped -- so every
     * migration that added an index the ordinary way contributed nothing to the
     * expected set, and an index that failed to build under a stamped version
     * was reported as present. Only the unnamed `ADD INDEX(c)` form, which has
     * no space for that pattern to match, ever got through.
     */
    public function testAnAddIndexStatementRegistersTheIndex(): void
    {
        $schema = ['t' => ['a' => 'a', 'b' => 'b']];
        $indexes = [];

        $this->assertTrue(applyStatement('ALTER TABLE `t` ADD INDEX `idx` (`a`)', $schema, $indexes));
        $this->assertSame(
            [['name' => 'idx', 'cols' => ['a'], 'unique' => false, 'primary' => false]],
            $indexes['t'] ?? [],
            'A named ADD INDEX has to land in the expected set.'
        );

        $this->assertTrue(applyStatement('ALTER TABLE `t` ADD UNIQUE KEY `u` (`a`,`b`)', $schema, $indexes));
        $this->assertContains(
            ['name' => 'u', 'cols' => ['a', 'b'], 'unique' => true, 'primary' => false],
            $indexes['t']
        );

        $this->assertTrue(applyStatement('ALTER TABLE `t` ADD INDEX( `b`)', $schema, $indexes));
        $this->assertContains(
            ['name' => null, 'cols' => ['b'], 'unique' => false, 'primary' => false],
            $indexes['t'],
            'and the unnamed form must keep working.'
        );
    }

    /** CREATE INDEX is the other way to add one, and never touched the ALTER branch. */
    public function testCreateIndexRegistersTheIndex(): void
    {
        $schema = ['t' => ['a' => 'a']];
        $indexes = [];

        $this->assertTrue(applyStatement('CREATE INDEX `idx` ON `t` (`a`)', $schema, $indexes));
        $this->assertSame(
            [['name' => 'idx', 'cols' => ['a'], 'unique' => false, 'primary' => false]],
            $indexes['t'] ?? []
        );

        $this->assertTrue(applyStatement('CREATE UNIQUE INDEX `u` ON `t` (`a`)', $schema, $indexes));
        $this->assertContains(
            ['name' => 'u', 'cols' => ['a'], 'unique' => true, 'primary' => false],
            $indexes['t']
        );

        $this->assertTrue(applyStatement('DROP INDEX `idx` ON `t`', $schema, $indexes));
        $this->assertNotContains(
            ['name' => 'idx', 'cols' => ['a'], 'unique' => false, 'primary' => false],
            $indexes['t']
        );
    }

    /**
     * An index follows its column through a rename.
     *
     * MySQL keeps the index across CHANGE and re-points it. The replay was
     * updating the column set and leaving the index on the old name, so a
     * correctly upgraded database was reported as missing an index over a
     * column that no longer exists.
     */
    public function testAnIndexFollowsARenamedColumn(): void
    {
        $schema = ['t' => ['a' => 'a']];
        $indexes = ['t' => [['name' => 'k', 'cols' => ['a'], 'unique' => false, 'primary' => false]]];

        $this->assertTrue(applyStatement('ALTER TABLE `t` CHANGE `a` `b` INT NOT NULL', $schema, $indexes));

        $this->assertSame(['b' => 'b'], $schema['t']);
        $this->assertSame(
            [['name' => 'k', 'cols' => ['b'], 'unique' => false, 'primary' => false]],
            $indexes['t'],
            'The index has to move with the column.'
        );
    }

    /** A prefix length survives the rename with it. */
    public function testARenameKeepsThePrefixLength(): void
    {
        $this->assertSame(
            [['name' => 'k', 'cols' => ['b(10)'], 'unique' => false, 'primary' => false]],
            renameIndexedColumn(
                [['name' => 'k', 'cols' => ['a(10)'], 'unique' => false, 'primary' => false]],
                'a',
                'b'
            )
        );
    }

    /** Dropping a column takes its indexes, and only its part of a composite. */
    public function testDroppingAColumnDropsWhatIndexedIt(): void
    {
        $schema = ['t' => ['a' => 'a', 'b' => 'b']];
        $indexes = ['t' => [
            ['name' => 'only_a', 'cols' => ['a'], 'unique' => false, 'primary' => false],
            ['name' => 'a_and_b', 'cols' => ['a', 'b'], 'unique' => false, 'primary' => false],
            ['name' => 'only_b', 'cols' => ['b'], 'unique' => false, 'primary' => false],
        ]];

        $this->assertTrue(applyStatement('ALTER TABLE `t` DROP COLUMN `a`', $schema, $indexes));

        $this->assertSame(
            [
                ['name' => 'a_and_b', 'cols' => ['b'], 'unique' => false, 'primary' => false],
                ['name' => 'only_b', 'cols' => ['b'], 'unique' => false, 'primary' => false],
            ],
            $indexes['t'],
            'The index over only that column goes; the composite keeps its other column.'
        );
    }
}

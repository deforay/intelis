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
    private const WANTED = ['statements', 'splitTopLevel', 'ident', 'parseIndexDeclaration'];

    public static function setUpBeforeClass(): void
    {
        if (function_exists('parseIndexDeclaration')) {
            return;
        }

        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/bin/build/check-schema-drift.php');
        $tokens = token_get_all($source);
        // The lifted functions are written without their file's imports, so the
        // ones they reach for have to come along.
        $out = "<?php\nuse PhpMyAdmin\\SqlParser\\Parser;\n";
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
        $this->assertSame(['PRIMARY', ['a'], true], parseIndexDeclaration('PRIMARY KEY (`a`)'));
        $this->assertSame(['u', ['a', 'b'], true], parseIndexDeclaration('UNIQUE KEY `u` (`a`,`b`)'));
        $this->assertSame(['k', ['a'], false], parseIndexDeclaration('KEY `k` (`a`)'));
        $this->assertSame([null, ['a'], false], parseIndexDeclaration('INDEX (`a`)'));
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
}

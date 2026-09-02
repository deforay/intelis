<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseService;
use App\Services\Database\MysqliDb;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * MysqliDb runs every raw query through rawAddPrefix(), whose only real job is to
 * inject a table prefix. This application never sets one, and with an empty prefix the
 * parent's regex does nothing but wrap the token after from/into/update/join/describe
 * in backticks -- harmless for a table name, but corruption for a keyword. It turned
 * `ON UPDATE CURRENT_TIMESTAMP` into `ON UPDATE ``CURRENT_TIMESTAMP```, a syntax error
 * that only ever appeared through this class, which is how a valid-looking migration
 * silently failed to create its table.
 *
 * The override is a pure string method, so it is exercised here without a database.
 */
final class DatabaseServiceRawAddPrefixTest extends TestCase
{
    private DatabaseService $db;
    private string $originalPrefix;

    protected function setUp(): void
    {
        // No constructor: it would open a connection. rawAddPrefix needs none.
        $this->db = (new ReflectionClass(DatabaseService::class))->newInstanceWithoutConstructor();
        $this->originalPrefix = MysqliDb::$prefix;
        MysqliDb::$prefix = '';
    }

    protected function tearDown(): void
    {
        MysqliDb::$prefix = $this->originalPrefix;
    }

    public function testOnUpdateCurrentTimestampSurvivesWithNoPrefix(): void
    {
        $ddl = 'CREATE TABLE t (updated_at datetime NOT NULL '
            . 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)';

        $out = $this->db->rawAddPrefix($ddl);

        self::assertStringNotContainsString('`CURRENT_TIMESTAMP`', $out);
        self::assertStringContainsString('ON UPDATE CURRENT_TIMESTAMP', $out);
    }

    public function testPlainTableReferenceIsLeftExecutable(): void
    {
        // Not backticking a plain name is fine -- MySQL accepts it -- and no table in
        // the schema is a reserved word that would need the quoting.
        $out = $this->db->rawAddPrefix('SELECT * FROM users WHERE id = 1');

        self::assertSame('SELECT * FROM users WHERE id = 1', $out);
    }

    public function testAlreadyBacktickedReferenceIsPreserved(): void
    {
        $out = $this->db->rawAddPrefix('SELECT * FROM `orders` o');

        self::assertSame('SELECT * FROM `orders` o', $out);
    }

    public function testQueryIsLeftVerbatimWithNoPrefix(): void
    {
        // The parent's whitespace collapsing existed only to flatten the query for its
        // single-line prefix regex. With no prefix there is no regex to feed, and MySQL
        // parses the query as written.
        $out = $this->db->rawAddPrefix('SELECT a,   b   FROM t');

        self::assertSame('SELECT a,   b   FROM t', $out);
    }

    public function testLineCommentDoesNotSwallowTheRestOfTheQuery(): void
    {
        // Flattening a multi-line query puts every later line behind a `--` comment, so
        // MySQL received the VL fetch-results SELECT truncated at the comment and failed
        // every call with a syntax error. Newlines have to survive.
        $query = "SELECT a,\n-- why b is what it is\nb\nFROM t";

        $out = $this->db->rawAddPrefix($query);

        self::assertStringContainsString("\n", $out);
        self::assertStringContainsString('FROM t', explode('-- why b is what it is', $out)[1] ?? '');
    }

    public function testConfiguredPrefixStillInjects(): void
    {
        // A prefixed install must behave exactly as the parent did.
        MysqliDb::$prefix = 'pfx_';

        $out = $this->db->rawAddPrefix('SELECT * FROM users');

        self::assertStringContainsString('`pfx_users`', $out);
    }
}

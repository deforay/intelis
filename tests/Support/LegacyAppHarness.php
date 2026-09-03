<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;
use DI\ContainerBuilder;
use mysqli;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;

use function DI\factory;

/**
 * Enough of the application to run one of the procedural pages under app/.
 *
 * Those files are not functions and cannot be called. They read services out of
 * ContainerRegistry, the request out of AppRegistry, and superglobals directly, then
 * finish with a redirect. LegacyRequestHandler already knows how to run one -- it
 * requires the file inside a closure with output buffered -- so what is missing for a
 * test is only the things a served request would have set up around it.
 *
 * The schema comes out of sql/init.sql rather than being written out here, so a test
 * runs against the columns an install actually has and starts failing if they move.
 */
final class LegacyAppHarness
{
    private static ?DatabaseService $db = null;

    private static string $database = '';

    /**
     * @param list<string> $tables tables to create, by name, from sql/init.sql
     * @param array<string, mixed> $config extra applicationConfig entries, for example
     *                                     the modules a page switches on
     */
    public static function boot(string $database, array $tables, array $config = []): DatabaseService
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');

        if ($host === false || $host === '' || $user === false || $user === '') {
            throw new RuntimeException('No test database configured.');
        }

        $port = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query("DROP DATABASE IF EXISTS `$database`");
        $bootstrap->query("CREATE DATABASE `$database`");
        $bootstrap->select_db($database);

        foreach ($tables as $table) {
            $bootstrap->query(self::schemaFor($table));
            if ($bootstrap->error !== '') {
                throw new RuntimeException("Could not create $table: " . $bootstrap->error);
            }
        }
        $bootstrap->close();

        self::$database = $database;
        self::$db = new DatabaseService([
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => $database,
            'port' => $port,
        ]);

        // Autowiring builds the real services, so a page under test drives the real
        // CommonService rather than a stand-in that agrees with it by construction.
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->addDefinitions([
            DatabaseService::class => factory(static fn(): DatabaseService => self::$db),
            'applicationConfig' => $config + ['database' => ['db' => $database]],
        ]);
        ContainerRegistry::setContainer($builder->build());

        return self::$db;
    }

    public static function db(): DatabaseService
    {
        if (!self::$db instanceof DatabaseService) {
            throw new RuntimeException('Harness has not been booted.');
        }
        return self::$db;
    }

    /**
     * The session a served request would have had. Set before driving a page; the
     * pages read $_SESSION directly and several write to it.
     *
     * @param array<string, mixed> $session
     */
    public static function withSession(array $session = []): void
    {
        $_SESSION = $session + [
            'userId' => 1,
            'userName' => 'Test User',
            'roleCode' => 'system-admin',
            'accessType' => 'testing-lab',
        ];
    }

    /**
     * Several pages read $_POST directly rather than the parsed body, so both carry
     * the same data. The request itself needs no registering here: the handler puts
     * it in AppRegistry before requiring the page, which is where the pages look.
     *
     * @param array<string, mixed> $post
     */
    public static function withPost(array $post, string $path = '/'): ServerRequestInterface
    {
        $_POST = $post;
        $_REQUEST = $post;

        return (new ServerRequestFactory())
            ->createServerRequest('POST', $path)
            ->withParsedBody($post)
            ->withHeader('User-Agent', 'intelis-tests')
            ->withHeader('X-Forwarded-For', '127.0.0.1');
    }

    public static function shutdown(): void
    {
        if (self::$database === '' || !self::$db instanceof DatabaseService) {
            return;
        }
        self::$db->rawQuery('DROP DATABASE `' . self::$database . '`');
        self::$db = null;
        self::$database = '';
    }

    /** Pull one CREATE TABLE out of sql/init.sql verbatim. */
    private static function schemaFor(string $table): string
    {
        $initSql = file_get_contents(dirname(__DIR__, 2) . '/sql/init.sql');
        if ($initSql === false) {
            throw new RuntimeException('Could not read sql/init.sql');
        }

        $pattern = '/^CREATE TABLE `' . preg_quote($table, '/') . '` \(.*?^\) ENGINE=[^;]*;/ms';
        if (preg_match($pattern, $initSql, $matches) !== 1) {
            throw new RuntimeException("sql/init.sql has no CREATE TABLE for $table");
        }

        return $matches[0];
    }
}

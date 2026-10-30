<?php

declare(strict_types=1);

namespace Tests\Support;

// The MySQL login the integration tests use, saved by `composer setup-tests` in
// configs/test-db.php (gitignored, readable only by its owner). The tests read
// INTELIS_TEST_DB_* from the environment, so load() copies the saved login
// there. A variable already set in the environment wins over the file, which
// keeps CI and one-off overrides working unchanged.
//
// bin/build/qc.php requires this file before vendor/ is checked, so it must not
// depend on the autoloader.
final class TestDbSettings
{
    private const array VARIABLES = [
        'host' => 'INTELIS_TEST_DB_HOST',
        'port' => 'INTELIS_TEST_DB_PORT',
        'user' => 'INTELIS_TEST_DB_USER',
        'pass' => 'INTELIS_TEST_DB_PASS',
    ];

    public static function path(): string
    {
        return dirname(__DIR__, 2) . '/configs/test-db.php';
    }

    public static function saved(): bool
    {
        return is_file(self::path());
    }

    /**
     * @param bool $replace replace what an earlier load() put in the environment,
     *                      after setup saved a new login
     */
    public static function load(bool $replace = false): void
    {
        if (!self::saved()) {
            return;
        }
        $settings = include self::path();
        if (!is_array($settings)) {
            return;
        }
        foreach (self::VARIABLES as $key => $variable) {
            if (isset($settings[$key]) && $settings[$key] !== '' && ($replace || getenv($variable) === false)) {
                putenv("{$variable}={$settings[$key]}");
            }
        }
    }

    public static function configured(): bool
    {
        return (string) getenv('INTELIS_TEST_DB_HOST') !== '' && (string) getenv('INTELIS_TEST_DB_USER') !== '';
    }

    /**
     * Save a login, or an empty array to remember that the person chose to skip
     * the integration tests.
     *
     * @param array{host?: string, port?: int, user?: string, pass?: string} $settings
     */
    public static function save(array $settings): void
    {
        $path = self::path();
        $previous = umask(0077);
        file_put_contents(
            $path,
            "<?php\n\n// Written by composer setup-tests. Rerun it to change this login.\nreturn "
                . var_export($settings, true) . ";\n"
        );
        umask($previous);
        chmod($path, 0600);
    }
}

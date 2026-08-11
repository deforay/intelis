#!/usr/bin/env php
<?php

/**
 * bin/preflight.php — pre-boot instance doctor.
 *
 * Answers one question: can this machine actually run and serve the app?
 *
 * It is deliberately zero-dependency — no bootstrap.php, no vendor/autoload,
 * no DI container, no DatabaseService. That is the whole point. Every other
 * diagnostic here (bin/health.php, the in-app System Health screens) boots the
 * application first, so at the exact moment an instance is broken they die with
 * the same fatal the app died with and tell you nothing. This runs on raw PHP
 * and PDO and keeps reporting when everything else has stopped.
 *
 * The division of labour, deliberately kept sharp:
 *
 *   bin/preflight.php  can it boot?     static, pre-boot, read-only, exit code
 *   bin/health.php     is it healthy?   post-boot, thresholds, latency,
 *                                       system alerts, cron
 *
 * So this script never writes an alert, never keeps state, never applies a
 * threshold and is never run from cron. Anything ongoing belongs in health.php.
 *
 * It reports; it does not repair. Every failure carries the exact command that
 * fixes it, so the support loop for a lab is "send me the output of
 * `composer preflight`" instead of decoding an Apache error log over WhatsApp.
 *
 * Checks, in order:
 *   1. PHP runtime — version and the extensions composer.json requires
 *   2. The web SAPI's php.ini, which the CLI cannot see and which is where the
 *      silent breakage lives (OPcache serving stale code, upload limits)
 *   3. vendor/ present and not stale against composer.lock
 *   4. configs/config.production.php present, parseable, and filled in
 *   5. Paths writable BY THE WEB SERVER USER, not by whoever ran this
 *   6. Database reachable, migrations current, collation clean, audit triggers
 *      installed
 *
 * Usage:
 *   composer preflight             run all checks
 *   composer preflight -- --quiet  only print warnings and failures (CI / hooks)
 *   php bin/preflight.php --help   print this docblock
 *
 * Exit codes: 0 nothing failed (warnings still exit 0), 1 one or more failed.
 */

declare(strict_types=1);

require __DIR__ . '/lib/help.php';
bin_help_if_requested(__FILE__);

$root  = dirname(__DIR__);
$quiet = in_array('--quiet', $argv, true);

const PF_OK   = 'ok';
const PF_WARN = 'warn';
const PF_FAIL = 'fail';
const PF_SKIP = 'skip';

/** @var list<array{status:string,label:string,detail:string}> $results */
$results = [];

function check(string $label, string $status, string $detail = ''): void
{
    global $results;
    $results[] = ['status' => $status, 'label' => $label, 'detail' => $detail];
}

// ─── 1. PHP runtime ───

// The version constraint and the extension list are read from composer.json
// rather than restated here, so this check cannot drift away from what the app
// actually declares it needs.
$composerJson = json_decode((string) @file_get_contents($root . '/composer.json'), true);
$require      = is_array($composerJson['require'] ?? null) ? $composerJson['require'] : [];

$constraint = (string) ($require['php'] ?? '');

if ($constraint === '') {
    check('PHP version', PF_SKIP, 'composer.json declares no php constraint');
} else {
    $phpOk = pf_satisfies_php_constraint(PHP_VERSION, $constraint);
    check(
        'PHP version',
        $phpOk ? PF_OK : PF_FAIL,
        PHP_VERSION . ($phpOk ? '' : " — composer.json requires {$constraint}"),
    );
}

// pdo_mysql is checked explicitly: composer.json can only declare ext-pdo, but
// the app talks to MySQL and a PDO build without the mysql driver passes the
// declared requirement while failing at the first query.
$extensions = ['pdo_mysql'];

foreach (array_keys($require) as $package) {
    if (str_starts_with((string) $package, 'ext-')) {
        $extensions[] = substr((string) $package, 4);
    }
}

$missing = array_values(array_filter(
    array_unique($extensions),
    static fn(string $ext): bool => !extension_loaded($ext),
));

check(
    'PHP extensions',
    $missing === [] ? PF_OK : PF_FAIL,
    $missing === []
        ? count($extensions) . ' required extension(s) loaded'
        : 'missing: ' . implode(', ', $missing) . "\n"
            . '  apt-get install ' . implode(' ', array_map(
                static fn(string $e): string => 'php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-' . $e,
                $missing,
            )),
);

// ─── 2. The web server's PHP, which is a different PHP ───

// mod_php and the CLI load separate php.ini files. Everything else in this repo
// that inspects PHP settings runs under the CLI and therefore reports on the
// wrong one: a setting can be correct for `composer migrate` and wrong for every
// page the lab actually opens. This section reads the Apache ini off disk.
$webIni = pf_apache_ini_path();

if ($webIni === null) {
    check('Web SAPI php.ini', PF_SKIP, 'no Apache mod_php config found (Docker, php-fpm, or dev machine)');
} else {
    check('Web SAPI php.ini', PF_OK, $webIni);

    // A different PHP under Apache than on the CLI means migrations, cron jobs
    // and the app itself are running on different engines. Silent, and it
    // produces failures that reproduce in one place and not the other.
    if (preg_match('#/etc/php/([0-9]+\.[0-9]+)/#', $webIni, $m) === 1) {
        $webPhp = $m[1];
        $cliPhp = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        check(
            'Web and CLI PHP',
            $webPhp === $cliPhp ? PF_OK : PF_WARN,
            $webPhp === $cliPhp
                ? "both {$cliPhp}"
                : "Apache runs {$webPhp}, CLI runs {$cliPhp} — migrations and the app "
                    . 'run on different engines',
        );
    }

    $ini = pf_read_ini_settings($webIni);

    // OPcache with validate_timestamps=0 never re-reads a changed file, so an
    // upgrade or a hand-edited config appears to do nothing at all. bootstrap.php
    // self-heals this on the first request after `composer purge-cache`, but a
    // config edited by hand has no such trigger, and this is the setting people
    // spend an afternoon on.
    $validate = $ini['opcache.validate_timestamps'] ?? null;
    if ($validate === null) {
        check('OPcache revalidation', PF_SKIP, 'opcache.validate_timestamps not set in the Apache ini');
    } else {
        $on = pf_ini_bool($validate);
        check(
            'OPcache revalidation',
            $on ? PF_OK : PF_WARN,
            $on
                ? 'opcache.validate_timestamps=1'
                : "opcache.validate_timestamps={$validate} — hand-edited config and code "
                    . "will not apply until Apache is restarted\n"
                    . '  set opcache.validate_timestamps=1 in ' . $webIni,
        );
    }

    // Imports are the largest thing anyone posts to this app, and both limits
    // have to clear the file. PHP truncates the request body silently when they
    // do not, which surfaces as an import that quietly did nothing.
    foreach (['post_max_size' => '1G', 'upload_max_filesize' => '1G'] as $key => $want) {
        $have = $ini[$key] ?? null;
        if ($have === null) {
            continue;
        }
        $ok = pf_ini_bytes($have) >= pf_ini_bytes($want);
        check(
            "Apache {$key}",
            $ok ? PF_OK : PF_WARN,
            $ok ? $have : "{$have} — large imports will be truncated; setup.sh sets {$want}",
        );
    }

    $memory = $ini['memory_limit'] ?? null;
    if ($memory !== null && pf_ini_bytes($memory) > 0 && pf_ini_bytes($memory) < pf_ini_bytes('256M')) {
        check('Apache memory_limit', PF_WARN, "{$memory} — exports and imports run out of memory below 256M");
    }

    // display_errors under Apache puts stack traces, file paths and sometimes
    // query fragments on a lab's screen.
    $display = $ini['display_errors'] ?? null;
    if ($display !== null && pf_ini_bool($display)) {
        check('Apache display_errors', PF_WARN, "display_errors={$display} — errors are shown to users; set it to Off");
    }
}

// ─── 3. Dependencies ───

$hasVendor = is_file($root . '/vendor/autoload.php');
check('vendor/ installed', $hasVendor ? PF_OK : PF_FAIL, $hasVendor ? '' : 'run: composer install');

if (!$hasVendor) {
    pf_render($results, $quiet);
    exit(1);
}

// An interrupted upgrade leaves a new composer.lock beside the old vendor tree,
// and the app then fails on a class the lock says is installed.
//
// Modification times cannot answer this. upgrade.sh lays down composer.lock and
// a prebuilt vendor tarball as two separate extractions, so on a correctly
// upgraded instance the lock is routinely the newer of the two — an mtime
// comparison flags every healthy production box and nothing else, which is how
// the first version of this check behaved.
//
// What actually settles it is the package list Composer records in
// vendor/composer/installed.json. Comparing that against the lock's own
// non-dev packages answers the real question — is what is on disk what the lock
// asked for — without caring when either arrived.
$comparison = pf_compare_lock_to_vendor($root);

if ($comparison === null) {
    check('vendor/ matches lock', PF_SKIP, 'composer.lock or vendor/composer/installed.json is unreadable');
} elseif ($comparison['problems'] === []) {
    check('vendor/ matches lock', PF_OK, $comparison['count'] . ' package(s)');
} else {
    // Three examples: enough to recognise which upgrade stopped half-way,
    // short enough to stay one finding rather than a wall of package names.
    $shown = array_slice($comparison['problems'], 0, 3);
    $extra = count($comparison['problems']) - count($shown);

    check(
        'vendor/ matches lock',
        PF_WARN,
        count($comparison['problems']) . ' package(s) differ from composer.lock: '
            . implode(', ', $shown) . ($extra > 0 ? ", and {$extra} more" : '') . "\n"
            . '  run: composer install --no-dev',
    );
}

// ─── 4. Configuration ───

$configPath = $root . '/configs/config.production.php';
$hasConfig  = is_file($configPath);

check(
    'Config file present',
    $hasConfig ? PF_OK : PF_FAIL,
    $hasConfig ? '' : 'run: cp configs/config.production.dist.php configs/config.production.php',
);

/** @var array<string, mixed> $config */
$config = [];

if ($hasConfig) {
    // Loaded through a closure so the config's own variables cannot land in this
    // script's scope, and inside a try so a parse error is reported as a finding
    // rather than as a fatal that stops every remaining check.
    try {
        $loaded = (static fn(string $path): mixed => require $path)($configPath);
        if (is_array($loaded)) {
            $config = $loaded;
            check('Config file parses', PF_OK);
        } else {
            check('Config file parses', PF_FAIL, 'the file does not return the config array');
        }
    } catch (Throwable $e) {
        check('Config file parses', PF_FAIL, $e->getMessage());
    }
}

// Every label below names the setting; every detail states what that setting
// actually is. A label naming the wanted state instead ("debug_mode off")
// contradicts its own detail line the moment the check fails, and the reader has
// to work out which of the two is the finding.
if ($config !== []) {
    $dbConfig = is_array($config['database'] ?? null) ? $config['database'] : [];

    $dbLabels = ['host' => 'Database host', 'username' => 'Database username', 'db' => 'Database name'];

    foreach ($dbLabels as $key => $label) {
        $value = (string) ($dbConfig[$key] ?? '');
        check(
            $label,
            $value !== '' ? PF_OK : PF_FAIL,
            $value !== '' ? $value : 'not set in configs/config.production.php',
        );
    }

    // Reported, not judged. instance-name is the title on the login and header
    // bars, so leaving it empty is a choice about branding rather than a fault —
    // most of the fleet sets its wording through global_config.header instead.
    // It used to also namespace the APCu definition cache, which was the one
    // thing that made an empty value actually harmful; app/system/di.php now
    // falls back to a digest of the install path for that.
    $instance = (string) ($config['instance-name'] ?? '');
    check(
        'Instance name',
        PF_OK,
        $instance !== '' ? $instance : 'not set — the default title is shown',
    );

    $remote = (string) ($config['remoteURL'] ?? '');
    check(
        'STS URL',
        $remote !== '' ? PF_OK : PF_WARN,
        $remote !== '' ? $remote : 'not set — sync and remote commands are off',
    );

    $modules = is_array($config['modules'] ?? null) ? $config['modules'] : [];
    $enabled = array_keys(array_filter($modules));
    check(
        'Modules enabled',
        $enabled === [] ? PF_WARN : PF_OK,
        $enabled === [] ? 'none — every menu will be empty' : implode(', ', $enabled),
    );

    $debug = (bool) ($config['system']['debug_mode'] ?? false);
    check(
        'Debug mode',
        $debug ? PF_WARN : PF_OK,
        $debug ? 'on — verbose errors in a production config' : 'off',
    );
}

// ─── 5. Writable by the web server, not by me ───

// is_writable() answers for the user running this script, which on a lab machine
// is root or an admin over SSH and never the user Apache runs as. A directory
// can pass that test and still be unwritable to the app. This resolves the web
// user and checks the permission bits against it instead — the root-owned
// var/cache case, where `composer purge-cache` cannot clear and post-update
// aborts, is exactly this and is invisible to a plain writability check.
$webUser = pf_web_user($root);

$dirs = [
    'var/logs',
    'var/cache',
    'var/temporary',
    'public/uploads',
    'public/temporary',
    'backups',
];

if ($webUser === null) {
    // Falling back to the current user is worth saying out loud: it is a weaker
    // check than the one this section exists to perform, and a pass here does
    // not mean the app can write.
    check(
        'Web server user',
        PF_SKIP,
        'no running web server found — checking as ' . pf_current_user() . ' instead',
    );

    $webUser = pf_resolve_user(pf_current_user());
} else {
    check('Web server user', PF_OK, $webUser['name'] . " (uid {$webUser['uid']})");
}

if ($webUser === null) {
    check('Directory permissions', PF_SKIP, 'could not resolve any user to check against');
} else {
    $notWritable = [];

    foreach ($dirs as $dir) {
        $path = $root . '/' . $dir;

        // var/temporary and the manifest subdirectories under it are created on
        // first use rather than shipped, so absence is not a fault. What matters
        // is whether the parent will let the app create them — that failure is
        // the real one, and it is silent.
        if (!is_dir($path)) {
            $parent = dirname($path);

            if (pf_writable_by($parent, $webUser)) {
                check("{$dir} writable", PF_OK, 'absent — will be created on first use');
            } else {
                check(
                    "{$dir} writable",
                    PF_FAIL,
                    is_dir($parent)
                        ? "absent, and {$webUser['name']} cannot create it in " . basename($parent) . '/'
                        : 'absent, and so is ' . basename($parent) . '/ — this install tree is incomplete',
                );
                $notWritable[] = $dir;
            }
            continue;
        }

        if (pf_writable_by($path, $webUser)) {
            check("{$dir} writable", PF_OK, 'by ' . $webUser['name']);
            continue;
        }

        check(
            "{$dir} writable",
            PF_FAIL,
            sprintf(
                'owned by %s, mode %s — %s cannot write here',
                pf_owner_name((int) fileowner($path)),
                substr(sprintf('%o', fileperms($path)), -4),
                $webUser['name'],
            ),
        );
        $notWritable[] = $dir;
    }

    if ($notWritable !== []) {
        check(
            'How to fix',
            PF_WARN,
            'run as root:' . "\n"
                . '  chown -R ' . $webUser['name'] . ':' . $webUser['name'] . ' '
                . implode(' ', array_map(static fn(string $d): string => $root . '/' . $d, $notWritable)),
        );
    }
}

// ─── 6. Database ───

$dbConfig = is_array($config['database'] ?? null) ? $config['database'] : [];
$host     = (string) ($dbConfig['host'] ?? '');
$port     = (string) ($dbConfig['port'] ?? '3306');
$name     = (string) ($dbConfig['db'] ?? '');

if ($host === '' || $name === '') {
    check('Database', PF_SKIP, 'no database configured — nothing to connect to');
    pf_render($results, $quiet);
    exit(pf_exit_code($results));
}

check('DB target', PF_OK, "{$host}:{$port}/{$name}");

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
        (string) ($dbConfig['username'] ?? ''),
        (string) ($dbConfig['password'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
    );
    check('DB reachable', PF_OK);
} catch (PDOException $e) {
    check('DB reachable', PF_FAIL, $e->getMessage());
    check('DB fix', PF_WARN, pf_db_remedy($host, $e));
    pf_render($results, $quiet);
    exit(1);
}

// Schema version against the migration files on disk. A lab left mid-upgrade
// runs new code on an old schema, and the symptom is an unrelated SQL error on
// whichever page happens to touch a new column first.
$scVersion = null;

try {
    $stmt      = $pdo->query("SELECT `value` FROM system_config WHERE `name` = 'sc_version'");
    $scVersion = $stmt === false ? null : ((string) $stmt->fetchColumn() ?: null);
} catch (PDOException) {
    // system_config absent — migrations have never run against this database.
}

$migrations = array_map(
    static fn(string $file): string => basename($file, '.sql'),
    (array) glob($root . '/sys/migrations/*.sql'),
);
usort($migrations, 'version_compare');
$latestMigration = $migrations === [] ? null : end($migrations);

if ($scVersion === null) {
    check('Schema version', PF_FAIL, 'no sc_version — this database has never been migrated' . "\n"
        . '  run: composer migrate');
} elseif ($latestMigration !== null && version_compare($latestMigration, $scVersion, '>')) {
    check(
        'Schema version',
        PF_FAIL,
        "database is at {$scVersion}, migrations go to {$latestMigration}\n"
            . '  run: composer migrate',
    );
} else {
    check('Schema version', PF_OK, "sc_version {$scVersion}");
}

// The app's version and the schema's version are bumped together by policy, so a
// gap means the code and the schema came from different releases.
if (is_file($root . '/app/system/version.php')) {
    require_once $root . '/app/system/version.php';
}

if ($scVersion !== null && defined('VERSION')) {
    $appVersion = (string) constant('VERSION');
    $matched    = version_compare($appVersion, $scVersion, '=');
    check(
        'Code and schema',
        $matched ? PF_OK : PF_WARN,
        $matched
            ? "both {$appVersion}"
            : "code is {$appVersion}, schema is {$scVersion}",
    );
}

// Collation drift silently breaks joins and sorting between an old table and a
// new one, and shows up as "Illegal mix of collations" from a query that has
// worked for years.
try {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = "BASE TABLE"
            AND TABLE_COLLATION IS NOT NULL AND TABLE_COLLATION NOT LIKE "utf8mb4%"',
    );
    $stmt->execute([$name]);
    $drifted = (int) $stmt->fetchColumn();

    check(
        'Table collation',
        $drifted === 0 ? PF_OK : PF_WARN,
        $drifted === 0 ? 'all tables utf8mb4' : "{$drifted} table(s) are not utf8mb4\n"
            . '  run: composer db:collation',
    );
} catch (PDOException $e) {
    check('Table collation', PF_SKIP, $e->getMessage());
}

// Audit triggers are what make the audit trail exist at all. They are dropped
// and reinstalled on every upgrade (post-update), so an upgrade interrupted
// between the two leaves an instance recording nothing — with no error anywhere,
// because nothing depends on them at request time.
try {
    $stmt = $pdo->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = "BASE TABLE" AND TABLE_NAME LIKE "form\_%"',
    );
    $stmt->execute([$name]);
    $formTables = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $stmt = $pdo->prepare('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?');
    $stmt->execute([$name]);
    $triggers = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    if ($formTables === []) {
        check('Audit triggers', PF_SKIP, 'no form tables yet');
    } else {
        $untriggered = array_values(array_filter(
            $formTables,
            static fn(string $table): bool => !isset($triggers[$table . '_audit_ai']),
        ));

        check(
            'Audit triggers',
            $untriggered === [] ? PF_OK : PF_FAIL,
            $untriggered === []
                ? count($formTables) . ' form table(s) audited'
                : 'no audit trigger on: ' . implode(', ', $untriggered) . "\n"
                    . '  changes to these tables are not being recorded — run: composer audit-triggers-install',
        );

        // Pre-5.5.3 triggers wrote to the columnar audit_form_* tables. Left in
        // place alongside the v2 triggers they double-write every change.
        $legacy = count(array_filter(
            array_keys($triggers),
            static fn(string $trigger): bool => str_contains($trigger, '_data__'),
        ));

        if ($legacy > 0) {
            check(
                'Legacy audit triggers',
                PF_WARN,
                "{$legacy} pre-5.5.3 trigger(s) still installed\n"
                    . '  run: composer audit-triggers-install',
            );
        }
    }
} catch (PDOException $e) {
    check('Audit triggers', PF_SKIP, $e->getMessage());
}

pf_render($results, $quiet);
exit(pf_exit_code($results));

/* ---------------------- Helpers ---------------------- */

/** @param list<array{status:string,label:string,detail:string}> $results */
function pf_exit_code(array $results): int
{
    foreach ($results as $result) {
        if ($result['status'] === PF_FAIL) {
            return 1;
        }
    }
    return 0;
}

/**
 * Whether a PHP version satisfies a composer constraint, for the small subset of
 * constraint syntax composer.json actually uses here: caret terms joined by ||,
 * plus bare >= / > comparisons. Anything else is reported as unknown by the
 * caller rather than guessed at.
 */
function pf_satisfies_php_constraint(string $version, string $constraint): bool
{
    foreach (explode('||', $constraint) as $term) {
        $term = trim($term);

        if (str_starts_with($term, '^')) {
            $base  = substr($term, 1);
            $major = (int) $base;
            if (version_compare($version, $base, '>=') && version_compare($version, ($major + 1) . '.0.0', '<')) {
                return true;
            }
            continue;
        }

        if (preg_match('/^(>=|>)\s*(.+)$/', $term, $m) === 1 && version_compare($version, trim($m[2]), $m[1])) {
            return true;
        }
    }

    return false;
}

/**
 * What composer.lock asks for against what Composer recorded as installed.
 *
 * Only the lock's non-dev packages are compared, because production installs run
 * --no-dev and every dev package would otherwise read as missing. installed.json
 * names its own dev packages in dev-package-names, so the same filter applies to
 * both sides and a --no-dev vendor tree matches a full lock cleanly.
 *
 * @return array{count:int, problems:list<string>}|null null when either file is
 *         unreadable, which is a different answer from "they disagree"
 */
function pf_compare_lock_to_vendor(string $root): ?array
{
    $lock      = json_decode((string) @file_get_contents($root . '/composer.lock'), true);
    $installed = json_decode((string) @file_get_contents($root . '/vendor/composer/installed.json'), true);

    if (!is_array($lock['packages'] ?? null) || !is_array($installed['packages'] ?? null)) {
        return null;
    }

    $devNames = array_flip(is_array($installed['dev-package-names'] ?? null) ? $installed['dev-package-names'] : []);

    $onDisk = [];

    foreach ($installed['packages'] as $package) {
        $name = (string) ($package['name'] ?? '');
        if ($name !== '' && !isset($devNames[$name])) {
            $onDisk[$name] = (string) ($package['version'] ?? '');
        }
    }

    $problems = [];

    foreach ($lock['packages'] as $package) {
        $name = (string) ($package['name'] ?? '');

        if ($name === '') {
            continue;
        }

        $wanted = (string) ($package['version'] ?? '');

        if (!isset($onDisk[$name])) {
            $problems[] = "{$name} missing";
            continue;
        }

        if ($onDisk[$name] !== $wanted) {
            $problems[] = "{$name} {$onDisk[$name]} != {$wanted}";
        }
    }

    return ['count' => count($lock['packages']), 'problems' => $problems];
}

/**
 * The php.ini mod_php loads, which is not the one this script is running under.
 *
 * The enabled Apache module is the authority — /etc/php can hold several
 * versions at once and only one of them is serving. Falls back to a lone
 * apache2 ini when the module layout is not Debian's.
 */
function pf_apache_ini_path(): ?string
{
    foreach ((array) glob('/etc/apache2/mods-enabled/php*.load') as $module) {
        if (preg_match('/php([0-9]+\.[0-9]+)\.load$/', (string) $module, $m) === 1) {
            $ini = "/etc/php/{$m[1]}/apache2/php.ini";
            if (is_readable($ini)) {
                return $ini;
            }
        }
    }

    $found = array_values(array_filter(
        (array) glob('/etc/php/*/apache2/php.ini'),
        static fn($path): bool => is_readable((string) $path),
    ));

    return count($found) === 1 ? (string) $found[0] : null;
}

/**
 * The last uncommented assignment of each directive in an ini file.
 *
 * parse_ini_file() is not used: setup.sh edits these files by commenting out the
 * old line and appending the new one, and a php.ini carrying section headers and
 * unquoted values makes parse_ini_file bail on the whole file. Last-wins matches
 * how PHP itself reads them.
 *
 * @return array<string, string>
 */
function pf_read_ini_settings(string $path): array
{
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return [];
    }

    $settings = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === ';' || $line[0] === '#' || $line[0] === '[') {
            continue;
        }

        if (preg_match('/^([A-Za-z0-9_.]+)\s*=\s*(.*)$/', $line, $m) === 1) {
            $settings[$m[1]] = trim(trim($m[2]), '"\'');
        }
    }

    return $settings;
}

function pf_ini_bool(string $value): bool
{
    return in_array(strtolower(trim($value)), ['1', 'on', 'true', 'yes'], true);
}

/** Bytes for an ini shorthand value such as 1G, 256M, 8192. */
function pf_ini_bytes(string $value): int
{
    $value = trim($value);

    if ($value === '' || $value === '-1') {
        return $value === '-1' ? PHP_INT_MAX : 0;
    }

    $number = (int) $value;

    return match (strtolower(substr($value, -1))) {
        'g'     => $number * 1024 * 1024 * 1024,
        'm'     => $number * 1024 * 1024,
        'k'     => $number * 1024,
        default => $number,
    };
}

/**
 * The user the web server runs as.
 *
 * Two sources of evidence, both of them observations rather than conventions:
 * a running apache/httpd worker, and failing that the account that already owns
 * var/cache — which is decisive on a box whose Apache is stopped, since that is
 * precisely the machine somebody is running this on. The conventional account
 * names are only ever used to recognise those observations, never on their own:
 * _www exists on every Mac and www-data on hosts that serve nothing, so treating
 * mere existence as proof reports permission failures against a user that is not
 * serving anything.
 *
 * @return array{name:string, uid:int, gids:list<int>}|null
 */
function pf_web_user(string $root): ?array
{
    $known = ['www-data', 'apache', 'apache2', '_www', 'http', 'nginx'];

    // ps prints the command last so the user field can be taken from the front
    // of the line; matching on the command column is what keeps this from
    // reading the pipeline's own grep as an Apache worker.
    $ps = (string) @shell_exec('ps -eo user=,comm= 2>/dev/null');

    foreach (explode("\n", $ps) as $line) {
        $fields = preg_split('/\s+/', trim($line)) ?: [];

        if (count($fields) < 2) {
            continue;
        }

        $user = (string) $fields[0];
        $comm = basename((string) end($fields));

        // Apache's parent process runs as root and forks the workers that
        // actually touch these directories, so root is not the answer here.
        if ($user !== 'root' && in_array($comm, ['apache2', 'httpd', 'nginx'], true)) {
            $resolved = pf_resolve_user($user);
            if ($resolved !== null) {
                return $resolved;
            }
        }
    }

    $owner = @fileowner($root . '/var/cache');

    if ($owner !== false) {
        $name = pf_owner_name((int) $owner);
        if (in_array($name, $known, true)) {
            return pf_resolve_user($name);
        }
    }

    return null;
}

/** @return array{name:string, uid:int, gids:list<int>}|null */
function pf_resolve_user(string $name): ?array
{
    $uid = trim((string) @shell_exec('id -u ' . escapeshellarg($name) . ' 2>/dev/null'));

    if ($uid === '' || !ctype_digit($uid)) {
        return null;
    }

    $gids = array_values(array_filter(array_map(
        'intval',
        preg_split('/\s+/', trim((string) @shell_exec('id -G ' . escapeshellarg($name) . ' 2>/dev/null'))) ?: [],
    )));

    return ['name' => $name, 'uid' => (int) $uid, 'gids' => $gids];
}

/** @param array{name:string, uid:int, gids:list<int>} $user */
function pf_writable_by(string $path, array $user): bool
{
    // Callers ask about a directory's parent, and on a half-installed tree that
    // parent can be missing too. Nothing can be written into what is not there,
    // and the stat functions warn rather than return false for it — which would
    // put PHP's own output in the middle of the report.
    if (!file_exists($path)) {
        return false;
    }

    $perms = fileperms($path);
    $owner = fileowner($path);
    $group = filegroup($path);

    if ($perms === false || $owner === false || $group === false) {
        return false;
    }

    if ($owner === $user['uid']) {
        return ($perms & 0o200) !== 0;
    }

    if (in_array((int) $group, $user['gids'], true)) {
        return ($perms & 0o020) !== 0;
    }

    return ($perms & 0o002) !== 0;
}

function pf_owner_name(int $uid): string
{
    $name = trim((string) @shell_exec('id -nu ' . escapeshellarg((string) $uid) . ' 2>/dev/null'));

    return $name === '' ? "uid {$uid}" : $name;
}

function pf_current_user(): string
{
    $name = trim((string) @shell_exec('id -nu 2>/dev/null'));

    return $name === '' ? 'the current user' : $name;
}

/**
 * The remedy for a connection failure, chosen from the driver's own error rather
 * than printed as a menu of everything it could have been.
 */
function pf_db_remedy(string $host, PDOException $e): string
{
    $message = strtolower($e->getMessage());

    if (str_contains($message, 'access denied')) {
        return 'the credentials in configs/config.production.php are rejected by MySQL' . "\n"
            . '  check database.username / database.password';
    }

    if (str_contains($message, 'unknown database')) {
        return 'the database named in configs/config.production.php does not exist' . "\n"
            . '  create it, then run: composer migrate';
    }

    if (str_contains($message, "can't connect") || str_contains($message, 'connection refused')) {
        return in_array($host, ['localhost', '127.0.0.1'], true)
            ? 'MySQL is not answering on this machine' . "\n" . '  systemctl status mysql'
            : "nothing is answering on {$host} — check the host, the port, and whether MySQL allows remote connections";
    }

    return 'check database settings in configs/config.production.php';
}

/**
 * Usable terminal width, falling back to 100 where nothing will say.
 *
 * COLUMNS is a shell variable and is usually not exported to child processes, so
 * tput is what actually answers on a real terminal. Both are consulted because
 * tput is absent from minimal container images.
 */
function pf_terminal_width(): int
{
    static $width = null;

    if ($width !== null) {
        return $width;
    }

    $env = (int) getenv('COLUMNS');

    if ($env > 0) {
        return $width = $env;
    }

    $reported = (int) trim((string) @shell_exec('tput cols 2>/dev/null'));

    return $width = $reported > 0 ? $reported : 100;
}

/** @param list<array{status:string,label:string,detail:string}> $results */
function pf_render(array $results, bool $quiet): void
{
    $tty    = function_exists('stream_isatty') && stream_isatty(STDOUT);
    $green  = $tty ? "\033[32m" : '';
    $yellow = $tty ? "\033[33m" : '';
    $red    = $tty ? "\033[31m" : '';
    $dim    = $tty ? "\033[2m" : '';
    $reset  = $tty ? "\033[0m" : '';

    $counts = [PF_OK => 0, PF_WARN => 0, PF_FAIL => 0, PF_SKIP => 0];

    echo "\n";

    foreach ($results as $result) {
        $counts[$result['status']]++;

        if ($quiet && ($result['status'] === PF_OK || $result['status'] === PF_SKIP)) {
            continue;
        }

        [$mark, $colour] = match ($result['status']) {
            PF_OK   => ['PASS', $green],
            PF_WARN => ['WARN', $yellow],
            PF_SKIP => ['SKIP', $dim],
            default => ['FAIL', $red],
        };

        // 2 spaces + 4-char mark + 2 spaces + 26-char label + 1 space.
        $gutter = 35;
        $detail = $result['detail'];

        // A remedy runs to several lines, and left alone the terminal breaks it
        // mid-word at the right edge with the continuation starting at column 0 —
        // which reads as a separate finding rather than the rest of this one.
        // Every line is indented to the detail column so the block holds together.
        //
        // The author's own line breaks are honoured either way; only the
        // width-driven wrapping is skipped when not attached to a terminal, since
        // piped output is being grepped or pasted into a ticket and a break
        // inserted mid-sentence hides that sentence from a search.
        if ($detail !== '') {
            $usable = $tty ? pf_terminal_width() - $gutter : 0;
            $lines  = [];

            // Split on the author's own line breaks first, then wrap each one.
            // wordwrap() counts from the start of the whole string rather than
            // resetting at an embedded newline, so handing it a multi-line remedy
            // wraps the later lines in the wrong places.
            foreach (explode("\n", $detail) as $line) {
                // An indented line is a command to be copied. Never hard-wrap one:
                // the terminal soft-wraps it and it still copies as a single line,
                // whereas a real newline in the middle of it copies as two and runs
                // as neither.
                $wrap = $usable > 20 && !str_starts_with($line, ' ');

                foreach ($wrap ? explode("\n", wordwrap($line, $usable, "\n", false)) : [$line] as $part) {
                    $lines[] = $part;
                }
            }

            $detail = implode("\n" . str_repeat(' ', $gutter), $lines);
        }

        printf(
            "  %s%s%s  %-26s %s%s%s\n",
            $colour,
            $mark,
            $reset,
            $result['label'],
            $dim,
            $detail,
            $reset,
        );
    }

    printf(
        "\n  %d passed, %d warning(s), %d failed, %d skipped\n\n",
        $counts[PF_OK],
        $counts[PF_WARN],
        $counts[PF_FAIL],
        $counts[PF_SKIP],
    );
}

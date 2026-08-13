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
 * `intelis check`" instead of decoding an Apache error log over WhatsApp.
 *
 * Checks, in order:
 *   1. PHP runtime — version and the extensions composer.json requires
 *   2. The web SAPI's php.ini, which the CLI cannot see and which is where the
 *      silent breakage lives (OPcache serving stale code, upload limits)
 *   3. vendor/ present and not stale against composer.lock
 *   4. configs/config.production.php present, parseable, and filled in
 *   5. Paths writable BY THE WEB SERVER USER, not by whoever ran this
 *   6. Database reachable, migrations current, collation clean, audit triggers
 *      installed, instance registered — and when the connection fails, why it
 *      failed rather than only that it did
 *
 * Usage:
 *   intelis check                  run all checks
 *   intelis check --quiet          only print warnings and failures (CI / hooks)
 *   php bin/preflight.php --help   print this docblock
 *
 * `composer preflight` still works and is what setup.sh and upgrade.sh call,
 * since they run before the intelis command is necessarily in place. Every
 * remedy printed below names the intelis form, because that is the one an
 * operator can type from anywhere on the machine — composer has to be run from
 * the installation directory, and a lab reading a remedy off a screen is rarely
 * standing in it.
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

// The same unreadable-ini failure as under Apache below, seen from the inside:
// PHP skips an ini it cannot open and carries on with its built-in defaults,
// silently. Only raised when the file is there to be loaded and was not, so a
// deployment that genuinely ships no php.ini (a slim container) stays quiet.
$cliIni      = php_ini_loaded_file();
$expectedIni = '/etc/php/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '/cli/php.ini';

if ($cliIni === false && is_file($expectedIni)) {
    check(
        'CLI php.ini',
        PF_WARN,
        "{$expectedIni} exists but was not loaded — not readable by " . pf_current_user() . "\n"
            . '  this PHP is running on built-in defaults; run: sudo chmod 0644 ' . $expectedIni,
    );
}

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

    // An unreadable php.ini is not a reporting inconvenience, it is the finding.
    // Apache reads its ini as root before dropping privileges, so mod_php keeps
    // serving and nothing looks wrong — but every non-root PHP process on the box
    // silently loses the file. `php -i` run as www-data then says "Loaded
    // Configuration File => (none)" and the cron jobs, bin/ scripts and composer
    // migrations that run as www-data are executing on PHP's built-in defaults
    // rather than on the settings this instance was configured with.
    if (!is_readable($webIni)) {
        check(
            'Web SAPI php.ini readable',
            PF_WARN,
            'not readable by ' . pf_current_user() . " — every non-root PHP process is ignoring it\n"
                . '  run: sudo chmod 0644 ' . $webIni,
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
check('vendor/ installed', $hasVendor ? PF_OK : PF_FAIL, $hasVendor ? '' : 'run: intelis install');

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
            . '  run: intelis install --no-dev',
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

    // remoteURL is read here but reported further down, next to the instance
    // identity checks. Whether an empty one is a fault depends on what kind of
    // instance this is, and that lives in system_config — so the row cannot be
    // judged until the database is open.

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
    // How the answer is reached matters more than who it is about, so it is said
    // here once instead of on every directory row.
    check(
        'Web server user',
        PF_OK,
        $webUser['name'] . " (uid {$webUser['uid']}) — " . pf_write_test_method($webUser),
    );
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

            if (pf_can_write($parent, $webUser)) {
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

        if (pf_can_write($path, $webUser)) {
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
        // bin/provision.php already does exactly this repair — it creates what is
        // missing and, run as root, sets owner:www-data group-writable so both the
        // CLI user and the web server can write. It is idempotent and exits 0
        // regardless, so pointing at it beats a hand-written chown that fixes only
        // the paths this run happened to notice.
        //
        // The chown stays as a second line for the one case provision cannot help
        // with: it boots the app to learn the paths, so a root-owned var/cache can
        // stop it before it starts — the same failure upgrade.sh pre-empts by
        // chowning var/cache before it calls composer.
        check(
            'How to fix',
            PF_WARN,
            'intelis provision' . "\n"
                . 'if that cannot start, clear the way for it first:' . "\n"
                . '  sudo chown -R $(logname):' . $webUser['name'] . ' '
                . implode(' ', array_map(static fn(string $d): string => $root . '/' . $d, $notWritable)) . "\n"
                . '  sudo chmod -R g+w '
                . implode(' ', array_map(static fn(string $d): string => $root . '/' . $d, $notWritable)),
        );
    }
}

// ─── 6. Database ───

$dbConfig  = is_array($config['database'] ?? null) ? $config['database'] : [];
$host      = (string) ($dbConfig['host'] ?? '');
$port      = (string) ($dbConfig['port'] ?? '3306');
$name      = (string) ($dbConfig['db'] ?? '');
$remoteUrl = (string) ($config['remoteURL'] ?? '');

if ($host === '' || $name === '') {
    check('Database', PF_SKIP, 'no database configured — nothing to connect to');
    pf_check_sts_url($remoteUrl, null);
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

    // The driver message says what happened to the connection, never why. It
    // reports the same "Connection refused" whether MySQL is stopped, crashed on
    // startup, or running fine behind a closed firewall port — three problems
    // with three different fixes. These rows go looking for the difference, and
    // they run only on failure, so a healthy instance never pays for them and
    // this stays a diagnosis rather than the ongoing service monitoring that
    // belongs in bin/health.php.
    // The generic remedy is the fallback for when the probing above could not
    // narrow it down. Printing both makes the report restate itself at exactly
    // the moment it had something specific to say.
    if (!pf_diagnose_db($host, $port, $e)) {
        check('DB fix', PF_WARN, pf_db_remedy($host, $e));
    }
    pf_check_sts_url($remoteUrl, null);
    pf_render($results, $quiet);
    exit(1);
}

// Schema version against the migration files on disk. A lab left mid-upgrade
// runs new code on an old schema, and the symptom is an unrelated SQL error on
// whichever page happens to touch a new column first.
// sc_user_type comes along for the ride: it is what makes this machine an STS,
// an LIS or a standalone lab, and several rows below read differently depending
// on which one it is.
$scVersion    = null;
$instanceType = null;

try {
    $stmt = $pdo->query("SELECT `name`, `value` FROM system_config WHERE `name` IN ('sc_version', 'sc_user_type')");
    $rows = $stmt === false ? [] : ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: []);

    $scVersion    = ((string) ($rows['sc_version'] ?? '')) ?: null;
    $instanceType = ((string) ($rows['sc_user_type'] ?? '')) ?: null;
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
        . '  run: intelis migrate');
} elseif ($latestMigration !== null && version_compare($latestMigration, $scVersion, '>')) {
    check(
        'Schema version',
        PF_FAIL,
        "database is at {$scVersion}, migrations go to {$latestMigration}\n"
            . '  run: intelis migrate',
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
            . '  run: intelis db:collation',
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

    $stmt = $pdo->prepare(
        'SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?',
    );
    $stmt->execute([$name]);
    $triggerRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $triggers    = array_flip(array_column($triggerRows, 'TRIGGER_NAME'));

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
                    . '  changes to these tables are not being recorded — run: intelis audit-triggers-install',
        );

        // Pre-5.5.3 triggers wrote to the columnar audit_form_* tables. Left in
        // place alongside the v2 triggers they double-write every change.
        //
        // The tables are named, not just counted. These survive precisely because
        // they sit on a table the reinstall does not visit, so "2 still installed"
        // sends someone to re-run a command that has already run and will not help,
        // while the table name is the whole diagnosis.
        $legacyTables = [];

        foreach ($triggerRows as $row) {
            if (str_contains((string) $row['TRIGGER_NAME'], '_data__')) {
                $legacyTables[(string) $row['EVENT_OBJECT_TABLE']] = true;
            }
        }

        $legacy = count(array_filter(
            array_keys($triggers),
            static fn(string $trigger): bool => str_contains($trigger, '_data__'),
        ));

        if ($legacy > 0) {
            check(
                'Legacy audit triggers',
                PF_WARN,
                "{$legacy} pre-5.5.3 trigger(s) still installed on: "
                    . implode(', ', array_keys($legacyTables)) . "\n"
                    . '  run: intelis audit-triggers-install',
            );
        }
    }
} catch (PDOException $e) {
    check('Audit triggers', PF_SKIP, $e->getMessage());
}

check(
    'Instance type',
    $instanceType !== null ? PF_OK : PF_SKIP,
    $instanceType ?? 'system_config has no sc_user_type row',
);

pf_check_sts_url($remoteUrl, $instanceType);

// s_vlsm_instance holds the one row that identifies this machine: the ULID
// minted once at registration and the STS token issued against it. Both come
// from app/setup/registerProcess.php, and nothing else ever writes them.
//
// With no row there is no instance id and no token, so every remote call —
// metadata sync, results, requests, pending commands — runs unidentified and
// unauthenticated. Each one fails on its own and logs its own failure, and none
// of them says the cause is that this instance was never registered.
//
// How much that matters depends entirely on whether an STS is configured, so the
// severity follows remoteURL rather than treating an instance that syncs with
// nobody as broken.
try {
    $instanceRow = $pdo->query('SELECT vlsm_instance_id, sts_token FROM s_vlsm_instance LIMIT 1');
    $instance    = $instanceRow === false ? false : $instanceRow->fetch(PDO::FETCH_ASSOC);
    $instanceId  = (string) ($instance['vlsm_instance_id'] ?? '');

    if ($instanceId !== '') {
        check('Instance registered', PF_OK, $instanceId);

        // The id and the token are written by the same registration and rotated
        // together, so an id without a token means a rotation that did not
        // finish rather than a machine that was never registered.
        if ($remoteUrl !== '' && (string) ($instance['sts_token'] ?? '') === '') {
            check(
                'STS token',
                PF_WARN,
                "registered, but no STS token is stored\n"
                    . '  run: intelis token',
            );
        }
    } elseif ($remoteUrl === '') {
        check('Instance registered', PF_WARN, 'not registered — expected when no STS is configured');
    } else {
        check(
            'Instance registered',
            PF_FAIL,
            "not registered, but an STS is configured — sync cannot identify this instance\n"
                . '  complete setup in a browser at /setup/index.php',
        );
    }
} catch (PDOException $e) {
    check('Instance registered', PF_SKIP, $e->getMessage());
}

pf_render($results, $quiet);
exit(pf_exit_code($results));

/* ---------------------- Helpers ---------------------- */

/**
 * The STS URL row. An empty remoteURL is only a finding on an instance that is
 * supposed to sync to an STS, so the severity is decided by sc_user_type and not
 * by the setting alone:
 *
 *   stsmode / remoteuser  this machine IS the STS; it has no STS above it to
 *                         point at, so empty is the correct configuration
 *   standalone            deliberately syncs with nobody
 *   vluser / lismode      an LIS with nowhere to send results — the real finding
 *
 * $instanceType is null when the database could not be read, which is the one
 * case where the row can only state the setting without judging it. The status
 * mirrors CommonService::isSTSInstance()/isLISInstance(); those two are the
 * definition and this restates them because preflight runs pre-boot and cannot
 * load the class.
 */
function pf_check_sts_url(string $remoteUrl, ?string $instanceType): void
{
    if ($remoteUrl !== '') {
        check('STS URL', PF_OK, $remoteUrl);
        return;
    }

    if ($instanceType === null) {
        check('STS URL', PF_OK, 'not set — instance type unknown, so whether that is expected cannot be told');
        return;
    }

    $isLis = $instanceType === 'vluser' || $instanceType === 'lismode';
    $isSts = $instanceType === 'stsmode' || $instanceType === 'remoteuser';

    check(
        'STS URL',
        $isLis ? PF_WARN : PF_OK,
        match (true) {
            $isLis  => 'not set — sync and remote commands are off',
            $isSts  => 'not set — this instance is the STS',
            default => "not set — expected on a {$instanceType} instance",
        },
    );
}

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
    // Existence, not readability. A php.ini this script cannot read is still the
    // file Apache is serving under, and testing readability here made the
    // unreadable case fall through to the search below and answer with a
    // different PHP version entirely — a confident, wrong report. Whether the
    // file can be read is a separate finding, raised by the caller.
    foreach ((array) glob('/etc/apache2/mods-enabled/php*.load') as $module) {
        if (preg_match('/php([0-9]+\.[0-9]+)\.load$/', (string) $module, $m) === 1) {
            $ini = "/etc/php/{$m[1]}/apache2/php.ini";
            if (is_file($ini)) {
                return $ini;
            }
        }
    }

    $found = array_values(array_filter(
        (array) glob('/etc/php/*/apache2/php.ini'),
        static fn($path): bool => is_file((string) $path),
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

/**
 * Whether a user can write to a path — tested against the kernel where possible,
 * inferred only as a last resort.
 *
 * The distinction is the whole point. scripts/shared-functions.sh::set_permissions
 * grants the web server access with POSIX ACLs (setfacl -m u:www-data:rwx), which
 * leave owner and group untouched. Reading owner/group/mode therefore reports
 * "root, 0775, www-data cannot write here" about a directory www-data writes to
 * all day, and every instance in the fleet is set up that way. Inference cannot
 * see an ACL; a write test does not have to.
 *
 * Three tiers, best first:
 *   1. Already running as that user — is_writable() asks the kernel, which
 *      applies ACLs, and is exactly the question being asked. This is the live
 *      path: setup.sh and upgrade.sh both invoke preflight as www-data.
 *   2. Running as root — sudo can pose the question as that user, same accuracy.
 *   3. Neither — fall back to the permission bits, plus getfacl where it exists
 *      so the common ACL grant is still seen. Approximate, and pf_write_test_method()
 *      says so in the report rather than letting a guess read like a measurement.
 *
 * @param array{name:string, uid:int, gids:list<int>} $user
 */
function pf_can_write(string $path, array $user): bool
{
    // Callers ask about a directory's parent, and on a half-installed tree that
    // parent can be missing too. Nothing can be written into what is not there,
    // and the stat functions warn rather than return false for it — which would
    // put PHP's own output in the middle of the report.
    if (!file_exists($path)) {
        return false;
    }

    if (pf_euid() === $user['uid']) {
        return is_writable($path);
    }

    if (pf_euid() === 0) {
        // -n so a sudoers rule needing a password fails immediately instead of
        // blocking a report on a prompt nobody is watching.
        @exec(
            'sudo -n -u ' . escapeshellarg($user['name']) . ' test -w ' . escapeshellarg($path) . ' 2>/dev/null',
            $ignored,
            $code,
        );

        if ($code === 0 || $code === 1) {
            return $code === 0;
        }
        // Any other code means sudo itself failed; fall through and infer.
    }

    return pf_probably_writable_by($path, $user);
}

/**
 * How pf_can_write() will answer for this user, in words for the report.
 *
 * A check that cannot say whether it measured or guessed invites the guess to be
 * acted on, which is how the mode-bit version sent people to chown directories
 * that were already writable.
 *
 * @param array{name:string, uid:int, gids:list<int>} $user
 */
function pf_write_test_method(array $user): string
{
    if (pf_euid() === $user['uid']) {
        return 'writability tested directly';
    }

    if (pf_euid() === 0) {
        return 'writability tested via sudo';
    }

    return 'writability inferred from permissions, not tested — re-run as root or '
        . $user['name'] . ' to be sure';
}

/** Effective uid, without assuming ext-posix is installed. */
function pf_euid(): int
{
    static $euid = null;

    if ($euid !== null) {
        return $euid;
    }

    if (function_exists('posix_geteuid')) {
        return $euid = posix_geteuid();
    }

    $reported = trim((string) @shell_exec('id -u 2>/dev/null'));

    return $euid = ctype_digit($reported) ? (int) $reported : -1;
}

/**
 * The inference tier: permission bits, widened by any ACL granting this user
 * write access. Only ever reached when neither tier above could run.
 *
 * @param array{name:string, uid:int, gids:list<int>} $user
 */
function pf_probably_writable_by(string $path, array $user): bool
{
    $perms = fileperms($path);
    $owner = fileowner($path);
    $group = filegroup($path);

    if ($perms === false || $owner === false || $group === false) {
        return false;
    }

    if ($owner === $user['uid'] && ($perms & 0o200) !== 0) {
        return true;
    }

    if (in_array((int) $group, $user['gids'], true) && ($perms & 0o020) !== 0) {
        return true;
    }

    if (($perms & 0o002) !== 0) {
        return true;
    }

    // getfacl prints one entry per line as user:<name>:<rwx>. Only -p is passed:
    // it is the one flag every version has, and anything narrower risks the
    // command erroring out and being read as "no access". Some versions append
    // a "\t#effective:r-x" comment, so the entry is matched without anchoring
    // the end of the line, and the mask -- which can strip a granted w -- is
    // checked separately.
    $acl = (string) @shell_exec('getfacl -p ' . escapeshellarg($path) . ' 2>/dev/null');

    if ($acl === '') {
        return false;
    }

    if (preg_match('/^user:' . preg_quote($user['name'], '/') . ':[r-]w/m', $acl) !== 1) {
        return false;
    }

    // No mask line means nothing is being masked, so the grant stands as written.
    if (preg_match('/^mask::/m', $acl) !== 1) {
        return true;
    }

    return preg_match('/^mask::[r-]w/m', $acl) === 1;
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
 * Why the database is unreachable, as far as this machine can tell.
 *
 * Emits its own findings rather than returning a string, because the answer is
 * several independent observations and collapsing them into one line loses which
 * of them is the surprising one.
 *
 * Split by where the database lives, since the useful evidence differs. For a
 * local server the question is whether mysqld is running and, if it is not,
 * whether it failed rather than was stopped. For a remote one this machine can
 * only say whether anything answers on the port, which is exactly the line
 * between a network problem and a MySQL problem.
 *
 * @return bool true when it reached a conclusion specific enough to act on, so
 *         the caller can drop the generic remedy rather than restate it
 */
function pf_diagnose_db(string $host, string $port, PDOException $e): bool
{
    // Rejected credentials and an unknown database are answers, not mysteries:
    // MySQL was reached and replied. Probing the service after one of those adds
    // rows that all say "working fine" underneath a failure, which reads as a
    // contradiction and buries the line that actually matters.
    $message = strtolower($e->getMessage());

    foreach (['access denied', 'unknown database'] as $answered) {
        if (str_contains($message, $answered)) {
            return false;
        }
    }

    if (!in_array($host, ['localhost', '127.0.0.1', '::1', gethostname()], true)) {
        // A refused connection is a host that answered; a timeout is one that
        // never did. That difference decides whether to look at MySQL's bind
        // address or at the firewall between here and there.
        $errno  = 0;
        $errstr = '';
        $socket = @fsockopen($host, (int) $port, $errno, $errstr, 5);

        if ($socket !== false) {
            fclose($socket);
            check('MySQL port', PF_OK, "{$host}:{$port} accepts connections — MySQL itself rejected us");
            return false;
        }

        check(
            'MySQL port',
            PF_FAIL,
            "nothing answers on {$host}:{$port} — {$errstr}\n"
                . '  the credentials are not the problem; check the firewall, the port, '
                . "and MySQL's bind-address",
        );
        return true;
    }

    $diagnosed = false;
    $service   = pf_mysql_service();

    if ($service === null) {
        check('MySQL service', PF_SKIP, 'systemctl not available — cannot tell whether MySQL is running');
    } else {
        [$name, $state] = $service;

        if ($state === 'active') {
            // Running, yet the connection failed: the socket path or the bind
            // address is wrong, or the credentials are. Not a service problem,
            // and saying so stops the next hour going into restarting it.
            check(
                'MySQL service',
                PF_OK,
                "{$name} is running — so this is a socket, bind-address or credentials problem",
            );
        } else {
            check(
                'MySQL service',
                PF_FAIL,
                "{$name} is {$state}\n"
                    . "  systemctl status {$name} --no-pager -n 20",
            );
            $diagnosed = true;
        }
    }

    // The error log names the actual cause when MySQL failed to start — a
    // corrupt InnoDB page, a full disk, a permissions change on the data
    // directory. It is normally root-readable only, and preflight runs as
    // www-data, so the path is worth printing even when the contents are not
    // reachable from here.
    $logs = array_values(array_filter(
        ['/var/log/mysql/error.log', '/var/log/mysqld.log', '/var/log/mariadb/mariadb.log'],
        static fn(string $path): bool => is_file($path),
    ));

    if ($logs === []) {
        return $diagnosed;
    }

    $log = $logs[0];

    if (!is_readable($log)) {
        check(
            'MySQL error log',
            PF_SKIP,
            "{$log} — not readable as " . pf_current_user() . "\n  try: sudo tail -20 " . $log,
        );
        return $diagnosed;
    }

    $lines = @file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $errors = array_values(array_filter(
        array_slice($lines, -200),
        static fn(string $line): bool => stripos($line, '[error]') !== false,
    ));

    check(
        'MySQL error log',
        $errors === [] ? PF_OK : PF_WARN,
        $errors === []
            ? "no errors in the tail of {$log}"
            : 'last error in ' . $log . ":\n  " . end($errors),
    );

    return $diagnosed;
}

/**
 * The MySQL unit on this machine and its current state.
 *
 * The unit is named mysql on Debian, mysqld on RHEL and mariadb where MariaDB
 * replaced it, and asking for the wrong one returns "inactive" rather than an
 * error — which would report a running server as stopped. So each candidate is
 * checked for existence before its state is believed.
 *
 * @return array{0:string, 1:string}|null
 */
function pf_mysql_service(): ?array
{
    if (trim((string) @shell_exec('command -v systemctl 2>/dev/null')) === '') {
        return null;
    }

    foreach (['mysql', 'mariadb', 'mysqld'] as $name) {
        $loaded = trim((string) @shell_exec(
            'systemctl show ' . escapeshellarg($name) . ' --property=LoadState --value 2>/dev/null',
        ));

        if ($loaded !== 'loaded') {
            continue;
        }

        $state = trim((string) @shell_exec('systemctl is-active ' . escapeshellarg($name) . ' 2>/dev/null'));

        return [$name, $state === '' ? 'unknown' : $state];
    }

    return null;
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
            . '  create it, then run: intelis migrate';
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

#!/usr/bin/env php
<?php

declare(strict_types=1);

// bin/setup/setup-interface.php
//
// Interactive setup for instrument interfacing.
//
// What this replaces is a page of a guide: open config.production.php in nano,
// find seven array keys among eighty, type a database password into a PHP file
// without a typo, then go back to a terminal and create the database and the
// MySQL account by hand from a second page of the guide. Every one of those
// steps is a step somebody has got wrong on a machine we then had to look at,
// and the commonest way to get it wrong -- leaving `enabled` false -- produces
// no error, no log line and no result, which reads exactly like a broken
// analyzer.
//
// So this asks the two questions that actually vary (where the tool stores its
// results, and where the tool runs), and does the rest: creates the database
// from sql/interface-init.sql, creates the account the tool connects with,
// writes the configuration, proves the connection works, and prints the four
// values to type into the tool.
//
// The interfacing database is deliberately the local one. The Interfacing Tool
// reaches across the network to this machine; InteLIS does not reach across the
// network to a database. A remote interfacing database is still offered, because
// somebody will one day have one, but it is not the path this steers towards.
//
// Usage:
//   composer interface-setup           (or: intelis interface setup)
//   php bin/setup/setup-interface.php

use App\Utilities\MiscUtility;
use App\Services\ConfigService;
use App\Utilities\CliPickerUtility;
use App\Services\DatabaseService;
use App\Utilities\CliPromptUtility;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

if (PHP_SAPI !== 'cli') {
    exit(CLI\ERROR);
}

if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function (): void {
        echo PHP_EOL . "Interfacing setup cancelled. Nothing has been changed." . PHP_EOL;
        exit(CLI\SIGINT);
    });
}

require_once __DIR__ . '/../../bootstrap.php';

ini_set('memory_limit', '-1');
set_time_limit(0);

// Every mysqli call below wants a throw rather than a false and a getter, and
// mysqli_report is global: it is set once here rather than saved and restored
// around each call, because mysqli_report returns whether it succeeded and not
// what the mode used to be, so there is nothing to restore it to. PHP 8.1 and
// later already default to exactly this.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const WEB_ACCOUNTS = ['www-data', 'apache', 'apache2', '_www', 'http', 'nginx'];

/** What sql/interface-init.sql builds, and what the tool and InteLIS then read. */
const SEEDED_TABLES = ['app_log', 'orders', 'raw_data', 'versions', 'telemetry_events', 'usage_statistics_daily'];

// ---------------------------------------------------------------------------
// Talking to MySQL as an administrator
// ---------------------------------------------------------------------------

/**
 * The handful of administrative statements this wizard needs, over whichever of
 * the two routes to MySQL this machine actually has.
 *
 * Two routes because neither one covers every installation. mysqli covers a
 * machine whose MySQL account has a password -- which a scripted InteLIS install
 * produces, and whose credentials are already in config.production.php, so
 * nothing has to be asked. It cannot cover stock Ubuntu, where root authenticates
 * through the unix socket: mysqlnd does not implement that plugin, which is why
 * `sudo mysql` works from a shell and PHP cannot follow it. There the mysql
 * client is the only way in, and it is available only when this is running as
 * root -- which `sudo intelis interface` already is.
 */
final class InterfaceDbAdmin
{
    private function __construct(
        private readonly ?mysqli $link,
        private readonly ?string $clientCommand,
        public readonly string $description,
    ) {
    }

    public static function viaMysqli(mysqli $link, string $description): self
    {
        return new self($link, null, $description);
    }

    public static function viaClient(string $command, string $description): self
    {
        return new self(null, $command, $description);
    }

    /**
     * Connect with explicit credentials, or return the reason it could not.
     *
     * @return array{0: ?self, 1: string}
     */
    public static function tryMysqli(string $host, int $port, string $user, string $password, string $description): array
    {
        // A plain mysqli, not DatabaseService: this creates a database, creates
        // accounts and switches schema, none of which is application data access,
        // and DatabaseService opens persistent connections and prepares every
        // statement it is handed -- neither of which a credentials probe wants.
        try {
            $link = new mysqli($host, $user, $password, null, $port);
            $link->query('SELECT 1');
            return [self::viaMysqli($link, $description), ''];
        } catch (Throwable $e) {
            return [null, trim($e->getMessage())];
        }
    }

    /**
     * The mysql client, as root, over the unix socket.
     *
     * --no-defaults is not optional: as root, /etc/mysql and /root/.my.cnf are
     * read first and a stale password in either is used in preference to socket
     * authentication, so the connection fails with credentials nobody supplied.
     *
     * @return array{0: ?self, 1: string}
     */
    public static function tryRootClient(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [null, 'not available on Windows'];
        }
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            return [null, 'not running as root'];
        }
        if (!CliPickerUtility::hasCommand('mysql')) {
            return [null, 'the mysql client is not installed'];
        }

        $command = 'mysql --no-defaults --protocol=socket -u root';
        $probe = self::viaClient($command, 'the mysql client, as root over the socket');

        try {
            $probe->run('SELECT 1');
        } catch (Throwable $e) {
            return [null, trim($e->getMessage())];
        }

        return [$probe, ''];
    }

    /**
     * @param list<int> $tolerate MySQL error numbers to accept as already-done
     */
    public function run(string $sql, array $tolerate = []): void
    {
        if ($this->link instanceof mysqli) {
            $this->runViaMysqli($sql, $tolerate);
            return;
        }

        $this->runViaClient($sql, $tolerate);
    }

    /**
     * First column of every row, as strings. Everything read here is an existence
     * check, so one column is all there has ever been to read.
     *
     * @return list<string>
     */
    public function scalars(string $sql): array
    {
        if ($this->link instanceof mysqli) {
            $result = $this->link->query($sql);
            if (!$result instanceof mysqli_result) {
                return [];
            }
            $values = [];
            foreach ($result as $row) {
                $values[] = (string) reset($row);
            }
            $result->free();
            return $values;
        }

        $output = $this->client($sql, ' --batch --raw --skip-column-names');
        $values = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (trim($line) !== '') {
                $values[] = explode("\t", $line)[0];
            }
        }
        return $values;
    }

    /**
     * @param list<int> $tolerate
     */
    private function runViaMysqli(string $sql, array $tolerate): void
    {
        $link = $this->link;
        if (!$link instanceof mysqli) {
            return;
        }

        try {
            // multi_query, so MySQL decides where each statement ends. A seed
            // that opens with SET SQL_MODE, START TRANSACTION and SET time_zone
            // does not survive being split anywhere else.
            $link->multi_query($sql);
            do {
                $result = $link->store_result();
                if ($result instanceof mysqli_result) {
                    $result->free();
                }
            } while ($link->more_results() && $link->next_result());
        } catch (Throwable $e) {
            if (!in_array((int) $e->getCode(), $tolerate, true)) {
                throw new RuntimeException(trim($e->getMessage()), (int) $e->getCode(), $e);
            }
        }
    }

    /**
     * @param list<int> $tolerate
     */
    private function runViaClient(string $sql, array $tolerate): void
    {
        try {
            $this->client($sql, '');
        } catch (RuntimeException $e) {
            // The client reports "ERROR 1050 (42S01) at line 12: ..."; the number
            // is the only part of it worth matching on.
            if (preg_match('/ERROR (\d+)/', $e->getMessage(), $match)
                && in_array((int) $match[1], $tolerate, true)) {
                return;
            }
            throw $e;
        }
    }

    /**
     * Feed SQL to the client through a file rather than -e, so that a statement
     * carrying the account's new password never appears in the process list.
     */
    private function client(string $sql, string $extraFlags): string
    {
        $sqlFile = tempnam(sys_get_temp_dir(), 'intelis_iface_');
        if ($sqlFile === false) {
            throw new RuntimeException('Could not create a temporary file to hold the SQL.');
        }
        @chmod($sqlFile, 0600);
        file_put_contents($sqlFile, $sql . ";\n");

        $errFile = tempnam(sys_get_temp_dir(), 'intelis_iface_err_');
        $command = $this->clientCommand . $extraFlags
            . ' < ' . escapeshellarg($sqlFile)
            . ' 2> ' . escapeshellarg((string) $errFile);

        $output = shell_exec($command);
        $stderr = $errFile === false ? '' : trim((string) @file_get_contents($errFile));

        MiscUtility::deleteFile($sqlFile);
        if ($errFile !== false) {
            MiscUtility::deleteFile($errFile);
        }

        if ($stderr !== '' && stripos($stderr, 'ERROR') !== false) {
            throw new RuntimeException($stderr);
        }

        return (string) $output;
    }
}

// ---------------------------------------------------------------------------
// Small helpers
// ---------------------------------------------------------------------------

/** A MySQL string literal. Backslash first, or it doubles the escapes it just added. */
function sqlString(string $value): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
}

function sqlIdentifier(string $value): string
{
    return '`' . str_replace('`', '``', $value) . '`';
}

/**
 * The account the web server runs as, judged by who owns the files it has to be
 * able to replace rather than by who exists. _www exists on every Mac and
 * www-data on hosts that serve nothing.
 */
function webAccountName(): ?string
{
    if (!function_exists('posix_getpwuid')) {
        return null;
    }

    foreach ([CACHE_PATH, VAR_PATH, ROOT_PATH] as $target) {
        if (!file_exists($target)) {
            continue;
        }
        $uid = @fileowner($target);
        if ($uid === false) {
            continue;
        }
        $owner = @posix_getpwuid($uid)['name'] ?? null;
        if ($owner === null) {
            continue;
        }

        // Owned by somebody else -- a developer's checkout. That is the answer;
        // do not keep walking up looking for a more convenient one.
        return in_array($owner, WEB_ACCOUNTS, true) ? $owner : null;
    }

    return null;
}

/** True when this process is already root, so nothing has to be elevated. */
function runningAsRoot(): bool
{
    return function_exists('posix_geteuid') && posix_geteuid() === 0;
}

/**
 * Can $account read $path?
 *
 * null means this process could not find out, which is emphatically not the same
 * answer as no: without root or passwordless sudo the test itself fails, and
 * reporting that as "the web server cannot read it" would send an operator off
 * to fix a permission that was never wrong.
 */
function readableBy(string $account, string $path): ?bool
{
    if (PHP_OS_FAMILY === 'Windows') {
        return null;
    }
    if (!runningAsRoot()) {
        @exec('sudo -n true 2>/dev/null', $ignored, $sudoWorks);
        if ($sudoWorks !== 0) {
            return null;
        }
    }

    @exec(
        'sudo -n -u ' . escapeshellarg($account) . ' test -r ' . escapeshellarg($path) . ' 2>/dev/null',
        $ignored,
        $status
    );

    return $status === 0;
}

/** Run a command as root, elevating only when this process is not root already. */
function runPrivileged(string $command): bool
{
    $status = 0;
    @exec((runningAsRoot() ? '' : 'sudo -n ') . $command . ' 2>/dev/null', $ignored, $status);

    return $status === 0;
}

/** This machine's address on the lab network, for the operator to type into the tool. */
function lanAddress(): ?string
{
    foreach (['hostname -I 2>/dev/null', "ip -4 -o route get 1.1.1.1 2>/dev/null | awk '{print \$7}'"] as $probe) {
        $output = trim((string) @shell_exec($probe));
        foreach (preg_split('/\s+/', $output) ?: [] as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                && !str_starts_with($candidate, '127.')) {
                return $candidate;
            }
        }
    }

    return null;
}

/** @return list<string> */
function discoverSqliteDatabases(): array
{
    // Where Electron puts userData. Same two places bin/interface-migrate.php
    // looks, so the two commands never disagree about which file is the one.
    $candidates = array_merge(
        glob('/home/*/.config/vlsm-interfacing/interface.db') ?: [],
        glob('/root/.config/vlsm-interfacing/interface.db') ?: [],
        glob(($_SERVER['HOME'] ?? '') . '/Library/Application Support/vlsm-interfacing/interface.db') ?: []
    );

    return array_values(array_unique(array_filter($candidates, 'is_readable')));
}

// ---------------------------------------------------------------------------

$exitCode = CLI\OK;

try {
    $input = new ArgvInput();
    $io = new SymfonyStyle($input, new ConsoleOutput());
    $prompt = new CliPromptUtility($io);

    /** @var ConfigService $configService */
    $configService = ContainerRegistry::get(ConfigService::class);
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    $io->title('Interfacing Setup');

    if (!CliPromptUtility::isInteractive()) {
        $io->error('Interfacing setup has questions to ask and there is nobody to answer them.');
        $io->text('Run it from a terminal: <info>intelis interface setup</info>');
        exit(CLI\ERROR);
    }

    // The one precondition worth stating rather than discovering halfway through.
    // Everything below writes to the application database or is decided by what
    // is in it, and a wizard that got as far as creating a MySQL account before
    // finding out it could not finish would leave the machine worse than it
    // found it.
    try {
        $db->connection('default')->rawQuery('SELECT 1');
    } catch (Throwable $e) {
        $io->error('The InteLIS database is not reachable, so setup cannot continue.');
        $io->text('Run <info>intelis fix-database</info> first, then come back to this.');
        exit(CLI\ERROR);
    }

    $configFile = $configService->getConfigFile();
    if (!is_writable($configFile)) {
        $io->error('Cannot write ' . $configFile);
        $io->text([
            'Setup finishes by saving these settings there, so there is no point starting.',
            'Run it again with administrator rights: <info>sudo intelis interface setup</info>',
        ]);
        exit(CLI\ERROR);
    }

    // --- what is configured now ------------------------------------------------

    $currentEnabled = !empty(SYSTEM_CONFIG['interfacing']['enabled']);
    $currentMysqlHost = (string) (SYSTEM_CONFIG['interfacing']['database']['host'] ?? '');
    $currentSqlitePath = (string) (SYSTEM_CONFIG['interfacing']['sqlite3Path'] ?? '');

    if ($currentEnabled && ($currentMysqlHost !== '' || $currentSqlitePath !== '')) {
        $io->section('What is configured now');
        $io->definitionList(
            ['Interfacing' => 'on'],
            ['MySQL' => $currentMysqlHost === ''
                ? 'not configured'
                : (string) (SYSTEM_CONFIG['interfacing']['database']['username'] ?? '') . '@' . $currentMysqlHost
                    . '/' . (string) (SYSTEM_CONFIG['interfacing']['database']['db'] ?? '')],
            ['SQLite file' => $currentSqlitePath === '' ? 'not configured' : $currentSqlitePath],
        );

        if (!$prompt->confirm('Set interfacing up again, replacing this?', false)) {
            $io->text('Nothing has been changed.');
            exit(CLI\OK);
        }
    }

    // --- step 1: where do results come from ------------------------------------

    $io->section('Where the results come from');

    $optionLocalMysql = 'MySQL on this machine — recommended, and the only path that carries instrument activity';
    $optionSqlite = "The tool's own SQLite file on this machine — simplest, results only";
    $optionRemoteMysql = 'MySQL on another server — advanced';

    $source = $prompt->choose(
        'How does the Interfacing Tool store the results InteLIS should read?',
        [$optionLocalMysql, $optionSqlite, $optionRemoteMysql],
        $optionLocalMysql
    );

    if ($source === null) {
        $io->text('Cancelled. Nothing has been changed.');
        exit(CLI\OK);
    }

    /** @var array<string, mixed> $configUpdates */
    $configUpdates = ['interfacing.enabled' => true];
    /** @var list<array{0: string, 1: string}> $toolSettings values to type into the Interfacing Tool */
    $toolSettings = [];
    $closingNotes = [];

    // =======================================================================
    // Path: the tool's SQLite file
    // =======================================================================

    if ($source === $optionSqlite) {
        $io->section('The tool\'s database file');

        $found = discoverSqliteDatabases();
        $sqlitePath = null;

        if ($found !== []) {
            $typeItIn = 'Somewhere else — I will type the path';
            $chosen = $prompt->choose('Which file?', [...$found, $typeItIn], $found[0]);
            if ($chosen === null) {
                $io->text('Cancelled. Nothing has been changed.');
                exit(CLI\OK);
            }
            if ($chosen !== $typeItIn) {
                $sqlitePath = $chosen;
            }
        } else {
            $io->warning('No interface.db was found in the usual place.');
            $io->text([
                'The file does not exist until the Interfacing Tool has run at least once,',
                'so if the tool has not been opened on this machine yet, do that first.',
            ]);
        }

        if ($sqlitePath === null) {
            $sqlitePath = $prompt->text(
                'Full path to interface.db',
                $currentSqlitePath,
                static function (string $value): ?string {
                    if ($value === '') {
                        return 'A path is needed.';
                    }
                    return file_exists($value) ? null : "There is no file at $value";
                },
                '/home/OPERATOR/.config/vlsm-interfacing/interface.db'
            );
        }

        if ($sqlitePath === null) {
            $io->text('Cancelled. Nothing has been changed.');
            exit(CLI\OK);
        }

        // InteLIS reads this file as the web server account, not as whoever is
        // running setup. Reading it here proves nothing about that, and a home
        // directory that only its owner can enter is the ordinary case rather
        // than the unusual one.
        $webAccount = webAccountName();
        if ($webAccount !== null && PHP_OS_FAMILY !== 'Windows') {
            $readable = readableBy($webAccount, $sqlitePath);

            // Traversal on the directories above the file, read on the file
            // itself. Loosening the file alone achieves nothing when a home
            // directory above it cannot be entered, and loosening the home
            // directory opens far more than one database.
            $directories = [];
            $walk = dirname($sqlitePath);
            while ($walk !== '/' && $walk !== '.' && $walk !== dirname($walk)) {
                $directories[] = $walk;
                $walk = dirname($walk);
            }
            $fix = array_map(
                static fn(string $dir): string => 'setfacl -m u:' . $webAccount . ':x ' . escapeshellarg($dir),
                array_reverse($directories)
            );
            $fix[] = 'setfacl -m u:' . $webAccount . ':r ' . escapeshellarg($sqlitePath);

            if ($readable === null) {
                $closingNotes[] = 'Setup could not check whether ' . $webAccount . ' can read that file.'
                    . ' Confirm it, because nothing arrives until it can:';
                $closingNotes[] = '  sudo -u ' . $webAccount . ' test -r '
                    . escapeshellarg($sqlitePath) . ' && echo readable';
            } elseif ($readable === false) {
                $io->warning("$webAccount cannot read that file, so InteLIS will find nothing in it.");

                $granted = false;
                if (CliPickerUtility::hasCommand('setfacl')
                    && $prompt->confirm("Grant $webAccount just enough access to read it?", true)) {
                    $granted = true;
                    foreach ($fix as $command) {
                        $granted = runPrivileged($command) && $granted;
                    }
                    // The commands reporting success is not the same as the file
                    // being readable -- an ACL on a filesystem mounted without
                    // acl support is accepted and does nothing. Ask the original
                    // question again rather than announcing a fix on faith.
                    $granted = $granted && readableBy($webAccount, $sqlitePath) === true;
                }

                if ($granted) {
                    $io->success('Access granted. Traversal on the directories above it, read on the file itself.');
                } else {
                    $closingNotes[] = 'Until ' . $webAccount . ' can read that file, no results will arrive. Run:';
                    foreach ($fix as $command) {
                        $closingNotes[] = '  sudo ' . $command;
                    }
                }
            }
        }

        $configUpdates['interfacing.sqlite3Path'] = $sqlitePath;

        // bin/interface.php reads a source because its settings are filled in,
        // not because anybody chose it. Leaving the MySQL block behind would
        // have it go on connecting to a database the operator has just replaced
        // with this file.
        if ($currentMysqlHost !== '') {
            $configUpdates['interfacing.database.host'] = '';
            $configUpdates['interfacing.database.username'] = '';
            $configUpdates['interfacing.database.password'] = '';
            $io->text('The MySQL settings that were there have been cleared; this file is the source now.');
        }

        $closingNotes[] = 'Instrument activity and daily usage reporting are not carried on this path.';
        $closingNotes[] = 'Point the tool at MySQL instead if the lab wants those.';
    }

    // =======================================================================
    // Path: MySQL, on this machine or another
    // =======================================================================

    if ($source === $optionLocalMysql || $source === $optionRemoteMysql) {
        $isLocal = $source === $optionLocalMysql;

        $io->section('The interfacing database');

        $dbHost = $isLocal ? '127.0.0.1' : '';
        if (!$isLocal) {
            $dbHost = $prompt->text(
                'Address of the server holding the interfacing database',
                $currentMysqlHost !== '' && $currentMysqlHost !== 'localhost' ? $currentMysqlHost : '',
                static fn(string $value): ?string => $value === '' ? 'An address is needed.' : null,
                '192.168.1.20'
            );
            if ($dbHost === null) {
                $io->text('Cancelled. Nothing has been changed.');
                exit(CLI\OK);
            }
        }

        $dbPort = $prompt->text('MySQL port', (string) (SYSTEM_CONFIG['interfacing']['database']['port'] ?? 3306), static function (string $value): ?string {
            return ctype_digit($value) && (int) $value > 0 && (int) $value <= 65535 ? null : 'A port is a number between 1 and 65535.';
        });
        if ($dbPort === null) {
            $io->text('Cancelled. Nothing has been changed.');
            exit(CLI\OK);
        }
        $dbPort = (int) $dbPort;

        $dbName = $prompt->text(
            'Database name',
            (string) (SYSTEM_CONFIG['interfacing']['database']['db'] ?? 'interfacing') ?: 'interfacing',
            static function (string $value): ?string {
                return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1
                    ? null
                    : 'Use letters, digits and underscores only.';
            }
        );
        if ($dbName === null) {
            $io->text('Cancelled. Nothing has been changed.');
            exit(CLI\OK);
        }

        $dbUser = '';
        $dbPassword = '';

        if (!$isLocal) {
            // Nothing to provision on a server this machine does not administer.
            // Collect what is already there and prove it works.
            $dbUser = $prompt->text('Username InteLIS should connect as', (string) (SYSTEM_CONFIG['interfacing']['database']['username'] ?? ''), static fn(string $v): ?string => $v === '' ? 'A username is needed.' : null);
            if ($dbUser === null) {
                $io->text('Cancelled. Nothing has been changed.');
                exit(CLI\OK);
            }
            $dbPassword = $prompt->password('Password for ' . $dbUser);
            if ($dbPassword === null) {
                $io->text('Cancelled. Nothing has been changed.');
                exit(CLI\OK);
            }
        } else {
            // --- where the tool runs, which decides how far the account reaches
            $io->section('Where the Interfacing Tool runs');

            $toolHere = 'On this machine';
            $toolElsewhere = 'On another computer on the lab network';
            $toolLocation = $prompt->choose(
                'Where is the Interfacing Tool installed?',
                [$toolHere, $toolElsewhere],
                $toolHere
            );
            if ($toolLocation === null) {
                $io->text('Cancelled. Nothing has been changed.');
                exit(CLI\OK);
            }

            $toolIsRemote = $toolLocation === $toolElsewhere;
            $toolAddress = null;

            if ($toolIsRemote) {
                $toolAddress = $prompt->text(
                    "Address of the computer running the tool (or % for any, which is wider than it needs to be)",
                    '',
                    static function (string $value): ?string {
                        if ($value === '%') {
                            return null;
                        }
                        return filter_var($value, FILTER_VALIDATE_IP) !== false
                            ? null
                            : 'An IP address, or % to allow any computer.';
                    },
                    '192.168.1.25'
                );
                if ($toolAddress === null) {
                    $io->text('Cancelled. Nothing has been changed.');
                    exit(CLI\OK);
                }
            }

            // --- an administrative way into MySQL ---------------------------
            $io->section('Creating the database and the account');

            $admin = null;
            $attempts = [];

            $lisDb = SYSTEM_CONFIG['database'] ?? [];
            if (!empty($lisDb['username'])) {
                [$admin, $why] = InterfaceDbAdmin::tryMysqli(
                    '127.0.0.1',
                    (int) ($lisDb['port'] ?? 3306),
                    (string) $lisDb['username'],
                    (string) ($lisDb['password'] ?? ''),
                    "InteLIS's own MySQL account (" . $lisDb['username'] . ')'
                );
                if ($admin === null) {
                    $attempts[] = "InteLIS's own MySQL account: $why";
                }
            }

            if ($admin === null) {
                [$admin, $why] = InterfaceDbAdmin::tryRootClient();
                if ($admin === null) {
                    $attempts[] = "root over the unix socket: $why";
                }
            }

            while ($admin === null) {
                $io->warning('No account was found that can create a database here.');
                foreach ($attempts as $attempt) {
                    $io->text('  · ' . $attempt);
                }
                $attempts = [];

                if (!$prompt->confirm('Enter a MySQL administrator account to use?', true)) {
                    $io->error('Setup needs an account that can CREATE DATABASE and CREATE USER.');
                    $io->text('Try again with <info>sudo intelis interface setup</info>, which can use root over the socket.');
                    exit(CLI\ERROR);
                }

                $adminUser = $prompt->text('MySQL administrator username', 'root', static fn(string $v): ?string => $v === '' ? 'A username is needed.' : null);
                if ($adminUser === null) {
                    $io->text('Cancelled. Nothing has been changed.');
                    exit(CLI\OK);
                }
                $adminPassword = $prompt->password("Password for $adminUser", true);
                if ($adminPassword === null) {
                    $io->text('Cancelled. Nothing has been changed.');
                    exit(CLI\OK);
                }

                [$admin, $why] = InterfaceDbAdmin::tryMysqli('127.0.0.1', $dbPort, $adminUser, $adminPassword, "$adminUser@127.0.0.1");
                if ($admin === null) {
                    $attempts[] = "$adminUser: $why";
                }
            }

            $io->text('Connected as ' . $admin->description . '.');

            // --- the database ------------------------------------------------
            $admin->run('CREATE DATABASE IF NOT EXISTS ' . sqlIdentifier($dbName)
                . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            $hasOrders = $admin->scalars(
                'SELECT 1 FROM information_schema.TABLES'
                . ' WHERE TABLE_SCHEMA = ' . sqlString($dbName) . " AND TABLE_NAME = 'orders'"
            ) !== [];

            if ($hasOrders) {
                $io->text("Database $dbName already has the tool's tables; leaving them alone.");
                $closingNotes[] = 'If the tool is newer than this database, top it up with: intelis interface-migrate';
            } else {
                $seedPath = ROOT_PATH . '/sql/interface-init.sql';
                if (!is_readable($seedPath)) {
                    $io->error('sql/interface-init.sql is missing, so the tables cannot be created.');
                    exit(CLI\ERROR);
                }

                // The seed names its own database in a CREATE DATABASE and a USE.
                // Point both at the one that was actually asked for, or the tables
                // land in a database nobody chose.
                $seed = (string) file_get_contents($seedPath);
                $seed = preg_replace(
                    '/CREATE\s+DATABASE\s+(IF\s+NOT\s+EXISTS\s+)?`?interfacing`?/i',
                    'CREATE DATABASE IF NOT EXISTS ' . sqlIdentifier($dbName),
                    $seed
                ) ?? $seed;
                $seed = preg_replace(
                    '/^\s*USE\s+`?interfacing`?\s*;/im',
                    'USE ' . sqlIdentifier($dbName) . ';',
                    $seed
                ) ?? $seed;

                // No tolerated errors. This only runs on a database with no
                // orders table -- a fresh one -- where every statement in the
                // seed is running for the first time and nothing it hits is
                // benign. Tolerating one would be worse than failing: the batch
                // stops at the first error either way, so a tolerated error is a
                // half-built schema reported as a success.
                $admin->run($seed);

                $built = $admin->scalars(
                    'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ' . sqlString($dbName)
                );
                $missing = array_diff(SEEDED_TABLES, $built);
                if ($missing !== []) {
                    $io->error('The database was created but these tables were not: ' . implode(', ', $missing));
                    $io->text('Nothing has been written to the configuration. The error above says what stopped it.');
                    exit(CLI\ERROR);
                }

                $io->success("Created the database $dbName and the tables the tool writes to.");
            }

            // --- the account -------------------------------------------------
            // Whatever name is settled on here has its password set below, so a
            // name that belongs to something else is not a naming mistake but a
            // lockout: 'root' resets the server's root password, and the LIS's
            // own account takes InteLIS itself off the air. Those two are
            // refused outright; any other account that already exists is a
            // question rather than a refusal, because re-running this wizard to
            // reset a forgotten interfacing password is a legitimate reason to
            // be here.
            $reservedAccounts = array_filter([
                'root',
                'mysql.sys',
                'mysql.session',
                'mysql.infoschema',
                'debian-sys-maint',
                strtolower((string) (SYSTEM_CONFIG['database']['username'] ?? '')),
            ]);

            $configuredUser = strtolower((string) (SYSTEM_CONFIG['interfacing']['database']['username'] ?? ''));
            $suggestedUser = $configuredUser !== '' && !in_array($configuredUser, $reservedAccounts, true)
                ? $configuredUser
                : 'interfacing';

            $dbUser = null;
            while ($dbUser === null) {
                $dbUser = $prompt->text(
                    'Name for the account the tool and InteLIS will use',
                    $suggestedUser,
                    static function (string $value) use ($reservedAccounts): ?string {
                        if (preg_match('/^[A-Za-z0-9_.-]{1,32}$/', $value) !== 1) {
                            return 'Use letters, digits, underscores, dots and hyphens, up to 32 characters.';
                        }
                        if (in_array(strtolower($value), $reservedAccounts, true)) {
                            return "Not $value — this sets the account's password, and that one is in use elsewhere."
                                . ' Pick a name of its own, such as interfacing.';
                        }
                        return null;
                    }
                );
                if ($dbUser === null) {
                    $io->text('Cancelled. Nothing has been changed.');
                    exit(CLI\OK);
                }

                // Not every administrative account can read mysql.user. Being
                // unable to look is not a reason to stop -- CREATE USER IF NOT
                // EXISTS below is safe either way -- so an unreadable table just
                // means the question does not get asked.
                try {
                    $existing = $admin->scalars(
                        'SELECT CONCAT(user, ' . sqlString('@') . ', host) FROM mysql.user'
                        . ' WHERE user = ' . sqlString($dbUser)
                    );
                } catch (Throwable) {
                    $existing = [];
                }
                if ($existing !== []) {
                    $io->warning("A MySQL account called $dbUser already exists: " . implode(', ', $existing));
                    if (!$prompt->confirm('Reuse it, and set its password to the one chosen next?', false)) {
                        $dbUser = null;
                        $suggestedUser = '';
                    }
                }
            }

            $generated = MiscUtility::generateRandomString(24);
            $useGenerated = 'Use a generated password (recommended)';
            $typeMyOwn = 'Type a password myself';
            $passwordChoice = $prompt->choose('Password for that account', [$useGenerated, $typeMyOwn], $useGenerated);
            if ($passwordChoice === null) {
                $io->text('Cancelled. Nothing has been changed.');
                exit(CLI\OK);
            }

            if ($passwordChoice === $useGenerated) {
                $dbPassword = $generated;
            } else {
                $dbPassword = $prompt->password('Password');
                if ($dbPassword === null) {
                    $io->text('Cancelled. Nothing has been changed.');
                    exit(CLI\OK);
                }
            }

            // Two grants for one account, on purpose. InteLIS connects from this
            // machine and the tool connects from wherever it is, and MySQL treats
            // 'interfacing'@'localhost' and 'interfacing'@'192.168.1.25' as two
            // different accounts. Creating only the tool's leaves InteLIS locked
            // out of the database it was just told to read.
            $grantHosts = ['localhost', '127.0.0.1'];
            if ($toolIsRemote && $toolAddress !== null) {
                $grantHosts[] = $toolAddress;
            }

            foreach ($grantHosts as $grantHost) {
                $account = sqlString($dbUser) . '@' . sqlString($grantHost);

                $admin->run("CREATE USER IF NOT EXISTS $account IDENTIFIED BY " . sqlString($dbPassword));

                // The tool is an Electron application whose MySQL client cannot
                // speak caching_sha2_password, which is MySQL 8's default -- an
                // account left on the default authenticates fine from here and is
                // refused from the tool, with an error that says nothing about
                // why. Ask for the older plugin, and accept a server that has
                // removed it (8.4 and later) rather than failing over a
                // preference.
                try {
                    $admin->run("ALTER USER $account IDENTIFIED WITH mysql_native_password BY " . sqlString($dbPassword));
                } catch (Throwable) {
                    $admin->run("ALTER USER $account IDENTIFIED BY " . sqlString($dbPassword));
                    $closingNotes[] = 'This MySQL no longer offers mysql_native_password. If the tool cannot connect,'
                        . ' that is why, and the tool needs a build with a newer MySQL client.';
                }

                // Scoped to this one database, and wide within it because the
                // tool's own migrations add columns to its tables.
                $admin->run('GRANT ALL PRIVILEGES ON ' . sqlIdentifier($dbName) . ".* TO $account");
            }

            $admin->run('FLUSH PRIVILEGES');
            $io->success("Account $dbUser is ready, reaching " . implode(', ', $grantHosts) . '.');

            // --- letting the tool's machine in --------------------------------
            if ($toolIsRemote) {
                $io->section('Reaching this machine from the tool');

                $bindAddress = trim((string) @shell_exec(
                    "grep -rhs '^ *bind-address' /etc/mysql/ /etc/my.cnf /etc/my.cnf.d/ 2>/dev/null | head -1"
                ));
                $bindsLocalOnly = $bindAddress !== '' && (
                    str_contains($bindAddress, '127.0.0.1') || str_contains($bindAddress, '::1')
                );

                if ($bindsLocalOnly) {
                    $io->warning('MySQL is listening on this machine only (' . trim($bindAddress) . ').');
                    $io->text([
                        'The tool cannot reach it from another computer until that changes.',
                        'The address to bind to is this machine\'s own on the lab network — not 0.0.0.0,',
                        'which offers the database to every network this machine is attached to.',
                    ]);
                    $closingNotes[] = 'Set bind-address in /etc/mysql/mysql.conf.d/mysqld.cnf to '
                        . (lanAddress() ?? "this machine's lab-network address")
                        . ', then: sudo systemctl restart mysql';
                }

                if (CliPickerUtility::hasCommand('ufw')) {
                    $ufwStatus = (string) @shell_exec('ufw status 2>/dev/null');
                    $firewallActive = stripos($ufwStatus, 'Status: active') !== false;
                    $portOpen = str_contains($ufwStatus, '3306') || str_contains($ufwStatus, (string) $dbPort);

                    if ($firewallActive && !$portOpen) {
                        $from = $toolAddress === '%' ? 'any' : $toolAddress;
                        $rule = 'ufw allow from ' . $from . ' to any port ' . $dbPort . ' proto tcp';

                        $io->warning("The firewall is on and port $dbPort is closed.");
                        if ($toolAddress === '%') {
                            $io->text('Opening it to any address is wider than a lab needs. An address here would be better.');
                        }

                        if ($prompt->confirm("Open port $dbPort to $from?", $toolAddress !== '%')) {
                            if (runPrivileged($rule)) {
                                $io->success('Firewall rule added: ' . $rule);
                            } else {
                                $io->warning('That rule could not be added from here.');
                                $closingNotes[] = 'Port ' . $dbPort . ' is still closed. Run: sudo ' . $rule;
                            }
                        } else {
                            $closingNotes[] = 'Port ' . $dbPort . ' is closed. When you want it open: sudo ' . $rule;
                        }
                    }
                }
            }

            $toolSettings = [
                ['Host', ($toolIsRemote ? (lanAddress() ?? 'this machine\'s lab-network address') : '127.0.0.1')],
                ['Port', (string) $dbPort],
                ['Database', $dbName],
                ['Username', $dbUser],
                ['Password', $dbPassword],
            ];
        }

        // --- prove InteLIS itself can connect ---------------------------------
        $io->section('Checking the connection');

        [$check, $why] = InterfaceDbAdmin::tryMysqli($dbHost, $dbPort, $dbUser, $dbPassword, 'the interfacing account');
        if ($check === null) {
            $io->error("InteLIS cannot connect to $dbUser@$dbHost:$dbPort — $why");
            $io->text('Nothing has been written to the configuration, so the machine is as it was.');
            exit(CLI\ERROR);
        }

        $tables = $check->scalars(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ' . sqlString($dbName)
        );
        if (!in_array('orders', $tables, true)) {
            $io->error("Connected, but $dbName has no 'orders' table, so there is nothing for InteLIS to read.");
            $io->text('On a database the tool has never written to, open the tool once and let it create its tables.');
            exit(CLI\ERROR);
        }

        $io->success("Connected to $dbName as $dbUser, and its 'orders' table is there.");

        $configUpdates['interfacing.database.host'] = $dbHost;
        $configUpdates['interfacing.database.username'] = $dbUser;
        $configUpdates['interfacing.database.password'] = $dbPassword;
        $configUpdates['interfacing.database.db'] = $dbName;
        $configUpdates['interfacing.database.port'] = $dbPort;
        $configUpdates['interfacing.database.charset'] = 'utf8mb4';

        // Same reasoning the other way round. A SQLite path left behind is read
        // as a second source, and every result in it arrives twice.
        if ($currentSqlitePath !== '') {
            $configUpdates['interfacing.sqlite3Path'] = '';
            $io->text('The SQLite file that was configured has been cleared; this database is the source now.');
        }
    }

    // --- save ------------------------------------------------------------------

    $configService->updateConfig($configUpdates);
    $io->success('Saved to ' . $configFile . ', and interfacing is now switched on.');

    if ($toolSettings !== []) {
        $io->section('Enter these in the Interfacing Tool');
        $io->text('Its MySQL settings, exactly as printed:');
        $io->table(['Setting', 'Value'], $toolSettings);
        $io->text('The password is also in the configuration file, if it is needed again later.');
    }

    // Deduplicated because several of these are raised per grant host, and the
    // same sentence three times reads as three different problems.
    $closingNotes = array_values(array_unique($closingNotes));
    if ($closingNotes !== []) {
        $io->section('Still to do');
        $io->listing($closingNotes);
    }

    $io->section('What happens next');
    $io->text([
        'Results are imported every minute by the scheduler, which is already running',
        'if <info>sudo crontab -l | grep crunz</info> shows a line.',
        '',
        'To pull whatever is waiting right now: <info>intelis interface</info>',
    ]);
} catch (Throwable $e) {
    $exitCode = CLI\ERROR;
    if (isset($io)) {
        $io->error($e->getMessage());
    } else {
        echo 'Interfacing setup failed: ' . $e->getMessage() . PHP_EOL;
    }
}

exit($exitCode);

#!/usr/bin/env php
<?php

// bin/db-tools.php - Database management CLI tool for InteLIS

if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . '/../bootstrap.php';

@set_time_limit(0);
@ignore_user_abort(true);
@ini_set('memory_limit', '-1');

// Flush output as we print
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
}
@ob_implicit_flush(true);

// Optional: better signal handling if available
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
}

use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\SystemService;
use Ifsnop\Mysqldump as IMysqldump;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\ProgressBar;


const DEFAULT_OPERATION = 'backup';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$backupFolder = APPLICATION_PATH . '/../backups/db';
if (!is_dir($backupFolder)) {
    MiscUtility::makeDirectory($backupFolder);
}
$backupFolder = realpath($backupFolder) ?: $backupFolder;
$backupFolder = rtrim($backupFolder, DIRECTORY_SEPARATOR);

$arguments = $_SERVER['argv'] ?? [];
array_shift($arguments); // remove script name
$command = strtolower($arguments[0] ?? DEFAULT_OPERATION);
$commandArgs = array_slice($arguments, 1);

$intelisDbConfig = SYSTEM_CONFIG['database'];
$interfacingEnabled = SYSTEM_CONFIG['interfacing']['enabled'] ?? false;
$interfacingDbConfig = $interfacingEnabled ? SYSTEM_CONFIG['interfacing']['database'] : null;
validateConfiguration($intelisDbConfig, $interfacingDbConfig);
try {
    switch ($command) {
        case 'backup':
            handleBackup($backupFolder, $intelisDbConfig, $interfacingDbConfig, $commandArgs);
            exit(0);
        case 'export':
            handleExport($backupFolder, $intelisDbConfig, $interfacingDbConfig, $commandArgs);
            exit(0);
        case 'import':
            handleImport($backupFolder, $intelisDbConfig, $interfacingDbConfig, $commandArgs);
            exit(0);
        case 'list':
            handleList($backupFolder);
            exit(0);
        case 'restore':
            handleRestore($backupFolder, $intelisDbConfig, $interfacingDbConfig, $commandArgs[0] ?? null);
            exit(0);
        case 'verify':
            handleVerify($backupFolder, $commandArgs);
            exit(0);
        case 'clean':
            handleClean($backupFolder, $commandArgs);
            exit(0);
        case 'size':
            handleSize($intelisDbConfig, $interfacingDbConfig, $commandArgs);
            exit(0);
        case 'config-test':
            handleConfigTest($backupFolder, $intelisDbConfig, $interfacingDbConfig, $commandArgs);
            exit(0);
        case 'mysqlcheck':
            handleMysqlCheck($intelisDbConfig, $interfacingDbConfig, $commandArgs);
            exit(0);
        case 'purge-binlogs':
        case 'purge-binlog':
            handlePurgeBinlogs($intelisDbConfig, $interfacingDbConfig, $commandArgs);
            exit(0);
        case 'pitr-info':
            handlePitrInfo($backupFolder, $intelisDbConfig, $interfacingDbConfig, $commandArgs);
            exit(0);
        case 'pitr-restore':
            handlePitrRestore($backupFolder, $intelisDbConfig, $interfacingDbConfig, $commandArgs);
            exit(0);
        case 'maintain':
            handleMaintain($intelisDbConfig, $interfacingDbConfig, $commandArgs);
            exit(0);
        case 'collation':
            $cmd = sprintf('%s %s/setup/change-db-collation.php', PHP_BINARY, BIN_PATH);
            passthru($cmd);
            break;
        case 'help':
        case '--help':
        case '-h':
            printUsage();
            exit(0);
        default:
            echo "Unknown command: {$command}\n\n";
            printUsage();
            exit(1);
    }
} catch (\Throwable $e) {
    handleUserFriendlyError($e);
    exit(1);
}

function isGpgGzFile(string $path): bool
{
    return str_ends_with(strtolower($path), '.sql.gz.gpg');
}

function getTempDir(string $backupFolder): string
{
    $tempDir = $backupFolder . DIRECTORY_SEPARATOR . '.tmp';
    if (!is_dir($tempDir)) {
        MiscUtility::makeDirectory($tempDir);
    }
    return $tempDir;
}

/**
 * Best-effort detection of available CPU threads.
 * Respects containers (nproc), macOS (sysctl), Windows env, with sane fallbacks.
 * Allows override via env PIGZ_THREADS.
 */
function getAvailableCpuThreads(): int
{
    // Env override first (lets you tune in prod quickly)
    $override = getenv('PIGZ_THREADS');
    if (is_numeric($override) && (int)$override > 0) {
        return max(1, (int)$override);
    }

    // 1) Linux / containers: nproc (respects cpuset/cgroup)
    $threads = null;
    $out = @shell_exec('nproc 2>/dev/null');
    if (is_string($out) && ($n = (int)trim($out)) > 0) {
        $threads = $n;
    }

    // 2) POSIX fallback
    if ($threads === null) {
        $out = @shell_exec('getconf _NPROCESSORS_ONLN 2>/dev/null');
        if (is_string($out) && ($n = (int)trim($out)) > 0) {
            $threads = $n;
        }
    }

    // 3) macOS
    if ($threads === null) {
        $out = @shell_exec('sysctl -n hw.logicalcpu 2>/dev/null');
        if (is_string($out) && ($n = (int)trim($out)) > 0) {
            $threads = $n;
        }
    }

    // 4) Windows
    if ($threads === null) {
        $n = (int)(getenv('NUMBER_OF_PROCESSORS') ?: 0);
        if ($n > 0) $threads = $n;
    }

    // 5) last resort
    if ($threads === null || $threads < 1) {
        $threads = 1;
    }

    // Optional: cap to something reasonable if you like
    return max(1, $threads);
}


/**
 * Compress a .sql to .sql.gz using pigz (parallel gzip).
 */
function compressWithPigz(string $sqlPath, string $gzPath): bool
{
    if (!is_file($sqlPath)) return false;
    if (!commandExists('pigz')) {
        throw new SystemException('pigz not found. Please install pigz or adjust PATH.');
    }

    $threads = getAvailableCpuThreads();

    // pigz -p <threads> -c input.sql > output.sql.gz
    $cmd = ['pigz', '-p', (string)$threads, '-c', $sqlPath];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $gzPath, 'wb'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($cmd, $descriptors, $pipes, null, buildProcessEnv());
    if (!is_resource($proc)) {
        throw new SystemException('Failed to start pigz process.');
    }

    if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);

    $stderr = '';
    if (isset($pipes[2]) && is_resource($pipes[2])) {
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
    }

    $exit = proc_close($proc);

    if ($exit !== 0) {
        @unlink($gzPath);
        $msg = trim($stderr) ?: 'pigz exited with a non-zero status.';
        throw new SystemException("Failed to compress SQL with pigz: {$msg}");
    }

    return is_file($gzPath) && filesize($gzPath) > 0;
}



/**
 * Encrypt a .gz to .gz.gpg (symmetric AES-256).
 */
function encryptWithGpg(string $gzPath, string $gpgPath, string $passphrase): bool
{
    if (!is_file($gzPath)) return false;
    if (!commandExists('gpg')) {
        throw new SystemException('gpg not found. Please install GnuPG or adjust PATH.');
    }

    $cmd = [
        'gpg',
        '--batch',
        '--yes',
        '--pinentry-mode',
        'loopback',
        '--passphrase',
        $passphrase,
        '--symmetric',
        '--cipher-algo',
        'AES256',
        '--output',
        $gpgPath,
        $gzPath
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($cmd, $descriptors, $pipes, null, buildProcessEnv());
    if (!is_resource($proc)) {
        throw new SystemException('Failed to start gpg process.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    if ($exit !== 0) {
        @unlink($gpgPath);
        $msg = trim($stderr) ?: trim($stdout) ?: 'Unknown GPG error';
        throw new SystemException("GPG encryption failed: {$msg}");
    }

    return is_file($gpgPath) && filesize($gpgPath) > 0;
}

/**
 * Decrypt *.sql.gz.gpg and output a temp *.sql file.
 */
function decryptGpgToTempSql(string $gpgPath, string $passphrase, string $backupFolder): string
{
    if (!is_file($gpgPath)) {
        throw new SystemException('Backup file not found for GPG decrypt.');
    }
    if (!commandExists('gpg')) {
        throw new SystemException('gpg not found. Please install GnuPG or adjust PATH.');
    }
    if (!commandExists('gunzip')) {
        throw new SystemException('gunzip not found. Please install gzip or adjust PATH.');
    }

    $tempDir = getTempDir($backupFolder);
    $base    = basename($gpgPath, '.gpg');          // *.sql.gz
    $outSql  = $tempDir . DIRECTORY_SEPARATOR . basename($base, '.gz'); // *.sql

    // gpg -d | gunzip > out.sql
    $gpgCmd = [
        'gpg',
        '--batch',
        '--yes',
        '--pinentry-mode',
        'loopback',
        '--passphrase',
        $passphrase,
        '--decrypt',
        $gpgPath
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'], // gpg stdout -> we'll pipe to gunzip
        2 => ['pipe', 'w'],
    ];
    $procGpg = proc_open($gpgCmd, $descriptors, $pipesGpg, null, buildProcessEnv());
    if (!is_resource($procGpg)) {
        throw new SystemException('Failed to start gpg decrypt process.');
    }

    $gunzipCmd = ['gunzip', '-c'];
    $desc2 = [
        0 => ['pipe', 'r'],
        1 => ['file', $outSql, 'wb'],
        2 => ['pipe', 'w'],
    ];
    $procGunzip = proc_open($gunzipCmd, $desc2, $pipesGz, null, buildProcessEnv());
    if (!is_resource($procGunzip)) {
        proc_close($procGpg);
        throw new SystemException('Failed to start gunzip process.');
    }

    // pipe: gpg stdout -> gunzip stdin
    stream_set_blocking($pipesGpg[1], true);
    stream_set_blocking($pipesGz[0], true);
    while (!feof($pipesGpg[1])) {
        $buf = fread($pipesGpg[1], 8192);
        if ($buf === false) break;
        fwrite($pipesGz[0], $buf);
    }

    fclose($pipesGpg[1]);
    fclose($pipesGpg[0]);
    fclose($pipesGpg[2] ?? null);
    fclose($pipesGz[0]);
    fclose($pipesGz[2] ?? null);

    $exitGpg    = proc_close($procGpg);
    $exitGunzip = proc_close($procGunzip);

    if ($exitGpg !== 0 || $exitGunzip !== 0 || !is_file($outSql) || filesize($outSql) === 0) {
        @unlink($outSql);
        throw new SystemException('Failed to decrypt/decompress GPG backup (wrong passphrase or corrupted file).');
    }

    return $outSql;
}

/**
 * Quick structural check that file is a readable GPG container (no passphrase needed).
 */
function verifyGpgStructure(string $gpgPath): bool
{
    if (!commandExists('gpg')) return false;
    $cmd = ['gpg', '--batch', '--list-packets', $gpgPath];

    $desc = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $desc, $pipes, null, buildProcessEnv());
    if (!is_resource($proc)) return false;

    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]) ?: '';
    $err = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    // Many gpg 2.4 builds emit non-zero exit even for readable packets.
    $text = strtolower($out . "\n" . $err);
    return (
        str_contains($text, 'encrypted data packet') ||
        str_contains($text, 'symkey enc packet') ||
        str_contains($text, 'aes256')
    );
}



function console(): ConsoleOutput
{
    static $out = null;
    if (!$out) $out = new ConsoleOutput();
    return $out;
}

/** Indeterminate spinner that we can advance from hooks */
function startSpinner(?string $label = null): ProgressBar
{
    $out = console();
    $bar = new ProgressBar($out);
    // Symfony has a "spinner" style by setting max steps = 0 and custom format
    $bar->setFormat("%message%\n [%bar%] %elapsed:6s%  %memory:6s%");
    $bar->setBarCharacter("<fg=green>●</>");
    $bar->setEmptyBarCharacter(" ");
    $bar->setProgressCharacter("<fg=green>●</>");
    if ($label) $bar->setMessage($label);
    // Use unknown steps (indeterminate)
    $bar->start();
    return $bar;
}


function getAppTimezone(): string
{
    /** @var SystemService $system */
    $system = ContainerRegistry::get(SystemService::class);
    try {
        return $system->getTimezone() ?: 'UTC';
    } catch (\Throwable $e) {
        return 'UTC';
    }
}

function collectBinlogSnapshot(array $config): array
{
    // Works whether GTID is ON or OFF
    $vars = [
        "SELECT @@global.gtid_mode AS gtid_mode",
        "SELECT @@global.enforce_gtid_consistency AS enforce_gtid_consistency",
        "SELECT @@global.gtid_executed AS gtid_executed",
        "SHOW VARIABLES LIKE 'log_bin'",
        "SHOW VARIABLES LIKE 'log_bin_basename'",
        "SHOW VARIABLES LIKE 'binlog_format'",
        "SHOW VARIABLES LIKE 'binlog_row_image'",
        "SHOW VARIABLES LIKE 'binlog_expire_logs_seconds'",
        "SHOW VARIABLES LIKE 'expire_logs_days'",
        "SELECT @@server_uuid AS server_uuid",
    ];

    $out = [
        'ts_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'app_tz' => getAppTimezone(),
        'server' => [
            'uuid' => null,
            'log_bin' => null,
            'log_bin_basename' => null,
            'binlog_format' => null,
            'binlog_row_image' => null,
            'binlog_expire_logs_seconds' => null,
            'expire_logs_days' => null,
            'gtid_mode' => null,
            'enforce_gtid_consistency' => null,
            'gtid_executed' => null,
        ],
        'master_status' => [
            'file' => null,
            'position' => null,
            'gtid_executed' => null,
        ],
    ];

    // Fetch variables
    foreach ($vars as $sql) {
        try {
            $res = runMysqlQuery($config, $sql);
            $res = trim($res);
            if ($res === '') continue;

            if (stripos($sql, 'SHOW VARIABLES LIKE') === 0) {
                // format: Variable_name\tValue
                foreach (explode("\n", $res) as $line) {
                    $parts = preg_split('/\s+/', trim($line));
                    if (count($parts) >= 2) {
                        $k = strtolower($parts[0]);
                        $v = $parts[1] ?? null;
                        if ($k === 'log_bin') $out['server']['log_bin'] = $v;
                        if ($k === 'log_bin_basename') $out['server']['log_bin_basename'] = $v;
                        if ($k === 'binlog_format') $out['server']['binlog_format'] = $v;
                        if ($k === 'binlog_row_image') $out['server']['binlog_row_image'] = $v;
                        if ($k === 'binlog_expire_logs_seconds') $out['server']['binlog_expire_logs_seconds'] = $v;
                        if ($k === 'expire_logs_days') $out['server']['expire_logs_days'] = $v;
                    }
                }
            } elseif (stripos($sql, 'SELECT @@server_uuid') === 0) {
                $out['server']['uuid'] = $res;
            } elseif (stripos($sql, 'SELECT @@global.gtid_mode') === 0) {
                $out['server']['gtid_mode'] = $res;
            } elseif (stripos($sql, 'SELECT @@global.enforce_gtid_consistency') === 0) {
                $out['server']['enforce_gtid_consistency'] = $res;
            } elseif (stripos($sql, 'SELECT @@global.gtid_executed') === 0) {
                $out['server']['gtid_executed'] = $res;
            }
        } catch (\Throwable $e) {
            // non-fatal; leave nulls
        }
    }

    // SHOW MASTER STATUS works both with/without GTID; returns File, Position, Binlog_Do_DB, Binlog_Ignore_DB, Executed_Gtid_Set
    try {
        $master = runMysqlQuery($config, "SHOW MASTER STATUS");
        $lines = array_filter(array_map('trim', explode("\n", trim($master))));
        if (!empty($lines)) {
            // Expect: File\tPosition\t...\tExecuted_Gtid_Set
            $parts = preg_split('/\s+/', $lines[0]);
            // guard
            if (count($parts) >= 2) {
                $out['master_status']['file'] = $parts[0] ?? null;
                $out['master_status']['position'] = isset($parts[1]) ? (int)$parts[1] : null;
                // try to find a GTID set; it’s often last column or empty
                $last = end($parts);
                if ($last && stripos($last, ':') !== false) {
                    $out['master_status']['gtid_executed'] = $last;
                }
            }
        }
    } catch (\Throwable $e) {
        // leave nulls
    }

    return $out;
}


function printUsage(): void
{
    $script = basename(__FILE__);
    echo <<<USAGE
Usage: php bin/{$script} [command] [options]

Commands:
    backup [target]         Create encrypted backup(s) (default: both if interfacing enabled, intelis otherwise)
    export <target> [file]  Export database as plain SQL file
    import <target> [file]  Import SQL file to database (supports .sql, .sql.gz, .zip, and .sql.zip)
    list                    List available backups and SQL files
    restore [file]          Restore encrypted backup; shows selection if no file specified
    verify [file]           Verify backup integrity without restoring
    clean <--keep=N | --days=N>
                            Delete old backups (keep N recent OR keep newer than N days)
    size [target]           Show database size and table breakdown
    mysqlcheck [target]     Run database maintenance for the selected database(s)
    purge-binlogs [target] [--days=N]
                            Clean up binary logs older than N days (default 7)
    maintain [target] [--days=N]
                            Run full maintenance (mysqlcheck + purge binlogs)
    collation               Launch DB collation conversion utility
    help                    Show this help message

Target options:
    intelis                 InteLIS/VLSM database (default)
    interfacing             Interfacing database
    both, all               Both databases (backup/mysqlcheck/purge-binlogs only)

Options:
    --skip-safety-backup    Skip creating safety backup before restore/import (faster but risky)

Examples:
    php bin/{$script} backup intelis
    php bin/{$script} verify
    php bin/{$script} clean --keep=7
    php bin/{$script} clean --days=30
    php bin/{$script} export intelis vlsm_backup.sql
    php bin/{$script} import interfacing
    php bin/{$script} restore
    php bin/{$script} restore --skip-safety-backup
    php bin/{$script} import intelis mydata.sql --skip-safety-backup
    php bin/{$script} maintain both

Note: For security, all file operations are restricted to the backups directory.
USAGE;
}

function validateConfiguration(array $intelisDbConfig, ?array $interfacingDbConfig): void
{
    $required = ['host', 'username', 'db'];

    foreach ($required as $field) {
        if (empty($intelisDbConfig[$field])) {
            throw new SystemException("InteLIS database configuration missing: {$field}");
        }
    }

    if ($interfacingDbConfig) {
        foreach ($required as $field) {
            if (empty($interfacingDbConfig[$field])) {
                throw new SystemException("Interfacing database configuration missing: {$field}");
            }
        }
    }
}

function verifyBackupIntegrity(string $zipPath): bool
{
    $zip = new ZipArchive();
    $result = $zip->open($zipPath, ZipArchive::CHECKCONS);

    if ($result !== true) {
        return false;
    }

    $zip->close();
    return true;
}

// Command Handlers

function handleBackup(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $args): void
{
    $targetOption = extractTargetOption($args);
    $targets = resolveBackupTargets($targetOption, $intelisDbConfig, $interfacingDbConfig);

    foreach ($targets as $target) {
        echo PHP_EOL . "Creating {$target['label']} database backup...this can take a while on large dataset" . PHP_EOL;
        $t0 = microtime(true);
        $zip = createBackupArchive(
            $prefix = $target['label'] === 'interfacing' ? 'interfacing' : 'intelis',
            $target['config'],
            $backupFolder
        );
        $secs = max(0.0, microtime(true) - $t0);
        $size = formatFileSize(@filesize($zip) ?: 0);
        echo PHP_EOL . "Created: " . basename($zip) . "  ({$size}, " . number_format($secs, 1) . "s)\n" . PHP_EOL;
    }
}

function handleExport(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $args): void
{
    if (empty($args)) {
        throw new SystemException('Export target is required. Use: intelis|interfacing');
    }

    $targetOption = $args[0];
    $outputFile = $args[1] ?? null;

    $config = resolveTargetConfig($targetOption, $intelisDbConfig, $interfacingDbConfig);
    $label = normalizeTargetLabel($targetOption);

    if (!$outputFile) {
        $timestamp = date('dmY-His');
        $outputFile = $backupFolder . DIRECTORY_SEPARATOR . "{$label}-export-{$timestamp}.sql";
    } else {
        // If relative path, make it relative to backup folder
        if (!str_contains($outputFile, DIRECTORY_SEPARATOR)) {
            $outputFile = $backupFolder . DIRECTORY_SEPARATOR . $outputFile;
        }

        // Security: Validate output path is within backup folder
        if (!validateSecureFilePath(dirname($outputFile), $backupFolder)) {
            throw new SystemException('Output file must be within the backups directory for security reasons.');
        }
    }

    echo "Exporting {$label} database to " . basename($outputFile) . "...\n";

    $dsn = sprintf('mysql:host=%s;dbname=%s', $config['host'], $config['db']);
    if (!empty($config['port'])) {
        $dsn .= ';port=' . $config['port'];
    }

    try {
        $dump = new IMysqldump\Mysqldump($dsn, $config['username'], $config['password'] ?? '');
        $dump->start($outputFile);
        echo "Export completed: " . $outputFile . PHP_EOL;
    } catch (\Exception $e) {
        throw new SystemException('Database export failed: ' . $e->getMessage());
    }
}

function handleImport(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $args): void
{
    if (count($args) < 1) {
        throw new SystemException('Import target is required. Use: intelis|interfacing [file]');
    }

    $targetOption = $args[0];
    $sourceFile = $args[1] ?? null;

    $config = resolveTargetConfig($targetOption, $intelisDbConfig, $interfacingDbConfig);
    $label = normalizeTargetLabel($targetOption);

    // Check for --skip-safety-backup flag
    global $argv;
    $skipSafetyBackup = in_array('--skip-safety-backup', $argv ?? [], true);

    // If no file specified, show interactive selection
    if ($sourceFile === null) {
        $sqlPath = promptForImportFileSelection($backupFolder);
        if ($sqlPath === null) {
            echo 'Import cancelled.' . PHP_EOL;
            return;
        }
    } else {
        $sqlPath = resolveImportFileSecure($sourceFile, $backupFolder);
        if (!$sqlPath) {
            throw new SystemException("Import file not found or access denied: {$sourceFile}");
        }
    }

    // Create safety backup unless skipped
    if (!$skipSafetyBackup) {
        echo "Creating safety backup of current {$label} database before import...\n";
        $note = 'pre-import-' . date('His');
        $preImport = createBackupArchive($label . '-backup', $config, $backupFolder, $note);
        echo '  Created: ' . basename($preImport) . PHP_EOL;
    } else {
        echo "⚠ Skipping safety backup (--skip-safety-backup flag used)\n";
        echo "  WARNING: No backup will be created before import!\n";
    }

    $lower = strtolower($sqlPath);
    if (isGpgGzFile($lower)) {
        echo "Decrypting & decompressing GPG backup...\n";
        $derived = ($config['password'] ?? '') . extractRandomTokenFromBackup($sqlPath);
        try {
            $extractedSqlPath = decryptGpgToTempSql($sqlPath, $derived, $backupFolder);
        } catch (SystemException $e) {
            echo "  Built-in password mechanism failed.\n";
            $userPassword = promptForPassword();
            if ($userPassword === null) throw $e;
            echo "  Trying with user-provided password...\n";
            $extractedSqlPath = decryptGpgToTempSql($sqlPath, $userPassword, $backupFolder);
        }
        TempFileRegistry::register($extractedSqlPath);
    } elseif (str_ends_with($lower, '.gz')) {
        echo "Processing gzipped SQL file...\n";
        $extractedSqlPath = extractGzipFile($sqlPath, $backupFolder);
        TempFileRegistry::register($extractedSqlPath);
    } elseif (str_ends_with($lower, '.zip')) {
        $isPasswordProtected = isZipPasswordProtected($sqlPath);
        if ($isPasswordProtected) {
            echo "Processing password-protected ZIP...\n";
            $extractedSqlPath = extractSqlFromBackupWithFallback($sqlPath, $config['password'] ?? '', $backupFolder);
        } else {
            echo "Processing unprotected ZIP...\n";
            $extractedSqlPath = extractUnprotectedZip($sqlPath, $backupFolder);
        }
        TempFileRegistry::register($extractedSqlPath);
    } else {
        echo "Processing SQL file...\n";
        $extractedSqlPath = $sqlPath;
    }

    echo "Resetting {$label} database...\n";
    recreateDatabase($config);

    echo "Importing data to {$label} database...\n";
    importSqlDump($config, $extractedSqlPath);

    echo "Import completed successfully.\n";
}

function handleList(string $backupFolder): void
{
    $backups = getSortedBackups($backupFolder);
    $sqlFiles = getSortedSqlFiles($backupFolder);

    if (empty($backups) && empty($sqlFiles)) {
        echo 'No backups or SQL files found in ' . $backupFolder . PHP_EOL;
        return;
    }

    if (!empty($backups)) {
        echo "Encrypted backups:\n";
        showBackupsWithIndex($backups);
        echo "\n";
    }

    if (!empty($sqlFiles)) {
        echo "SQL files:\n";
        showBackupsWithIndex($sqlFiles);
    }
}

function handleRestore(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, ?string $requestedFile): void
{
    $backups = getSortedBackups($backupFolder);
    if (empty($backups)) {
        echo 'No encrypted backups found to restore.' . PHP_EOL;
        return;
    }

    // Check for --skip-safety-backup flag
    global $argv;
    $skipSafetyBackup = in_array('--skip-safety-backup', $argv ?? [], true);

    $selectedPath = null;
    if ($requestedFile) {
        $selectedPath = resolveBackupFileSecure($requestedFile, $backupFolder);
        if (!$selectedPath) {
            throw new SystemException("Backup file not found or access denied: {$requestedFile}");
        }
    } else {
        showBackupsWithIndex($backups);
        $selectedPath = promptForBackupSelection($backups);
        if ($selectedPath === null) {
            echo 'Restore cancelled.' . PHP_EOL;
            return;
        }
    }

    // Basic integrity check
    $ok = true;
    $lower = strtolower($selectedPath);
    if (str_ends_with($lower, '.sql.zip')) {
        $ok = verifyBackupIntegrity($selectedPath);
        if (!$ok) {
            echo "Warning: Backup file may be corrupted. Continue anyway? (y/N): ";
            $input = trim(fgets(STDIN) ?: '');
            if (strtolower($input) !== 'y') {
                echo 'Restore cancelled due to integrity check failure.' . PHP_EOL;
                return;
            }
        }
    } elseif (isGpgGzFile($lower)) {
        // Structural check only; we can't fully test without passphrase
        $ok = verifyGpgStructure($selectedPath);
        if (!$ok) {
            echo "Warning: GPG structure check failed. Continue anyway? (y/N): ";
            $input = trim(fgets(STDIN) ?: '');
            if (strtolower($input) !== 'y') {
                echo 'Restore cancelled due to integrity check failure.' . PHP_EOL;
                return;
            }
        }
    }

    $basename = basename($selectedPath);
    $targetKey = detectDatabaseKey($basename);

    if ($targetKey === 'interfacing') {
        if (!$interfacingDbConfig) {
            throw new SystemException('The backup targets the interfacing database, but it is not configured.');
        }
        $targetConfig = $interfacingDbConfig;
        $targetLabel = 'interfacing';
        $backupPrefix = 'interfacing-pre-restore';
    } else {
        $targetConfig = $intelisDbConfig;
        $targetLabel = 'intelis';
        $backupPrefix = 'pre-restore-intelis';
    }

    // Create safety backup unless skipped
    if (!$skipSafetyBackup) {
        echo 'Creating safety backup of current ' . $targetLabel . ' database before restore...' . PHP_EOL;
        $note = 'restoreof-' . slugifyForFilename($basename, 32);
        $preRestorePath = createBackupArchive($backupPrefix, $targetConfig, $backupFolder, $note);
        echo '  Created: ' . basename($preRestorePath) . PHP_EOL;
    } else {
        echo '⚠ Skipping safety backup (--skip-safety-backup flag used)' . PHP_EOL;
        echo '  WARNING: No backup will be created before restore!' . PHP_EOL;
    }

    echo 'Decrypting and extracting backup...' . PHP_EOL;
    if (isGpgGzFile($lower)) {
        $derived = ($targetConfig['password'] ?? '') . extractRandomTokenFromBackup($selectedPath);
        try {
            $sqlPath = decryptGpgToTempSql($selectedPath, $derived, $backupFolder);
        } catch (SystemException $e) {
            echo "  Built-in password mechanism failed.\n";
            $userPassword = promptForPassword();
            if ($userPassword === null) throw $e;
            echo "  Trying with user-provided password...\n";
            $sqlPath = decryptGpgToTempSql($selectedPath, $userPassword, $backupFolder);
        }
        TempFileRegistry::register($sqlPath);
    } else {
        $sqlPath = extractSqlFromBackupWithFallback($selectedPath, $targetConfig['password'] ?? '', $backupFolder);
        TempFileRegistry::register($sqlPath);
    }

    echo 'Resetting ' . $targetLabel . ' database...' . PHP_EOL;
    recreateDatabase($targetConfig);

    echo 'Restoring database from ' . $basename . '...' . PHP_EOL;
    importSqlDump($targetConfig, $sqlPath);

    // PITR suggestion (unchanged)
    $metaGuess = preg_replace('/\.(sql\.gz\.gpg|sql\.zip)$/', '.meta.json', $selectedPath);
    if (!is_file($metaGuess)) {
        $base = preg_replace('/\.(sql\.gz\.gpg|sql\.zip)$/', '', basename($selectedPath));
        $alt = dirname($selectedPath) . DIRECTORY_SEPARATOR . $base . '.meta.json';
        $metaGuess = is_file($alt) ? $alt : null;
    }

    echo 'Restore completed successfully.' . PHP_EOL;

    if ($metaGuess && is_file($metaGuess)) {
        $suggestTo = gmdate('Y-m-d H:i:s');
        $cmd = sprintf(
            "php bin/%s pitr-restore --from-meta=\"%s\" --to=\"%s\" --target=%s",
            basename(__FILE__),
            $metaGuess,
            $suggestTo,
            $targetLabel
        );
        echo PHP_EOL;
        echo "Next step (optional - Point-in-Time Recovery):\n";
        echo "  $cmd\n";
        echo "Adjust --to to your desired timestamp (YYYY-MM-DD HH:MM:SS, in UTC or with timezone offset).\n";
    } else {
        echo "Note: PITR suggestion unavailable (no .meta.json found for this backup).\n";
    }
}


function handleMysqlCheck(array $intelisDbConfig, ?array $interfacingDbConfig, array $args): void
{
    $targetOption = extractTargetOption($args);
    $targets = resolveMaintenanceTargets($targetOption, $intelisDbConfig, $interfacingDbConfig);

    foreach ($targets as $target) {
        echo 'Running database maintenance for ' . $target['label'] . ' database...' . PHP_EOL;
        $output = runMysqlCheckCommand($target['config']);
        if ($output !== '') {
            foreach (explode("\n", $output) as $line) {
                if ($line !== '') {
                    echo '  ' . $line . PHP_EOL;
                }
            }
        }
        echo '  Maintenance completed for ' . $target['label'] . PHP_EOL;
    }
}

function handlePurgeBinlogs(array $intelisDbConfig, ?array $interfacingDbConfig, array $args): void
{
    $targetOption = extractTargetOption($args);
    $days = extractDaysOption($args, 7);
    $targets = resolveMaintenanceTargets($targetOption, $intelisDbConfig, $interfacingDbConfig);

    $sql = sprintf('PURGE BINARY LOGS BEFORE DATE(NOW() - INTERVAL %d DAY);', $days);

    foreach ($targets as $target) {
        echo sprintf('Purging binary logs older than %d day(s) for %s database...', $days, $target['label']) . PHP_EOL;
        $result = runMysqlQuery($target['config'], $sql);
        if ($result !== '') {
            foreach (explode("\n", $result) as $line) {
                if ($line !== '') {
                    echo '  ' . $line . PHP_EOL;
                }
            }
        }
        echo '  Log cleanup completed for ' . $target['label'] . PHP_EOL;
    }
}

/**
 * Interactive file selection for import
 */
function promptForImportFileSelection(string $backupFolder): ?string
{
    $backups = getSortedBackups($backupFolder);
    $sqlFiles = getSortedSqlFiles($backupFolder);

    $allFiles = array_merge($backups, $sqlFiles);

    if (empty($allFiles)) {
        echo 'No import files found in ' . $backupFolder . PHP_EOL;
        return null;
    }

    echo "Available files for import:\n";
    showBackupsWithIndex($allFiles);

    $count = count($allFiles);
    while (true) {
        $prompt = sprintf('Select file [1-%d] (or press Enter to cancel): ', $count);
        $input = function_exists('readline') ? readline($prompt) : null;
        if ($input === null) {
            echo $prompt;
            $input = fgets(STDIN) ?: '';
        }
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (!ctype_digit($input)) {
            echo 'Please enter a number.' . PHP_EOL;
            continue;
        }

        $index = (int) $input;
        if ($index < 1 || $index > $count) {
            echo 'Selection out of range.' . PHP_EOL;
            continue;
        }

        return $allFiles[$index - 1]['path'];
    }
}

// Archive and Password Handling Functions

function isZipPasswordProtected(string $zipPath): bool
{
    $zip = new ZipArchive();
    $status = $zip->open($zipPath);

    if ($status !== true) {
        // If we can't open it, assume it might be password protected
        return true;
    }

    // Check if any files in the archive are encrypted
    $isProtected = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if ($stat !== false) {
            // Check encryption method - if it's not 0 (none), it's encrypted
            if (isset($stat['encryption_method']) && $stat['encryption_method'] !== 0) {
                $isProtected = true;
                break;
            }
        }
    }

    $zip->close();
    return $isProtected;
}

function extractUnprotectedZip(string $zipPath, string $backupFolder): string
{
    $zip = new ZipArchive();
    $status = $zip->open($zipPath);

    if ($status !== true) {
        throw new SystemException(sprintf('Failed to open archive. (Status code: %s)', $status));
    }

    if ($zip->numFiles < 1) {
        $zip->close();
        throw new SystemException('Archive is empty.');
    }

    // Find the first .sql file in the archive
    $sqlEntryName = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name !== false && str_ends_with(strtolower($name), '.sql')) {
            $sqlEntryName = $name;
            break;
        }
    }

    if ($sqlEntryName === null) {
        $zip->close();
        throw new SystemException('No SQL file found in archive.');
    }

    $tempDir = $backupFolder . DIRECTORY_SEPARATOR . '.tmp';
    if (!is_dir($tempDir)) {
        MiscUtility::makeDirectory($tempDir);
    }

    $destination = $tempDir . DIRECTORY_SEPARATOR . basename($sqlEntryName);
    if (is_file($destination) && !unlink($destination)) {
        $zip->close();
        throw new SystemException('Unable to clear previous temporary file.');
    }

    if (!$zip->extractTo($tempDir, [$sqlEntryName])) {
        $zip->close();
        throw new SystemException('Failed to extract archive.');
    }

    $zip->close();

    $sqlPath = $tempDir . DIRECTORY_SEPARATOR . basename($sqlEntryName);
    if (!is_file($sqlPath)) {
        throw new SystemException('Extracted SQL file not found.');
    }

    return $sqlPath;
}

function extractSqlFromBackupWithFallback(string $zipPath, string $dbPassword, string $backupFolder): string
{
    // First try our built-in mechanism (database password + token)
    try {
        return extractSqlFromBackup($zipPath, $dbPassword, $backupFolder);
    } catch (SystemException $e) {
        // Check if the error is password-related
        if (str_contains(strtolower($e->getMessage()), 'password')) {
            echo "  Built-in password mechanism failed.\n";

            // Try to prompt user for password
            $userPassword = promptForPassword();
            if ($userPassword !== null) {
                echo "  Trying with user-provided password...\n";
                return extractSqlFromBackupWithPassword($zipPath, $userPassword, $backupFolder);
            } else {
                echo "  No password provided.\n";
            }
        }

        // Re-throw the original exception
        throw $e;
    }
}

function extractSqlFromBackupWithPassword(string $zipPath, string $password, string $backupFolder): string
{
    $zip = new ZipArchive();
    $status = $zip->open($zipPath);
    if ($status !== true) {
        throw new SystemException(sprintf('Failed to open backup archive. (Status code: %s)', $status));
    }

    if (!$zip->setPassword($password)) {
        $zip->close();
        throw new SystemException('Failed to set password for archive. Password may be incorrect.');
    }

    if ($zip->numFiles < 1) {
        $zip->close();
        throw new SystemException('Backup archive is empty.');
    }

    // Find the first .sql file in the archive
    $sqlEntryName = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name !== false && str_ends_with(strtolower($name), '.sql')) {
            $sqlEntryName = $name;
            break;
        }
    }

    if ($sqlEntryName === null) {
        $zip->close();
        throw new SystemException('No SQL file found in backup archive.');
    }

    $tempDir = $backupFolder . DIRECTORY_SEPARATOR . '.tmp';
    if (!is_dir($tempDir)) {
        MiscUtility::makeDirectory($tempDir);
    }

    $destination = $tempDir . DIRECTORY_SEPARATOR . basename($sqlEntryName);
    if (is_file($destination) && !unlink($destination)) {
        $zip->close();
        throw new SystemException('Unable to clear previous temporary file.');
    }

    if (!$zip->extractTo($tempDir, [$sqlEntryName])) {
        $zip->close();
        throw new SystemException('Failed to extract backup archive. Password may be incorrect.');
    }

    $zip->close();

    $sqlPath = $tempDir . DIRECTORY_SEPARATOR . basename($sqlEntryName);
    if (!is_file($sqlPath)) {
        throw new SystemException('Extracted SQL file not found.');
    }

    return $sqlPath;
}

function promptForPassword(): ?string
{
    echo "Please enter the archive password: ";

    // Try to hide password input on Unix-like systems
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        // Unix-like system - try to use stty to hide input
        $password = null;

        // Disable echo
        exec('stty -echo 2>/dev/null', $output, $returnCode);
        $sttyWorked = ($returnCode === 0);

        $handle = fopen('php://stdin', 'r');
        if ($handle !== false) {
            $password = fgets($handle);
            fclose($handle);
        }

        // Re-enable echo
        if ($sttyWorked) {
            exec('stty echo 2>/dev/null');
        }

        echo "\n"; // Add newline since echo was disabled

        if ($password === false || trim($password) === '') {
            return null;
        }

        return trim($password);
    } else {
        // Windows or stty not available - use visible input
        echo "(Note: Password will be visible as you type)\n";
        echo "Password: ";

        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            return null;
        }

        $password = fgets($handle);
        fclose($handle);

        if ($password === false || trim($password) === '') {
            return null;
        }

        return trim($password);
    }
}

function extractSqlFromBackup(string $zipPath, string $dbPassword, string $backupFolder): string
{
    $zip = new ZipArchive();
    $status = $zip->open($zipPath);
    if ($status !== true) {
        throw new SystemException(sprintf('Failed to open backup archive. (Status code: %s)', $status));
    }

    $token = extractRandomTokenFromBackup($zipPath);
    $zipPassword = $dbPassword . $token;

    if (!$zip->setPassword($zipPassword)) {
        $zip->close();
        throw new SystemException('Failed to set password for archive.');
    }

    if ($zip->numFiles < 1) {
        $zip->close();
        throw new SystemException('Backup archive is empty.');
    }

    $entryName = $zip->getNameIndex(0);
    if ($entryName === false) {
        $zip->close();
        throw new SystemException('Failed to read backup archive contents.');
    }

    $tempDir = $backupFolder . DIRECTORY_SEPARATOR . '.tmp';
    if (!is_dir($tempDir)) {
        MiscUtility::makeDirectory($tempDir);
    }

    $destination = $tempDir . DIRECTORY_SEPARATOR . basename($entryName);
    if (is_file($destination) && !unlink($destination)) {
        $zip->close();
        throw new SystemException('Unable to clear previous temporary file.');
    }

    if (!$zip->extractTo($tempDir, [$entryName])) {
        $zip->close();
        throw new SystemException('Failed to extract backup archive.');
    }

    $zip->close();

    $sqlPath = $tempDir . DIRECTORY_SEPARATOR . basename($entryName);
    if (!is_file($sqlPath)) {
        throw new SystemException('Extracted SQL file not found.');
    }

    return $sqlPath;
}

function extractRandomTokenFromBackup(string $path): string
{
    $name = basename($path);

    foreach (['.sql.gz.gpg', '.sql.zip', '.sql.gz', '.zip', '.sql'] as $suffix) {
        if (str_ends_with($name, $suffix)) {
            $name = substr($name, 0, -strlen($suffix));
            break;
        }
    }

    $parts = explode('-', $name);
    $token = $parts[count($parts) - 1] ?? '';

    if ($token === '') {
        throw new SystemException('Unable to derive password token from backup filename.');
    }

    return $token;
}


function createBackupArchive(string $prefix, array $config, string $backupFolder, ?string $note = null): string
{
    $randomString = MiscUtility::generateRandomString(12);
    $parts = [$prefix, date('dmYHis')];
    if ($note) {
        $parts[] = $note;
    }
    $parts[] = $randomString;

    $baseName   = implode('-', array_filter($parts));
    $sqlPath    = $backupFolder . DIRECTORY_SEPARATOR . $baseName . '.sql';
    $gzPath     = $sqlPath . '.gz';
    $gpgPath    = $sqlPath . '.gz.gpg';

    $dsn = sprintf('mysql:host=%s;dbname=%s', $config['host'], $config['db']);
    if (!empty($config['port'])) {
        $dsn .= ';port=' . $config['port'];
    }

    // Snapshot BEFORE dump (for PITR)
    $startSnap = collectBinlogSnapshot($config);

    $tblCount = (int)trim(runMysqlQuery(
        $config,
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$config['db']}'"
    )) ?: null;

    $spinner = MiscUtility::spinnerStart($tblCount ?: null, "Dumping database `{$config['db']}` … this can take a while on large datasets");

    try {
        $dump = new Ifsnop\Mysqldump\Mysqldump($dsn, $config['username'], $config['password'] ?? '');

        if (method_exists($dump, 'setInfoHook')) {
            $lastTick = 0;
            $dump->setInfoHook(function (string $msg) use ($spinner, &$lastTick) {
                $msg = trim($msg);
                if ($msg !== '') $spinner->setMessage($msg);
                $now = microtime(true);
                if ($now - $lastTick >= 0.1) {
                    MiscUtility::spinnerAdvance($spinner);
                    $lastTick = $now;
                }
            });
        }

        // Write plain SQL first
        $dump->start($sqlPath);

        // Compress (pigz) and Encrypt (gpg)
        $spinner->setMessage("Compressing with pigz …");
        MiscUtility::spinnerAdvance($spinner);
        $gzMade = compressWithPigz($sqlPath, $gzPath);

        if (!$gzMade) {
            @unlink($sqlPath);
            throw new SystemException('Failed to compress SQL with pigz.');
        }
        @unlink($sqlPath);

        $spinner->setMessage("Encrypting with GPG (AES-256) …");
        MiscUtility::spinnerAdvance($spinner);

        $passphrase = ($config['password'] ?? '') . $randomString;
        $gpgMade = encryptWithGpg($gzPath, $gpgPath, $passphrase);
        if (!$gpgMade) {
            @unlink($gzPath);
            throw new SystemException('GPG encryption failed.');
        }
        @unlink($gzPath);
    } catch (\Throwable $e) {
        if (isset($spinner)) MiscUtility::spinnerFinish($spinner);
        @unlink($sqlPath);
        @unlink($gzPath);
        @unlink($gpgPath);
        throw new SystemException("Failed to create database dump for {$config['db']}: " . $e->getMessage());
    }

    // Snapshot AFTER dump (for PITR)
    $endSnap = collectBinlogSnapshot($config);

    // Write PITR metadata (same format as before)
    $meta = [
        'db'          => $config['db'],
        'backup_base' => $baseName,
        'created_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'app_tz'      => getAppTimezone(),
        'server'      => $endSnap['server'],
        'snapshot'    => [
            'start' => $startSnap['master_status'],
            'end'   => $endSnap['master_status'],
        ],
        'note'        => $note,
        'version'     => 1,
    ];
    $metaPath = $backupFolder . DIRECTORY_SEPARATOR . $baseName . '.meta.json';
    file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $spinner->finish();
    return $gpgPath; // now returns *.sql.gz.gpg
}



// File Operations and Listing Functions

// gzip extraction
function extractGzipFile(string $gzPath, string $backupFolder): string
{
    if (!function_exists('gzopen')) {
        throw new SystemException('PHP gzip extension is not installed. Cannot process .gz files.');
    }

    $tempDir = $backupFolder . DIRECTORY_SEPARATOR . '.tmp';
    if (!is_dir($tempDir)) {
        MiscUtility::makeDirectory($tempDir);
    }

    $outputPath = $tempDir . DIRECTORY_SEPARATOR . basename($gzPath, '.gz');

    $gz = gzopen($gzPath, 'rb');
    if (!$gz) {
        throw new SystemException('Could not open gzip file.');
    }

    $output = fopen($outputPath, 'wb');
    if (!$output) {
        gzclose($gz);
        throw new SystemException('Could not create temporary file.');
    }

    while (!gzeof($gz)) {
        $data = gzread($gz, 8192);
        if ($data === false) {
            gzclose($gz);
            fclose($output);
            @unlink($outputPath);
            throw new SystemException('Error reading gzip file.');
        }
        fwrite($output, $data);
    }

    gzclose($gz);
    fclose($output);

    return $outputPath;
}

function getSortedBackups(string $backupFolder): array
{
    $patterns = [
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql.zip',
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql.gz.gpg',
    ];

    $files = [];
    foreach ($patterns as $pattern) {
        $matches = glob($pattern) ?: [];
        $files = array_merge($files, $matches);
    }

    $backups = [];
    foreach ($files as $file) {
        $backups[] = [
            'path'     => $file,
            'basename' => basename($file),
            'mtime'    => @filemtime($file) ?: 0,
            'size'     => @filesize($file) ?: 0,
        ];
    }

    usort($backups, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $backups;
}


function getSortedSqlFiles(string $backupFolder): array
{
    $patterns = [
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql',
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql.gz',
    ];

    $files = [];
    foreach ($patterns as $pattern) {
        $matches = glob($pattern) ?: [];
        $files = array_merge($files, $matches);
    }

    $sqlFiles = [];
    foreach ($files as $file) {
        $sqlFiles[] = [
            'path' => $file,
            'basename' => basename($file),
            'mtime' => @filemtime($file) ?: 0,
            'size' => @filesize($file) ?: 0,
        ];
    }

    usort($sqlFiles, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);

    return $sqlFiles;
}

function showBackupsWithIndex(array $backups): void
{
    foreach ($backups as $index => $backup) {
        $position = $index + 1;
        $timestamp = date('Y-m-d H:i:s', $backup['mtime']);
        $size = formatFileSize($backup['size']);
        echo sprintf('[%d] %s  %s  %s', $position, $backup['basename'], $timestamp, $size) . PHP_EOL;
    }
}

function promptForBackupSelection(array $backups): ?string
{
    $count = count($backups);
    while (true) {
        $prompt = sprintf('Select backup [1-%d] (or press Enter to cancel): ', $count);
        $input = function_exists('readline') ? readline($prompt) : null;
        if ($input === null) {
            echo $prompt;
            $input = fgets(STDIN) ?: '';
        }
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (!ctype_digit($input)) {
            echo 'Please enter a numeric value.' . PHP_EOL;
            continue;
        }

        $index = (int) $input;
        if ($index < 1 || $index > $count) {
            echo 'Selection out of range.' . PHP_EOL;
            continue;
        }

        return $backups[$index - 1]['path'];
    }
}

// MySQL Operations

/**
 * Centralized MySQL command execution with proper error handling
 */
function executeMysqlCommand(array $config, array $baseCommand, ?string $inputData = null, ?string $sql = null): string
{
    $command = $baseCommand;
    $command[] = '--host=' . $config['host'];
    if (!empty($config['port'])) {
        $command[] = '--port=' . $config['port'];
    }
    $command[] = '--user=' . $config['username'];
    $charset = $config['charset'] ?? 'utf8mb4';
    $command[] = '--default-character-set=' . $charset;

    // Add SQL execution if provided
    if ($sql !== null) {
        $command[] = '--batch';
        $command[] = '--raw';
        $command[] = '--silent';
        $command[] = '--execute=' . $sql;
    }

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $env = buildProcessEnv([
        'MYSQL_PWD' => $config['password'] ?? '',
    ]);

    $process = proc_open($command, $descriptorSpec, $pipes, null, $env);
    if (!is_resource($process)) {
        throw new SystemException('Could not connect to the database. Please check your configuration and network connection.');
    }

    // Handle input data (for imports)
    if ($inputData !== null) {
        if (is_file($inputData)) {
            // Input is a file path
            $source = fopen($inputData, 'rb');
            if (!$source) {
                fclose($pipes[0]);
                proc_close($process);
                throw new SystemException('Could not read the SQL file. Please check file permissions.');
            }

            $fileSize = filesize($inputData);
            $bytesRead = 0;

            // Use ProgressBar for files larger than 1MB
            $progressBar = null;
            if ($fileSize > 1048576) {
                $out = console();
                $progressBar = new ProgressBar($out, 100); // 100 steps for percentage
                $progressBar->setFormat(" Importing SQL … %current%/%max%  [%bar%]  %elapsed:6s%  %memory:6s%");
                $progressBar->setBarCharacter("<fg=green>█</>");
                $progressBar->setEmptyBarCharacter("░");
                $progressBar->setProgressCharacter("<fg=green>█</>");
                $progressBar->start();
            }

            $lastPercent = 0;
            while (!feof($source)) {
                $chunk = fread($source, 8192);
                if ($chunk === false) break;

                fwrite($pipes[0], $chunk);
                $bytesRead += strlen($chunk);

                // Update progress bar based on percentage
                if ($progressBar !== null && $fileSize > 0) {
                    $percent = intval(($bytesRead / $fileSize) * 100);
                    if ($percent > $lastPercent) {
                        $progressBar->setProgress($percent);
                        $lastPercent = $percent;
                    }
                }
            }

            if ($progressBar !== null) {
                $progressBar->finish();
                echo "\n";
            }

            fclose($source);
        } else {
            // Input is raw data
            fwrite($pipes[0], $inputData);
        }
    }

    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        $errorMessage = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
        throw new SystemException("Database operation failed: {$errorMessage}");
    }

    return trim($stdout);
}

/**
 * Import SQL dump using centralized command execution
 */
function importSqlDump(array $config, string $sqlFilePath): void
{
    if (!commandExists('mysql')) {
        throw new SystemException('MySQL tools are not installed or not in your system PATH. Please install MySQL client tools.');
    }

    if (!validateSecureFilePath($sqlFilePath, dirname($sqlFilePath))) {
        throw new SystemException('Invalid file path provided for security reasons.');
    }

    try {
        // Apply bulk import optimizations
        echo "  Optimizing settings for fast import...\n";

        // Try to disable binary logging first (requires SUPER or BINLOG_ADMIN privilege)
        $sqlLogBinDisabled = false;
        try {
            runMysqlQuery($config, 'SET SESSION SQL_LOG_BIN=0;');
            $sqlLogBinDisabled = true;
            echo "  - Binary logging disabled (won't generate binlog during restore)\n";
        } catch (\Throwable $e) {
            // SQL_LOG_BIN requires SUPER/BINLOG_ADMIN privilege - continue without it
            echo "  - Binary logging still active (requires SUPER privilege to disable)\n";
        }

        // Apply other optimizations
        $optimizations = [
            'SET FOREIGN_KEY_CHECKS=0',
            'SET UNIQUE_CHECKS=0',
            'SET AUTOCOMMIT=0',
        ];

        foreach ($optimizations as $sql) {
            runMysqlQuery($config, $sql . ';');
        }

        echo "  - Foreign key checks: disabled\n";
        echo "  - Unique checks: disabled\n";
        echo "  - Autocommit: disabled\n";

        // Pass database name in base command for import
        executeMysqlCommand($config, ['mysql', $config['db']], $sqlFilePath);

        // Commit any pending transactions
        echo "  Committing transactions...\n";
        runMysqlQuery($config, 'COMMIT;');

        // Restore normal settings
        echo "  Restoring normal database settings...\n";
        runMysqlQuery($config, 'SET FOREIGN_KEY_CHECKS=1;');
        runMysqlQuery($config, 'SET UNIQUE_CHECKS=1;');
        runMysqlQuery($config, 'SET AUTOCOMMIT=1;');

        if ($sqlLogBinDisabled) {
            try {
                runMysqlQuery($config, 'SET SESSION SQL_LOG_BIN=1;');
                echo "  - Binary logging re-enabled\n";
            } catch (\Throwable $e) {
                // Ignore errors re-enabling SQL_LOG_BIN
            }
        }
    } catch (SystemException $e) {
        // Attempt to restore settings even if import fails
        echo "  Import failed, attempting to restore database settings...\n";
        try {
            runMysqlQuery($config, 'COMMIT;');
        } catch (\Throwable $ignored) {
        }
        try {
            runMysqlQuery($config, 'SET FOREIGN_KEY_CHECKS=1;');
        } catch (\Throwable $ignored) {
        }
        try {
            runMysqlQuery($config, 'SET UNIQUE_CHECKS=1;');
        } catch (\Throwable $ignored) {
        }
        try {
            runMysqlQuery($config, 'SET AUTOCOMMIT=1;');
        } catch (\Throwable $ignored) {
        }
        if ($sqlLogBinDisabled) {
            try {
                runMysqlQuery($config, 'SET SESSION SQL_LOG_BIN=1;');
            } catch (\Throwable $ignored) {
            }
        }

        throw new SystemException('Database import failed: ' . $e->getMessage());
    }
}

/**
 * Run MySQL check command - direct execution without executeMysqlCommand wrapper
 */
function runMysqlCheckCommand(array $config): string
{
    if (!commandExists('mysqlcheck')) {
        throw new SystemException('MySQL maintenance tools are not installed. Please install MySQL client tools.');
    }

    // Run one action per invocation (required by mysqlcheck)
    $steps = [
        ['label' => 'REPAIR (auto)', 'args' => ['--auto-repair']],  // no-op on InnoDB, safe on MyISAM
        ['label' => 'OPTIMIZE',       'args' => ['--optimize']],
        ['label' => 'ANALYZE',        'args' => ['--analyze']],
    ];

    $combined = [];
    foreach ($steps as $step) {
        $command = ['mysqlcheck'];

        // connection
        $command[] = '--host=' . $config['host'];
        if (!empty($config['port'])) {
            $command[] = '--port=' . $config['port'];
        }
        $command[] = '--user=' . $config['username'];

        // action
        foreach ($step['args'] as $a) {
            $command[] = $a;
        }

        // database last
        $command[] = $config['db'];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = buildProcessEnv([
            'MYSQL_PWD' => $config['password'] ?? '',
        ]);

        $proc = proc_open($command, $descriptorSpec, $pipes, null, $env);
        if (!is_resource($proc)) {
            throw new SystemException('Could not run database maintenance. Please verify MySQL tools are installed.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        $exit = proc_close($proc);
        if ($exit !== 0) {
            $msg = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
            throw new SystemException("Database maintenance failed during {$step['label']}: {$msg}");
        }

        $out = trim($stdout);
        if ($out !== '') {
            $combined[] = "[{$step['label']}]\n{$out}";
        } else {
            $combined[] = "[{$step['label']}] OK";
        }
    }

    return implode("\n", $combined);
}


/**
 * Run MySQL query using centralized execution
 */
function runMysqlQuery(array $config, string $sql): string
{
    if (!commandExists('mysql')) {
        throw new SystemException('MySQL tools are not installed or not in your system PATH. Please install MySQL client tools.');
    }

    try {
        // For queries, we include database in base command unless SQL contains database operations
        $baseCommand = ['mysql'];

        // Check if SQL contains database operations (CREATE, DROP, USE)
        $upperSql = strtoupper(trim($sql));
        $containsDbOperations = str_contains($upperSql, 'CREATE DATABASE') ||
            str_contains($upperSql, 'DROP DATABASE') ||
            str_contains($upperSql, 'USE ');

        // Only add database name if SQL doesn't contain database operations
        if (!$containsDbOperations) {
            $baseCommand[] = $config['db'];
        }

        return executeMysqlCommand($config, $baseCommand, null, $sql);
    } catch (SystemException $e) {
        throw new SystemException('Database query failed: ' . $e->getMessage());
    }
}

/**
 * Recreate database
 */
function recreateDatabase(array $config): void
{
    $dbName = $config['db'] ?? '';
    if ($dbName === '') {
        throw new SystemException('Database configuration is missing or invalid.');
    }

    $sanitizedDb = '`' . str_replace('`', '``', $dbName) . '`';

    $charset = $config['charset'] ?? 'utf8mb4';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $charset)) {
        throw new SystemException('Invalid database charset in configuration.');
    }

    $collation = $config['collation'] ?? null;
    if ($collation !== null && $collation !== '') {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $collation)) {
            throw new SystemException('Invalid database collation in configuration.');
        }
    }

    $clauses = ' CHARACTER SET ' . $charset;
    if ($collation) {
        $clauses .= ' COLLATE ' . $collation;
    }

    // Disable foreign key checks globally before dropping database
    // This prevents "Cannot drop table referenced by a foreign key constraint" errors
    $sql = sprintf(
        'SET FOREIGN_KEY_CHECKS=0; DROP DATABASE IF EXISTS %1$s; CREATE DATABASE %1$s%2$s; SET FOREIGN_KEY_CHECKS=1;',
        $sanitizedDb,
        $clauses
    );

    try {
        // For database recreation, we don't specify a database in the command
        // since we're creating/dropping databases
        executeMysqlCommand($config, ['mysql'], null, $sql);
    } catch (SystemException $e) {
        throw new SystemException('Failed to recreate database: ' . $e->getMessage());
    }
}

// Utility Functions

function resolveTargetConfig(string $targetOption, array $intelisDbConfig, ?array $interfacingDbConfig): array
{
    $normalized = normalizeTargetLabel($targetOption);

    if ($normalized === 'interfacing') {
        if (!$interfacingDbConfig) {
            throw new SystemException('The interfacing database is not configured.');
        }
        return $interfacingDbConfig;
    }

    return $intelisDbConfig;
}

/**
 * Standardized target label normalization - only 'intelis' and 'interfacing'
 */
function normalizeTargetLabel(string $targetOption): string
{
    $normalized = strtolower(trim($targetOption));

    // Map all interfacing variants to 'interfacing'
    if (in_array($normalized, ['interfacing', 'interface'], true)) {
        return 'interfacing';
    }

    // Everything else maps to 'intelis' (including 'vlsm', 'primary', 'default')
    return 'intelis';
}

function resolveBackupTargets(?string $targetOption, array $intelisDbConfig, ?array $interfacingDbConfig): array
{
    if ($targetOption === null) {
        // Default behavior: both if interfacing enabled, intelis otherwise
        if ($interfacingDbConfig) {
            return [
                ['label' => 'intelis', 'config' => $intelisDbConfig],
                ['label' => 'interfacing', 'config' => $interfacingDbConfig],
            ];
        } else {
            return [
                ['label' => 'intelis', 'config' => $intelisDbConfig],
            ];
        }
    }

    $normalized = strtolower($targetOption);

    if (in_array($normalized, ['both', 'all'], true)) {
        if (!$interfacingDbConfig) {
            throw new SystemException('The interfacing database is not configured; cannot target both databases.');
        }

        return [
            ['label' => 'intelis', 'config' => $intelisDbConfig],
            ['label' => 'interfacing', 'config' => $interfacingDbConfig],
        ];
    }

    if (in_array($normalized, ['interfacing', 'interface'], true)) {
        if (!$interfacingDbConfig) {
            throw new SystemException('The interfacing database is not configured.');
        }

        return [
            ['label' => 'interfacing', 'config' => $interfacingDbConfig],
        ];
    }

    return [
        ['label' => 'intelis', 'config' => $intelisDbConfig],
    ];
}

function resolveMaintenanceTargets(?string $targetOption, array $intelisDbConfig, ?array $interfacingDbConfig): array
{
    $normalized = $targetOption ?? 'intelis';

    if (in_array($normalized, ['both', 'all'], true)) {
        if (!$interfacingDbConfig) {
            throw new SystemException('The interfacing database is not configured; cannot target both databases.');
        }

        return [
            ['label' => 'intelis', 'config' => $intelisDbConfig],
            ['label' => 'interfacing', 'config' => $interfacingDbConfig],
        ];
    }

    if (in_array($normalized, ['interfacing', 'interface'], true)) {
        if (!$interfacingDbConfig) {
            throw new SystemException('The interfacing database is not configured.');
        }

        return [
            ['label' => 'interfacing', 'config' => $interfacingDbConfig],
        ];
    }

    return [
        ['label' => 'intelis', 'config' => $intelisDbConfig],
    ];
}

function detectDatabaseKey(string $basename): string
{
    $tokens = explode('-', strtolower($basename));
    return in_array('interfacing', $tokens, true) ? 'interfacing' : 'intelis';
}

function slugifyForFilename(string $value, int $maxLength = 32): string
{
    $slug = strtolower($value);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    if ($maxLength > 0 && strlen($slug) > $maxLength) {
        $slug = substr($slug, 0, $maxLength);
    }

    return $slug !== '' ? $slug : 'note';
}

/**
 * Target option extraction
 */
function extractTargetOption(array $args): ?string
{
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--target=')) {
            $value = trim(substr($arg, 9));
            if ($value !== '') {
                return strtolower($value);
            }
        }
    }

    foreach ($args as $arg) {
        $candidate = strtolower($arg);
        // Accept legacy names but they'll be normalized
        if (in_array($candidate, ['intelis', 'vlsm', 'primary', 'default', 'interfacing', 'interface', 'both', 'all'], true)) {
            return $candidate;
        }
    }

    return null;
}

function extractDaysOption(array $args, int $default): int
{
    foreach ($args as $arg) {
        if (preg_match('/^--days=(\d+)$/', $arg, $matches)) {
            $value = (int) $matches[1];
            if ($value < 1) {
                throw new SystemException('Days value must be greater than zero.');
            }
            return $value;
        }
    }

    return $default;
}

function formatFileSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = $bytes;
    $unitIndex = 0;

    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    return sprintf('%.1f%s', $size, $units[$unitIndex]);
}

function buildProcessEnv(array $extra = []): array
{
    $env = [];

    if (isset($_ENV) && is_array($_ENV) && $_ENV !== []) {
        $env = $_ENV;
    } else {
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
                $env[$key] = (string) $value;
            }
        }
    }

    if (empty($env['PATH'])) {
        $path = getenv('PATH');
        if (!is_string($path) || $path === '') {
            $path = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/homebrew/bin:/opt/homebrew/sbin';
        }
        $env['PATH'] = $path;
    }

    foreach ($extra as $key => $value) {
        if ($value === null) {
            unset($env[$key]);
        } else {
            $env[$key] = $value;
        }
    }

    return $env;
}

function commandExists(string $command): bool
{
    if ($command === '') {
        return false;
    }

    if (str_contains($command, DIRECTORY_SEPARATOR)) {
        return is_file($command) && is_executable($command);
    }

    $env = buildProcessEnv();
    $paths = explode(PATH_SEPARATOR, $env['PATH'] ?? '');
    foreach ($paths as $path) {
        $fullPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $command;
        if (is_file($fullPath) && is_executable($fullPath)) {
            return true;
        }
    }

    return false;
}

// Security and Path Validation Functions

/**
 * Validates that a file path is within the allowed backup directory
 * Prevents path traversal attacks
 */
function validateSecureFilePath(string $filePath, string $allowedDirectory): bool
{
    // Get real, canonical paths
    $realFilePath = realpath($filePath);
    $realAllowedDir = realpath($allowedDirectory);

    // If either path doesn't exist or can't be resolved, reject
    if ($realFilePath === false || $realAllowedDir === false) {
        return false;
    }

    // Handle exact match (file is the directory itself)
    if ($realFilePath === $realAllowedDir) {
        return true;
    }

    // Normalize the allowed directory path with trailing separator
    $normalizedAllowedDir = rtrim($realAllowedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    // Check if file path is within the allowed directory tree
    // This prevents partial matches like /home/user vs /home/user2
    return str_starts_with($realFilePath . DIRECTORY_SEPARATOR, $normalizedAllowedDir);
}

/**
 * Securely resolves import file path with validation
 */
function resolveImportFileSecure(string $sourceFile, string $backupFolder): ?string
{
    $sourceFile = trim($sourceFile);
    if ($sourceFile === '') return null;

    $candidates = [];

    if (str_contains($sourceFile, DIRECTORY_SEPARATOR)) {
        $candidates[] = $sourceFile;
    } else {
        $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile;

        $lower = strtolower($sourceFile);
        $hasExt = preg_match('/\.(sql(\.gz(\.gpg)?)?|zip)$/', $lower);

        if (!$hasExt) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql.gz';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql.gz.gpg';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.zip';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql.zip';
        }
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && validateSecureFilePath($candidate, $backupFolder)) {
            return realpath($candidate);
        }
    }
    return null;
}


/**
 * Securely resolves backup file path with validation
 */
function resolveBackupFileSecure(string $requested, string $backupFolder): ?string
{
    $requested = trim($requested);
    if ($requested === '') return null;

    $candidates = [];

    if (str_contains($requested, DIRECTORY_SEPARATOR)) {
        $candidates[] = $requested;
    } else {
        $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested;

        if (!preg_match('/\.(sql\.gz\.gpg|sql\.zip)$/i', $requested)) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested . '.sql.gz.gpg';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested . '.sql.zip';
        }
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && validateSecureFilePath($candidate, $backupFolder)) {
            return realpath($candidate);
        }
    }
    return null;
}


/**
 * Global cleanup registry for temporary files
 */
class TempFileRegistry
{
    private static array $tempFiles = [];
    private static bool $shutdownRegistered = false;

    public static function register(string $filePath): void
    {
        self::$tempFiles[] = $filePath;

        if (!self::$shutdownRegistered) {
            register_shutdown_function([self::class, 'cleanup']);
            self::$shutdownRegistered = true;
        }
    }

    public static function unregister(string $filePath): void
    {
        $key = array_search($filePath, self::$tempFiles, true);
        if ($key !== false) {
            unset(self::$tempFiles[$key]);
        }
    }

    public static function cleanup(): void
    {
        foreach (self::$tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        self::$tempFiles = [];
    }
}

/**
 * User-friendly error handler
 */
function handleUserFriendlyError(\Throwable $e): void
{
    $message = $e->getMessage();
    $userMessage = translateErrorMessage($message);

    LoggerUtility::log('error', $e->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $e->getTraceAsString()
    ]);

    fwrite(STDERR, 'Error: ' . $userMessage . PHP_EOL);
}

/**
 * Translates technical error messages to user-friendly ones
 */
function translateErrorMessage(string $technicalMessage): string
{
    $translations = [
        'Failed to open mysql process' => 'Could not connect to the database. Please check your configuration and network connection.',
        'Failed to open mysqlcheck process' => 'Could not run database maintenance. Please verify MySQL tools are installed.',
        'mysql command not found' => 'MySQL tools are not installed or not in your system PATH. Please install MySQL client tools.',
        'mysqlcheck command not found' => 'MySQL maintenance tools are not installed. Please install MySQL client tools.',
        'Failed to create zip archive' => 'Could not create backup file. Please check disk space and permissions.',
        'Failed to open backup archive' => 'Could not open the backup file. It may be corrupted or in an unsupported format.',
        'Failed to set password for archive' => 'Incorrect password for the backup file.',
        'No SQL file found in archive' => 'The backup file does not contain a valid database dump.',
        'Database name not configured' => 'Database configuration is missing or invalid.',
        'Interfacing database not configured' => 'The secondary database is not set up in your configuration.',
    ];

    foreach ($translations as $technical => $friendly) {
        if (str_contains($technicalMessage, $technical)) {
            return $friendly;
        }
    }

    // Return original message if no translation found
    return $technicalMessage;
}


/**
 * Run comprehensive database maintenance
 * - mysqlcheck (optimize, repair, analyze)
 * - purge old binary logs
 */
function handleMaintain(array $intelisDbConfig, ?array $interfacingDbConfig, array $args): void
{
    $targetOption = extractTargetOption($args);
    $days = extractDaysOption($args, 7);
    $targets = resolveMaintenanceTargets($targetOption, $intelisDbConfig, $interfacingDbConfig);

    echo "===========================================\n";
    echo "  DATABASE MAINTENANCE\n";
    echo "===========================================\n\n";

    foreach ($targets as $target) {
        echo "Processing {$target['label']} database...\n";
        echo str_repeat('-', 43) . "\n";

        // Step 1: MySQL Check
        echo "Step 1/2: Running database optimization and repair...\n";

        try {
            $output = runMysqlCheckCommand($target['config']);
            if ($output !== '') {
                foreach (explode("\n", $output) as $line) {
                    if ($line !== '') {
                        echo '  ' . $line . PHP_EOL;
                    }
                }
            }
            echo "  ✅ Database maintenance completed\n\n";
        } catch (SystemException $e) {
            echo "  ❌ Database maintenance failed: " . $e->getMessage() . "\n\n";
        }

        // Step 2: Purge Binary Logs
        echo "Step 2/2: Cleaning up old binary logs...\n";

        try {
            $sql = sprintf('PURGE BINARY LOGS BEFORE DATE(NOW() - INTERVAL %d DAY);', $days);
            echo sprintf("  Purging logs older than %d day(s)...\n", $days);

            $result = runMysqlQuery($target['config'], $sql);
            if ($result !== '') {
                foreach (explode("\n", $result) as $line) {
                    if ($line !== '') {
                        echo '  ' . $line . PHP_EOL;
                    }
                }
            }
            echo "  ✅ Binary log cleanup completed\n\n";
        } catch (SystemException $e) {
            echo "  ❌ Binary log cleanup failed: " . $e->getMessage() . "\n\n";
        }

        if (count($targets) > 1) {
            echo "\n";
        }
    }

    echo "===========================================\n";
    echo "  MAINTENANCE COMPLETE\n";
    echo "===========================================\n";
}


/**
 * Verify backup integrity
 */
function handleVerify(string $backupFolder, array $args): void
{
    $backupFile = $args[0] ?? null;

    if ($backupFile === null) {
        $backups = getSortedBackups($backupFolder);
        if (empty($backups)) {
            echo 'No backups found to verify.' . PHP_EOL;
            return;
        }
        echo "Select backup to verify:\n";
        showBackupsWithIndex($backups);
        $selectedPath = promptForBackupSelection($backups);
        if ($selectedPath === null) {
            echo 'Verification cancelled.' . PHP_EOL;
            return;
        }
    } else {
        $selectedPath = resolveBackupFileSecure($backupFile, $backupFolder);
        if (!$selectedPath) {
            throw new SystemException("Backup file not found or access denied: {$backupFile}");
        }
    }

    $basename = basename($selectedPath);
    $fileSize = formatFileSize(filesize($selectedPath));
    $lower = strtolower($selectedPath);

    echo "Verifying backup: {$basename} ({$fileSize})\n";
    echo str_repeat('-', 50) . "\n";
    echo "✅ File exists and is readable\n";

    if (str_ends_with($lower, '.sql.zip')) {
        echo "Checking ZIP integrity... ";
        if (!verifyBackupIntegrity($selectedPath)) {
            echo "❌ FAILED\n";
            echo "  Error: ZIP archive is corrupted or invalid\n";
            exit(1);
        }
        echo "✅ PASSED\n";

        echo "Checking archive contents... ";
        $zip = new ZipArchive();
        $status = $zip->open($selectedPath);
        if ($status !== true) {
            echo "❌ FAILED\n";
            echo "  Error: Cannot open archive (code: {$status})\n";
            exit(1);
        }
        $sqlFound = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && str_ends_with(strtolower($name), '.sql')) {
                $sqlFound = true;
                $stat = $zip->statIndex($i);
                $sqlSize = $stat ? formatFileSize($stat['size']) : 'unknown';
                echo "✅ PASSED\n";
                echo "  Found: {$name} ({$sqlSize})\n";
                break;
            }
        }
        if (!$sqlFound) {
            echo "❌ FAILED\n";
            echo "  Error: No SQL file found in archive\n";
            $zip->close();
            exit(1);
        }
        $zip->close();

        echo "Checking encryption... ";
        echo isZipPasswordProtected($selectedPath)
            ? "✅ Password protected (AES-256)\n"
            : "⚠ WARNING: Archive is not password protected\n";
    } elseif (isGpgGzFile($lower)) {
        echo "Checking GPG structure... ";
        if (!verifyGpgStructure($selectedPath)) {
            echo "❌ FAILED\n";
            echo "  Error: GPG packet structure unreadable\n";
            exit(1);
        }
        echo "✅ PASSED\n";
        echo "Encryption: ✅ GPG symmetric (AES-256 expected)\n";
        echo "Inner compression: gzip (not verified without passphrase)\n";
    } else {
        echo "❌ Unsupported file type for verification\n";
        exit(1);
    }

    echo str_repeat('-', 50) . "\n";
    echo "✅ Backup verification PASSED\n";
    echo "\nBackup is valid and can be restored.\n";
}


/**
 * Clean old backups based on retention policy
 */
function handleClean(string $backupFolder, array $args): void
{
    $keepCount = extractKeepOption($args);
    $keepDays = extractDaysOption($args, 0);

    if ($keepCount === null && $keepDays === 0) {
        throw new SystemException('Please specify --keep=N or --days=N for retention policy');
    }

    $backups = getSortedBackups($backupFolder);

    if (empty($backups)) {
        echo 'No backups found to clean.' . PHP_EOL;
        return;
    }

    echo "Backup cleanup\n";
    echo str_repeat('-', 50) . "\n";
    echo "Total backups: " . count($backups) . "\n";

    $toDelete = [];

    if ($keepCount !== null) {
        // Keep N most recent backups
        if (count($backups) <= $keepCount) {
            echo "Retention policy: Keep {$keepCount} most recent\n";
            echo "Action: Nothing to delete (have " . count($backups) . " backups)\n";
            return;
        }

        $toDelete = array_slice($backups, $keepCount);
        echo "Retention policy: Keep {$keepCount} most recent\n";
    } elseif ($keepDays > 0) {
        // Keep backups newer than N days
        $cutoffTime = time() - ($keepDays * 86400);

        foreach ($backups as $backup) {
            if ($backup['mtime'] < $cutoffTime) {
                $toDelete[] = $backup;
            }
        }

        echo "Retention policy: Keep backups newer than {$keepDays} day(s)\n";

        if (empty($toDelete)) {
            echo "Action: Nothing to delete (all backups are within retention period)\n";
            return;
        }
    }

    echo "Backups to delete: " . count($toDelete) . "\n";
    echo str_repeat('-', 50) . "\n";

    // Show what will be deleted
    $totalSize = 0;
    foreach ($toDelete as $backup) {
        $age = floor((time() - $backup['mtime']) / 86400);
        $size = $backup['size'];
        $totalSize += $size;
        echo sprintf(
            "  %s (%s, %d days old)\n",
            $backup['basename'],
            formatFileSize($size),
            $age
        );
    }

    echo str_repeat('-', 50) . "\n";
    echo "Total space to free: " . formatFileSize($totalSize) . "\n\n";

    // Confirm deletion
    echo "Proceed with deletion? (y/N): ";
    $input = trim(fgets(STDIN) ?: '');

    if (strtolower($input) !== 'y') {
        echo "Cleanup cancelled.\n";
        return;
    }

    // Delete files
    $deleted = 0;
    $failed = 0;

    foreach ($toDelete as $backup) {
        if (@unlink($backup['path'])) {
            $deleted++;
            echo "✅ Deleted: " . $backup['basename'] . "\n";
        } else {
            $failed++;
            echo "❌ Failed to delete: " . $backup['basename'] . "\n";
        }
    }

    echo str_repeat('-', 50) . "\n";
    echo "Cleanup complete: {$deleted} deleted, {$failed} failed\n";

    if ($deleted > 0) {
        echo "Freed: " . formatFileSize($totalSize) . "\n";
    }
}

/**
 * Extract --keep option from arguments
 */
function extractKeepOption(array $args): ?int
{
    foreach ($args as $arg) {
        if (preg_match('/^--keep=(\d+)$/', $arg, $matches)) {
            $value = (int) $matches[1];
            if ($value < 1) {
                throw new SystemException('Keep value must be greater than zero.');
            }
            return $value;
        }
    }

    return null;
}


/**
 * Show database size information
 */
function handleSize(array $intelisDbConfig, ?array $interfacingDbConfig, array $args): void
{
    $targetOption = extractTargetOption($args);
    $targets = resolveMaintenanceTargets($targetOption, $intelisDbConfig, $interfacingDbConfig);

    foreach ($targets as $target) {
        echo "===========================================\n";
        echo "  DATABASE SIZE: {$target['label']}\n";
        echo "===========================================\n\n";

        try {
            $sizeInfo = getDatabaseSize($target['config']);

            // Overall database size
            echo "Total database size: " . formatFileSize($sizeInfo['total_size']) . "\n";
            echo "Total tables: " . $sizeInfo['table_count'] . "\n\n";

            if (!empty($sizeInfo['tables'])) {
                echo "Top tables by size:\n";
                echo str_repeat('-', 80) . "\n";
                echo sprintf("%-40s %12s %12s %12s\n", "Table", "Data", "Index", "Total");
                echo str_repeat('-', 80) . "\n";

                // Show top 20 tables
                $tablesToShow = array_slice($sizeInfo['tables'], 0, 20);

                foreach ($tablesToShow as $table) {
                    echo sprintf(
                        "%-40s %12s %12s %12s\n",
                        $table['name'],
                        formatFileSize($table['data_size']),
                        formatFileSize($table['index_size']),
                        formatFileSize($table['total_size'])
                    );
                }

                if (count($sizeInfo['tables']) > 20) {
                    $remaining = count($sizeInfo['tables']) - 20;
                    echo str_repeat('-', 80) . "\n";
                    echo "... and {$remaining} more table(s)\n";
                }
            }

            echo "\n";
        } catch (SystemException $e) {
            echo "❌ Failed to get size information: " . $e->getMessage() . "\n\n";
        }
    }
}

/**
 * Get database size information
 */
function getDatabaseSize(array $config): array
{
    $sql = "
        SELECT 
            TABLE_NAME as name,
            DATA_LENGTH as data_size,
            INDEX_LENGTH as index_size,
            DATA_LENGTH + INDEX_LENGTH as total_size
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '{$config['db']}'
        ORDER BY total_size DESC
    ";

    if (!commandExists('mysql')) {
        throw new SystemException('MySQL tools are not installed or not in your system PATH.');
    }

    try {
        $output = executeMysqlCommand(
            $config,
            ['mysql', '--skip-column-names'],
            null,
            $sql
        );

        $tables = [];
        $totalSize = 0;

        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (trim($line) === '') continue;

            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 4) {
                $dataSize = (int) $parts[1];
                $indexSize = (int) $parts[2];
                $tableTotal = (int) $parts[3];

                $tables[] = [
                    'name' => $parts[0],
                    'data_size' => $dataSize,
                    'index_size' => $indexSize,
                    'total_size' => $tableTotal,
                ];

                $totalSize += $tableTotal;
            }
        }

        return [
            'total_size' => $totalSize,
            'table_count' => count($tables),
            'tables' => $tables,
        ];
    } catch (SystemException $e) {
        throw new SystemException('Failed to retrieve database size: ' . $e->getMessage());
    }
}

/**
 * Test database configuration and connectivity
 */
function handleConfigTest(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $args): void
{
    $targetOption = extractTargetOption($args);
    $targets = resolveMaintenanceTargets($targetOption, $intelisDbConfig, $interfacingDbConfig);

    echo "===========================================\n";
    echo "  DATABASE CONFIGURATION TEST\n";
    echo "===========================================\n\n";

    $allPassed = true;

    foreach ($targets as $target) {
        echo "Testing {$target['label']} database...\n";
        echo str_repeat('-', 50) . "\n";

        $config = $target['config'];

        // Test 1: Configuration completeness
        echo "1. Configuration completeness... ";
        $required = ['host', 'username', 'db'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            echo "❌ FAILED\n";
            echo "   Missing: " . implode(', ', $missing) . "\n";
            $allPassed = false;
        } else {
            echo "✅ PASSED\n";
        }

        // Test 2: MySQL client tools
        echo "2. MySQL client tools... ";
        if (!commandExists('mysql')) {
            echo "❌ FAILED\n";
            echo "   mysql command not found in PATH\n";
            $allPassed = false;
        } else {
            echo "✅ PASSED\n";
        }

        if (!commandExists('mysqlcheck')) {
            echo "   ⚠ WARNING: mysqlcheck not found (maintenance commands unavailable)\n";
        }

        // Test 3: Database connectivity
        echo "3. Database connectivity... ";
        try {
            $result = testDatabaseConnection($config);
            echo "✅ PASSED\n";
            echo "   Connected to: {$config['host']}:{$config['port']}\n";
            echo "   Database: {$config['db']}\n";
            echo "   MySQL version: {$result['version']}\n";
        } catch (SystemException $e) {
            echo "❌ FAILED\n";
            echo "   " . $e->getMessage() . "\n";
            $allPassed = false;
        }

        // Test 4: Database permissions
        echo "4. Database permissions... ";
        try {
            testDatabasePermissions($config);
            echo "✅ PASSED\n";
        } catch (SystemException $e) {
            echo "❌ FAILED\n";
            echo "   " . $e->getMessage() . "\n";
            $allPassed = false;
        }

        // Test 5: Backup folder
        echo "5. Backup folder access... ";
        if (!is_dir($backupFolder)) {
            echo "❌ FAILED\n";
            echo "   Directory does not exist: {$backupFolder}\n";
            $allPassed = false;
        } elseif (!is_writable($backupFolder)) {
            echo "❌ FAILED\n";
            echo "   Directory is not writable: {$backupFolder}\n";
            $allPassed = false;
        } else {
            echo "✅ PASSED\n";
            $freeSpace = disk_free_space($backupFolder);
            if ($freeSpace !== false) {
                echo "   Free space: " . formatFileSize($freeSpace) . "\n";

                // Warn if less than 1GB free
                if ($freeSpace < 1073741824) {
                    echo "   ⚠ WARNING: Low disk space (< 1GB remaining)\n";
                }
            }
        }

        // Test 6: Character set
        echo "6. Character set configuration... ";
        $charset = $config['charset'] ?? 'utf8mb4';
        if ($charset === 'utf8mb4') {
            echo "✅ PASSED\n";
            echo "   Using: {$charset}\n";
        } else {
            echo "⚠ WARNING\n";
            echo "   Using: {$charset} (utf8mb4 recommended)\n";
        }

        echo "\n";
    }

    echo "===========================================\n";
    if ($allPassed) {
        echo "  ✅ ALL TESTS PASSED\n";
    } else {
        echo "  ❌ SOME TESTS FAILED\n";
    }
    echo "===========================================\n";

    exit($allPassed ? 0 : 1);
}

/**
 * Test database connection
 */
function testDatabaseConnection(array $config): array
{
    $sql = "SELECT VERSION() as version;";

    try {
        $output = executeMysqlCommand(
            $config,
            ['mysql', $config['db'], '--skip-column-names'],
            null,
            $sql
        );

        return [
            'version' => trim($output) ?: 'Unknown',
        ];
    } catch (SystemException $e) {
        throw new SystemException('Cannot connect to database: ' . $e->getMessage());
    }
}

/**
 * Test database permissions
 */
function testDatabasePermissions(array $config): void
{
    // Test basic SELECT
    $sql = "SELECT 1;";

    try {
        executeMysqlCommand(
            $config,
            ['mysql', $config['db'], '--skip-column-names'],
            null,
            $sql
        );
    } catch (SystemException $e) {
        throw new SystemException('SELECT permission denied');
    }

    // Test if we can list tables
    $sql = "SHOW TABLES;";

    try {
        executeMysqlCommand(
            $config,
            ['mysql', $config['db'], '--skip-column-names'],
            null,
            $sql
        );
    } catch (SystemException $e) {
        throw new SystemException('Cannot list tables');
    }
}

function parseArgs(array $args): array
{
    $out = [];
    foreach ($args as $a) {
        if (preg_match('/^--([^=]+)=(.*)$/', $a, $m)) {
            $out[$m[1]] = $m[2];
        }
    }
    return $out;
}

function getBinlogList(array $config): array
{
    // SHOW BINARY LOGS returns Log_name and File_size; we only need names
    $res = runMysqlQuery($config, "SHOW BINARY LOGS");
    $files = [];
    foreach (explode("\n", trim($res)) as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (!empty($parts[0])) $files[] = $parts[0];
    }
    return $files;
}

function runPipeline(array $producer, array $consumer, array $env = []): int
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $env = buildProcessEnv($env);

    // Start producer
    $proc1 = proc_open($producer, $descriptors, $pipes1, null, $env);
    if (!is_resource($proc1)) {
        throw new SystemException('Failed to start mysqlbinlog process.');
    }

    // Start consumer
    $descriptors2 = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc2 = proc_open($consumer, $descriptors2, $pipes2, null, $env);
    if (!is_resource($proc2)) {
        proc_close($proc1);
        throw new SystemException('Failed to start mysql (consumer) process.');
    }

    // Pipe producer stdout -> consumer stdin
    stream_set_blocking($pipes1[1], true);
    stream_set_blocking($pipes2[0], true);

    while (!feof($pipes1[1])) {
        $buf = fread($pipes1[1], 8192);
        if ($buf === false) break;
        fwrite($pipes2[0], $buf);
    }

    fclose($pipes1[1]);
    fclose($pipes1[0]);
    fclose($pipes1[2]);
    fclose($pipes2[0]);

    $out = stream_get_contents($pipes2[1]) ?: '';
    $err = stream_get_contents($pipes2[2]) ?: '';
    fclose($pipes2[1]);
    fclose($pipes2[2]);

    $code1 = proc_close($proc1);
    $code2 = proc_close($proc2);

    if ($code1 !== 0 || $code2 !== 0) {
        $msg = trim($err) !== '' ? $err : $out;
        throw new SystemException("PITR pipeline failed: {$msg}");
    }
    return 0;
}

function handlePitrInfo(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $args): void
{
    $targetOption = extractTargetOption($args) ?? 'intelis';
    $config = resolveTargetConfig($targetOption, $intelisDbConfig, $interfacingDbConfig);
    $label  = normalizeTargetLabel($targetOption);

    echo "PITR readiness for {$label}\n";
    echo str_repeat('-', 50) . "\n";

    $snap = collectBinlogSnapshot($config);
    if (strtoupper((string)$snap['server']['log_bin']) !== 'ON' && $snap['server']['log_bin'] !== '1') {
        echo "❌ Binary logging is OFF. Enable log_bin to use PITR.\n";
        return;
    }
    echo "✅ Binary logging: ON\n";
    echo "  Server UUID: " . ($snap['server']['uuid'] ?? 'unknown') . "\n";
    echo "  Binlog base: " . ($snap['server']['log_bin_basename'] ?? 'unknown') . "\n";
    echo "  GTID mode:   " . ($snap['server']['gtid_mode'] ?? 'OFF') . "\n";

    $binlogs = getBinlogList($config);
    if (empty($binlogs)) {
        echo "❌ No binary logs listed by server.\n";
        return;
    }
    echo "  Binlogs on server: " . count($binlogs) . " (e.g., {$binlogs[0]})\n";

    // Find most recent backup meta for this DB
    $pattern = $backupFolder . DIRECTORY_SEPARATOR . ($label === 'interfacing' ? 'interfacing' : 'intelis') . "-*.meta.json";
    $candidates = glob($pattern) ?: [];
    usort($candidates, static fn($a, $b) => filemtime($b) <=> filemtime($a));

    if (empty($candidates)) {
        echo "\nNo .meta.json found. Run a new backup to enable PITR suggestions.\n";
        return;
    }

    $metaPath = $candidates[0];
    $meta = json_decode(file_get_contents($metaPath), true);
    $fromFile = $meta['snapshot']['end']['file'] ?? null;
    $fromPos  = $meta['snapshot']['end']['position'] ?? null;

    echo "\nLatest backup snapshot:\n";
    echo "  Meta:    " . basename($metaPath) . "\n";
    echo "  From:    file={$fromFile} pos={$fromPos}\n";

    // Sample command
    $suggestTo = gmdate('Y-m-d H:i:s');
    $cmd = sprintf(
        "php bin/%s pitr-restore --from-meta=\"%s\" --to=\"%s\" --target=%s",
        basename(__FILE__),
        $metaPath,
        $suggestTo,
        $label
    );
    echo "\nSample PITR command:\n  {$cmd}\n";
}

function handlePitrRestore(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $args): void
{
    $opts = parseArgs($args);
    $metaFile = $opts['from-meta'] ?? null;
    $to = $opts['to'] ?? null;
    $targetOption = $opts['target'] ?? 'intelis';

    if (!$metaFile || !$to) {
        throw new SystemException('Usage: pitr-restore --from-meta=<file.meta.json> --to="YYYY-MM-DD HH:MM:SS" [--target=intelis|interfacing]');
    }

    if (!is_file($metaFile)) {
        // allow relative from backup folder
        $candidate = $backupFolder . DIRECTORY_SEPARATOR . $metaFile;
        if (!is_file($candidate)) {
            throw new SystemException("Meta file not found: {$metaFile}");
        }
        $metaFile = realpath($candidate);
    }

    $config = resolveTargetConfig($targetOption, $intelisDbConfig, $interfacingDbConfig);
    $label  = normalizeTargetLabel($targetOption);

    $meta = json_decode(file_get_contents($metaFile), true);
    if (!$meta || empty($meta['snapshot']['end']['file'])) {
        throw new SystemException('Invalid or incomplete meta file (missing snapshot.end).');
    }

    // Starting point
    $startFile = $meta['snapshot']['end']['file'];
    $startPos  = (int)($meta['snapshot']['end']['position'] ?? 4);
    $gtidMode  = strtoupper((string)($meta['server']['gtid_mode'] ?? 'OFF')) === 'ON';
    $executed  = $meta['snapshot']['end']['gtid_executed'] ?? ($meta['server']['gtid_executed'] ?? '');

    echo "PITR replay for {$label}\n";
    echo str_repeat('-', 50) . "\n";
    echo "From snapshot: file={$startFile} pos={$startPos}\n";
    echo "To timestamp:  {$to}\n";
    echo "GTID mode:     " . ($gtidMode ? 'ON' : 'OFF') . "\n\n";

    // Build mysqlbinlog args (remote read; avoids local FS access to binlog dir)
    $binlogs = getBinlogList($config);
    if (empty($binlogs)) {
        throw new SystemException('No binlogs available on the server.');
    }

    // Include files from $startFile onwards
    $selected = [];
    $include = false;
    foreach ($binlogs as $f) {
        if ($f === $startFile) $include = true;
        if ($include) $selected[] = $f;
    }
    if (empty($selected)) {
        throw new SystemException("Start binlog {$startFile} not found on server. Retention may have purged it.");
    }

    // Producer: mysqlbinlog ...
    $producer = [
        'mysqlbinlog',
        '--read-from-remote-server',
        '--host=' . $config['host']
    ];
    if (!empty($config['port'])) $producer[] = '--port=' . $config['port'];
    $producer[] = '--user=' . $config['username'];
    $producer[] = '--stop-datetime=' . $to;

    if ($gtidMode && !empty($executed)) {
        $producer[] = '--exclude-gtids=' . $executed;
    } else {
        $producer[] = '--start-position=' . max(4, $startPos);
    }

    foreach ($selected as $f) $producer[] = $f;

    // Consumer: mysql (apply to target DB)
    $consumer = [
        'mysql',
        '--host=' . $config['host']
    ];
    if (!empty($config['port'])) $consumer[] = '--port=' . $config['port'];
    $consumer[] = '--user=' . $config['username'];
    // no --database : binlog has USE statements

    echo "Replaying " . count($selected) . " binlog file(s)...\n";
    echo "  Starting at " . ($gtidMode ? 'GTID-exclude set' : "pos {$startPos}") . "\n";
    echo "  This may take a while. Do not interrupt.\n\n";

    // Optimize settings before PITR replay
    echo "Optimizing settings for PITR replay...\n";

    $sqlLogBinDisabled = false;
    try {
        runMysqlQuery($config, 'SET SESSION SQL_LOG_BIN=0;');
        $sqlLogBinDisabled = true;
        echo "  - Binary logging disabled (won't generate binlog during replay)\n";
    } catch (\Throwable $e) {
        echo "  - Binary logging still active (requires SUPER privilege to disable)\n";
    }

    try {
        runMysqlQuery($config, 'SET FOREIGN_KEY_CHECKS=0;');
        runMysqlQuery($config, 'SET UNIQUE_CHECKS=0;');
        runMysqlQuery($config, 'SET AUTOCOMMIT=0;');
        echo "  - Foreign key checks: disabled\n";
        echo "  - Unique checks: disabled\n";
        echo "  - Autocommit: disabled\n";
    } catch (\Throwable $e) {
        echo "  Warning: Could not set optimization flags: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // Use env for password (avoid printing it)
    $env = ['MYSQL_PWD' => $config['password'] ?? ''];

    try {
        runPipeline($producer, $consumer, $env);

        // Commit and restore settings
        echo "\nCommitting transactions...\n";
        runMysqlQuery($config, 'COMMIT;');

        echo "Restoring normal database settings...\n";
        runMysqlQuery($config, 'SET FOREIGN_KEY_CHECKS=1;');
        runMysqlQuery($config, 'SET UNIQUE_CHECKS=1;');
        runMysqlQuery($config, 'SET AUTOCOMMIT=1;');

        if ($sqlLogBinDisabled) {
            try {
                runMysqlQuery($config, 'SET SESSION SQL_LOG_BIN=1;');
                echo "  - Binary logging re-enabled\n";
            } catch (\Throwable $e) {
                // Ignore errors re-enabling SQL_LOG_BIN
            }
        }

        echo "\n✅ PITR replay completed up to {$to}.\n";
    } catch (\Throwable $e) {
        // Attempt to restore settings even on failure
        echo "\nPITR replay failed, attempting to restore database settings...\n";
        try {
            runMysqlQuery($config, 'COMMIT;');
        } catch (\Throwable $ignored) {
        }
        try {
            runMysqlQuery($config, 'SET FOREIGN_KEY_CHECKS=1;');
        } catch (\Throwable $ignored) {
        }
        try {
            runMysqlQuery($config, 'SET UNIQUE_CHECKS=1;');
        } catch (\Throwable $ignored) {
        }
        try {
            runMysqlQuery($config, 'SET AUTOCOMMIT=1;');
        } catch (\Throwable $ignored) {
        }
        if ($sqlLogBinDisabled) {
            try {
                runMysqlQuery($config, 'SET SESSION SQL_LOG_BIN=1;');
            } catch (\Throwable $ignored) {
            }
        }

        throw $e;
    }
}

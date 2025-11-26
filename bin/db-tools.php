#!/usr/bin/env php
<?php

// bin/db-tools.php - Database management CLI tool for InteLIS

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(0);
}

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

use App\Utilities\ArchiveUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\SystemService;
use Ifsnop\Mysqldump as IMysqldump;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;


const DEFAULT_OPERATION = 'backup';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Database dumps routinely exceed the default 25MB safety limit; lift it for this CLI.
ArchiveUtility::setMaxFileSize(null);

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

// Parse arguments properly
$parsed = parseArguments($commandArgs);

$intelisDbConfig = SYSTEM_CONFIG['database'];
$interfacingEnabled = SYSTEM_CONFIG['interfacing']['enabled'] ?? false;
$interfacingDbConfig = $interfacingEnabled ? SYSTEM_CONFIG['interfacing']['database'] : null;
validateConfiguration($intelisDbConfig, $interfacingDbConfig);
try {
    switch ($command) {
        case 'backup':
            handleBackup($backupFolder, $intelisDbConfig, $interfacingDbConfig, $parsed);
            exit(0);
        case 'export':
            handleExport($backupFolder, $intelisDbConfig, $interfacingDbConfig, $parsed);
            exit(0);
        case 'import':
            handleImport($backupFolder, $intelisDbConfig, $interfacingDbConfig, $parsed);
            exit(0);
        case 'list':
            handleList($backupFolder);
            exit(0);
        case 'restore':
            handleRestore($backupFolder, $intelisDbConfig, $interfacingDbConfig, $parsed);
            exit(0);
        case 'verify':
            handleVerify($backupFolder, $parsed);
            exit(0);
        case 'clean':
            handleClean($backupFolder, $parsed);
            handlePurgeBinlogs($intelisDbConfig, $interfacingDbConfig, $parsed);
            exit(0);
        case 'size':
            handleSize($intelisDbConfig, $interfacingDbConfig, $parsed);
            exit(0);
        case 'config-test':
            handleConfigTest($backupFolder, $intelisDbConfig, $interfacingDbConfig, $commandArgs);
            exit(0);
        case 'mysqlcheck':
            handleMysqlCheck($intelisDbConfig, $interfacingDbConfig, $parsed);
            exit(0);
        case 'purge-binlogs':
        case 'purge-binlog':
            handlePurgeBinlogs($intelisDbConfig, $interfacingDbConfig, $parsed);
            exit(0);
        case 'pitr-info':
            handlePitrInfo($backupFolder, $intelisDbConfig, $interfacingDbConfig, $parsed);
            exit(0);
        case 'pitr-restore':
            handlePitrRestore($backupFolder, $intelisDbConfig, $interfacingDbConfig, $parsed);
            exit(0);
        case 'maintain':
            handleMaintain($intelisDbConfig, $interfacingDbConfig, $parsed);
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
            $safeCommand = MiscUtility::sanitizeCliString($command);
            MiscUtility::safeCliEcho("Unknown command: {$safeCommand}");
            echo PHP_EOL;
            printUsage();
            exit(CLI\ERROR);
    }
} catch (\Throwable $e) {
    handleUserFriendlyError($e);
    exit(CLI\ERROR);
}

/**
 * Parse command-line arguments into flags, options, and positional args.
 * 
 * Supports:
 *   - Flags: --skip-safety-backup, --no-fzf
 *   - Options with =: --target=intelis, --keep=7
 *   - Options with space: --target intelis, --keep 7
 *   - Positional args: backup, file.sql
 * 
 * @param array $args Raw arguments (without script name)
 * @return array ['flags' => [...], 'options' => [...], 'positional' => [...]]
 */
function parseArguments(array $args): array
{
    $flags = [];
    $options = [];
    $positional = [];

    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];

        // Option with value: --key=value
        if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
            [$key, $value] = explode('=', $arg, 2);
            $options[$key] = $value;
        }
        // Option with value: --key value (next arg doesn't start with --)
        elseif (str_starts_with($arg, '--') && isset($args[$i + 1]) && !str_starts_with($args[$i + 1], '-')) {
            $options[$arg] = $args[$i + 1];
            $i++; // skip next arg since we consumed it
        }
        // Flag: --flag
        elseif (str_starts_with($arg, '--')) {
            $flags[] = $arg;
        }
        // Positional argument
        else {
            $positional[] = $arg;
        }
    }

    return compact('flags', 'options', 'positional');
}

/**
 * Check if a flag is present in parsed arguments.
 */
function hasFlag(array $parsed, string $flag): bool
{
    return in_array($flag, $parsed['flags'], true);
}

/**
 * Get an option value from parsed arguments.
 */
function getOption(array $parsed, string $key, mixed $default = null): mixed
{
    return $parsed['options'][$key] ?? $default;
}

function isGpgBackupFile(string $path): bool
{
    return str_ends_with(strtolower($path), '.gpg');
}

function isSafetyBackupBasename(string $basename): bool
{
    $lower = strtolower($basename);
    return str_starts_with($lower, 'pre-restore-') || str_starts_with($lower, 'interfacing-pre-restore-');
}

function shouldUseFzf(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $parsed;
    if (hasFlag($parsed, '--no-fzf')) {
        return $cached = false;
    }

    $envDisable = getenv('DBTOOLS_NO_FZF');
    if (is_string($envDisable) && $envDisable !== '') {
        $value = strtolower(trim($envDisable));
        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return $cached = false;
        }
    }

    if (!commandExists('fzf')) {
        return $cached = false;
    }

    $stdin = defined('STDIN') ? STDIN : null;
    $stdout = defined('STDOUT') ? STDOUT : null;

    if ($stdin === null || $stdout === null) {
        return $cached = false;
    }

    if (!stream_isatty($stdin) || !stream_isatty($stdout)) {
        return $cached = false;
    }

    return $cached = true;
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
 * Use fzf to let the user pick a file from the provided list.
 *
 * @param array<int, array{path:string, basename:string, mtime:int, size:int}> $candidates
 */
function selectFileWithFzf(array $candidates, string $header): ?string
{
    if ($candidates === [] || !shouldUseFzf()) {
        return null;
    }

    $inputFile = tempnam(sys_get_temp_dir(), 'dbtools_fzf_in_');
    $outputFile = tempnam(sys_get_temp_dir(), 'dbtools_fzf_out_');

    if ($inputFile === false || $outputFile === false) {
        if ($inputFile !== false) {
            MiscUtility::deleteFile($inputFile);
        }
        if ($outputFile !== false) {
            MiscUtility::deleteFile($outputFile);
        }
        return null;
    }

    $lines = [];
    foreach ($candidates as $index => $candidate) {
        $basename = $candidate['basename'] ?? basename($candidate['path'] ?? '');
        $isSafety = $candidate['is_safety'] ?? (is_string($basename) && isSafetyBackupBasename($basename));
        $labelSuffix = $isSafety ? ' [SAFETY BACKUP]' : '';
        $lines[] = implode("\t", [
            $candidate['path'],
            sprintf(
                '%3d. %s%s  %s  %s',
                $index + 1,
                $basename,
                $labelSuffix,
                date('Y-m-d H:i:s', $candidate['mtime']),
                formatFileSize((int) $candidate['size'])
            ),
        ]);
    }

    file_put_contents($inputFile, implode(PHP_EOL, $lines));

    $cmd = sprintf(
        'OUT=%s; export OUT; ' .
        'cat %s | fzf --ansi --height=80%% --reverse --border ' .
        ' --prompt=%s ' .
        ' --header=%s ' .
        ' --delimiter="\t" --with-nth=2.. ' .
        ' --bind "enter:execute-silent(echo {1} > \"$OUT\")+abort" ',
        escapeshellarg($outputFile),
        escapeshellarg($inputFile),
        escapeshellarg('Select> '),
        escapeshellarg($header)
    );

    $process = Process::fromShellCommandline($cmd, null, buildSafeProcessEnv());
    $process->setTimeout(null);
    if (Process::isTtySupported()) {
        try {
            $process->setTty(true);
        } catch (\RuntimeException) {
            // If TTY cannot be attached, fall back to default pipes.
        }
    }
    $process->run();

    $selection = @file_get_contents($outputFile);

    MiscUtility::deleteFile([$inputFile, $outputFile]);

    $selection = $selection === false ? '' : trim($selection);
    if ($selection === '') {
        return null;
    }

    return $selection;
}

/**
 * Encrypt a compressed file (e.g. .gz, .zst) to .gpg (symmetric AES-256).
 */
function encryptWithGpg(string $gzPath, string $gpgPath, string $passphrase): bool
{
    if (!is_file($gzPath)) {
        return false;
    }
    if (!commandExists('gpg')) {
        throw new SystemException('gpg not found. Please install GnuPG or adjust PATH.');
    }

    $cleanPassphrase = str_replace(["\r", "\n"], '', (string) $passphrase);

    $cmd = [
        'gpg',
        '--batch',
        '--yes',
        '--pinentry-mode',
        'loopback',
        '--passphrase-fd',
        '0',
        '--symmetric',
        '--cipher-algo',
        'AES256',
        '--output',
        $gpgPath,
        $gzPath
    ];

    $process = new Process($cmd, null, buildSafeProcessEnv());
    $process->setTimeout(null);
    $process->setInput($cleanPassphrase . PHP_EOL);
    $process->run();

    $stdout = $process->getOutput();
    $stderr = $process->getErrorOutput();

    if (!$process->isSuccessful()) {
        MiscUtility::deleteFile($gpgPath);
        $msg = (trim($stderr) ?: trim($stdout)) ?: 'Unknown GPG error';
        throw new SystemException("GPG encryption failed: {$msg}");
    }

    return is_file($gpgPath) && filesize($gpgPath) > 0;
}

/**
 * Decrypt *.sql.<compression>.gpg and output a temp *.sql file.
 */
function decryptGpgToTempSql(string $gpgPath, string $passphrase, string $backupFolder): string
{
    if (!is_file($gpgPath)) {
        throw new SystemException('Backup file not found for GPG decrypt.');
    }
    if (!validateSecureFilePath($gpgPath, $backupFolder)) {
        throw new SystemException('GPG backup path is outside the allowed directory.');
    }
    $realGpgPath = realpath($gpgPath);
    if ($realGpgPath === false) {
        throw new SystemException('Unable to resolve GPG backup path.');
    }
    if (!commandExists('gpg')) {
        throw new SystemException('gpg not found. Please install GnuPG or adjust PATH.');
    }

    $tempDir = getTempDir($backupFolder);
    $compressedOutput = $tempDir . DIRECTORY_SEPARATOR . basename($gpgPath, '.gpg');

    $cleanPassphrase = str_replace(["\r", "\n"], '', (string) $passphrase);

    $gpgCmd = [
        'gpg',
        '--batch',
        '--yes',
        '--pinentry-mode',
        'loopback',
        '--passphrase-fd',
        '0',
        '--output',
        $compressedOutput,
        '--decrypt',
        $realGpgPath
    ];

    $process = new Process($gpgCmd, null, buildSafeProcessEnv());
    $process->setTimeout(null);
    $process->setInput($cleanPassphrase . PHP_EOL);
    $process->run();

    $stdout = $process->getOutput();
    $stderr = $process->getErrorOutput();

    if (!$process->isSuccessful() || !is_file($compressedOutput) || filesize($compressedOutput) === 0) {
        MiscUtility::deleteFile($compressedOutput);
        $msg = (trim($stderr) ?: trim($stdout)) ?: 'Unknown GPG error';
        throw new SystemException("Failed to decrypt GPG backup: {$msg}");
    }

    $ext = strtolower(pathinfo($compressedOutput, PATHINFO_EXTENSION));
    if ($ext === 'sql') {
        return $compressedOutput;
    }

    try {
        $sqlPath = ArchiveUtility::decompressToFile($compressedOutput, $tempDir);
    } catch (\Throwable $e) {
        MiscUtility::deleteFile($compressedOutput);
        throw new SystemException('Failed to decompress decrypted backup: ' . $e->getMessage());
    }

    MiscUtility::deleteFile($compressedOutput);

    return $sqlPath;
}

/**
 * Quick structural check that file is a readable GPG container (no passphrase needed).
 */
/**
 * Verify GPG file structure without decrypting
 * 
 * Security: Uses buildSafeProcessEnv() to prevent command injection via HTTP headers
 * Additional validation: Blocks shell metacharacters in file paths
 * 
 * @param string $gpgPath Path to GPG encrypted file
 * @param string|null $allowedDirectory Optional directory constraint for path validation
 * @return bool True if GPG structure is valid, false otherwise
 */
function verifyGpgStructure(string $gpgPath, ?string $allowedDirectory = null): bool
{
    if (!commandExists('gpg')) {
        return false;
    }
    $realPath = realpath($gpgPath);
    if ($realPath === false) {
        return false;
    }

    if ($allowedDirectory !== null && !validateSecureFilePath($realPath, $allowedDirectory)) {
        return false;
    }

    // Additional validation: ensure path doesn't contain shell metacharacters
    if (preg_match('/[;&|`$<>(){}[\]!]/', $realPath)) {
        return false;
    }

    // Build environment without HTTP headers to prevent injection
    $safeEnv = buildSafeProcessEnv();

    $cmd = ['gpg', '--batch', '--list-packets', $realPath];

    $process = new Process($cmd, null, $safeEnv);
    $process->setTimeout(null);
    $process->run();

    // Many gpg 2.4 builds emit non-zero exit even for readable packets.
    $text = strtolower($process->getOutput() . "\n" . $process->getErrorOutput());
    return (
        str_contains($text, 'encrypted data packet') ||
        str_contains($text, 'symkey enc packet') ||
        str_contains($text, 'aes256')
    );
}



function console(): ConsoleOutput
{
    static $out = null;
    if (!$out) {
        $out = new ConsoleOutput();
    }
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
    if ($label) {
        $bar->setMessage($label);
    }
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
    } catch (\Throwable) {
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
            if ($res === '') {
                continue;
            }

            if (stripos($sql, 'SHOW VARIABLES LIKE') === 0) {
                // format: Variable_name\tValue
                foreach (explode("\n", $res) as $line) {
                    $parts = preg_split('/\s+/', trim($line));
                    if (count($parts) >= 2) {
                        $k = strtolower($parts[0]);
                        $v = $parts[1] ?? null;
                        if ($k === 'log_bin') {
                            $out['server']['log_bin'] = $v;
                        }
                        if ($k === 'log_bin_basename') {
                            $out['server']['log_bin_basename'] = $v;
                        }
                        if ($k === 'binlog_format') {
                            $out['server']['binlog_format'] = $v;
                        }
                        if ($k === 'binlog_row_image') {
                            $out['server']['binlog_row_image'] = $v;
                        }
                        if ($k === 'binlog_expire_logs_seconds') {
                            $out['server']['binlog_expire_logs_seconds'] = $v;
                        }
                        if ($k === 'expire_logs_days') {
                            $out['server']['expire_logs_days'] = $v;
                        }
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
        } catch (\Throwable) {
            // non-fatal; leave nulls
        }
    }

    // SHOW MASTER STATUS works both with/without GTID; returns File, Position, Binlog_Do_DB, Binlog_Ignore_DB, Executed_Gtid_Set
    try {
        $master = runMysqlQuery($config, "SHOW MASTER STATUS");
        $lines = array_filter(array_map('trim', explode("\n", trim($master))));
        if ($lines !== []) {
            // Expect: File\tPosition\t...\tExecuted_Gtid_Set
            $parts = preg_split('/\s+/', $lines[0]);
            // guard
            if (count($parts) >= 2) {
                $out['master_status']['file'] = $parts[0] ?? null;
                $out['master_status']['position'] = isset($parts[1]) ? (int) $parts[1] : null;
                // try to find a GTID set; it’s often last column or empty
                $last = end($parts);
                if ($last && stripos($last, ':') !== false) {
                    $out['master_status']['gtid_executed'] = $last;
                }
            }
        }
    } catch (\Throwable) {
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
    import <target> [file]  Import SQL file to database (supports .sql, .sql.gz, .sql.zst, .zip, and .sql.zip)
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
    --no-fzf                Force classic selection prompts even if fzf is available

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

function handleBackup(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $parsed): void
{
    $targetOption = extractTargetOption($parsed);
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

function handleExport(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $parsed): void
{
    if ($parsed["positional"] === []) {
        throw new SystemException('Export target is required. Use: intelis|interfacing');
    }

    $targetOption = $parsed["positional"][0];
    $outputFile = $args[1] ?? null;

    $config = resolveTargetConfig($targetOption, $intelisDbConfig, $interfacingDbConfig);
    $label = normalizeTargetLabel($targetOption);

    if (!$outputFile) {
        $timestamp = date('dmY-His');
        $outputFile = $backupFolder . DIRECTORY_SEPARATOR . "{$label}-export-{$timestamp}.sql";
    } else {
        // If relative path, make it relative to backup folder
        if (!str_contains((string) $outputFile, DIRECTORY_SEPARATOR)) {
            $outputFile = $backupFolder . DIRECTORY_SEPARATOR . $outputFile;
        }

        // Security: Validate output path is within backup folder
        if (!validateSecureFilePath(dirname((string) $outputFile), $backupFolder)) {
            throw new SystemException('Output file must be within the backups directory for security reasons.');
        }
    }

    $safeOutputBasename = MiscUtility::sanitizeCliString(basename((string) $outputFile));
    MiscUtility::safeCliEcho(sprintf('Exporting %s database to %s...', $label, $safeOutputBasename));

    if (!commandExists('mysqldump')) {
        throw new SystemException('mysqldump not found. Please install MySQL client tools.');
    }

    $command = ['mysqldump'];
    $command[] = '--host=' . $config['host'];
    if (!empty($config['port'])) {
        $command[] = '--port=' . $config['port'];
    }
    $command[] = '--user=' . $config['username'];
    $command[] = '--single-transaction';
    $command[] = '--skip-lock-tables';
    $command[] = '--skip-add-locks';      // Don't add LOCK TABLES to output
    $command[] = '--routines';
    $command[] = '--triggers';
    $command[] = '--events';
    $command[] = '--add-drop-table';
    $command[] = '--result-file=' . $outputFile;
    $command[] = $config['db'];

    $env = buildProcessEnv([
        'MYSQL_PWD' => $config['password'] ?? '',
    ]);

    $process = new Process($command, null, $env);
    $process->setTimeout(null);
    $process->run();

    if (!$process->isSuccessful()) {
        $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());
        throw new SystemException("Export failed: {$error}");
    }

    MiscUtility::safeCliEcho('Export completed: ' . MiscUtility::sanitizeCliString($outputFile));
}

function handleImport(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $parsed): void
{
    if (count($parsed["positional"]) < 1) {
        throw new SystemException('Import target is required. Use: intelis|interfacing [file]');
    }

    $targetOption = $parsed["positional"][0];
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
    if (isGpgBackupFile($lower)) {
        echo "Decrypting & decompressing GPG backup...\n";
        $derived = ($config['password'] ?? '') . extractRandomTokenFromBackup($sqlPath);
        try {
            $extractedSqlPath = decryptGpgToTempSql($sqlPath, $derived, $backupFolder);
        } catch (SystemException $e) {
            echo "  Built-in password mechanism failed.\n";
            $userPassword = promptForPassword();
            if ($userPassword === null) {
                throw $e;
            }
            echo "  Trying with user-provided password...\n";
            $extractedSqlPath = decryptGpgToTempSql($sqlPath, $userPassword, $backupFolder);
        }
        TempFileRegistry::register($extractedSqlPath);
    } elseif (isCompressedSqlFile($sqlPath)) {
        echo "Processing compressed SQL file...\n";
        $extractedSqlPath = extractCompressedSqlFile($sqlPath, $backupFolder);
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

    if ($backups === [] && $sqlFiles === []) {
        echo 'No backups or SQL files found in ' . $backupFolder . PHP_EOL;
        return;
    }

    if ($backups !== []) {
        echo "Encrypted backups:\n";
        showBackupsWithIndex($backups);
        echo "\n";
    }

    if ($sqlFiles !== []) {
        echo "SQL files:\n";
        showBackupsWithIndex($sqlFiles);
    }
}

function handleRestore(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $parsed): void
{
    $requestedFile = $parsed['positional'][0] ?? null;
    $skipSafetyBackup = hasFlag($parsed, '--skip-safety-backup');

    $backups = getSortedBackups($backupFolder);
    if (empty($backups)) {
        echo 'No encrypted backups found to restore.' . PHP_EOL;
        return;
    }

    $selectedPath = null;
    if ($requestedFile) {
        $selectedPath = resolveBackupFileSecure($requestedFile, $backupFolder);
        if (!$selectedPath) {
            throw new SystemException("Backup file not found or access denied: {$requestedFile}");
        }
    } else {
        if (!shouldUseFzf()) {
            showBackupsWithIndex($backups, true);
        } else {
            echo "Launching backup selector (safety backups listed last; press Esc to cancel)…\n";
        }
        $selectedPath = promptForBackupSelection($backups, true, true);
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
    } elseif (isGpgBackupFile($lower)) {
        // Structural check only; we can't fully test without passphrase
        $ok = verifyGpgStructure($selectedPath, $backupFolder);
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
        MiscUtility::safeCliEcho('Created: ' . basename($preRestorePath));
    } else {
        echo '⚠ Skipping safety backup (--skip-safety-backup flag used)' . PHP_EOL;
        echo '  WARNING: No backup will be created before restore!' . PHP_EOL;
    }

    MiscUtility::safeCliEcho('Decrypting and extracting backup...');
    if (isGpgBackupFile($lower)) {
        $derived = ($targetConfig['password'] ?? '') . extractRandomTokenFromBackup($selectedPath);
        try {
            $sqlPath = decryptGpgToTempSql($selectedPath, $derived, $backupFolder);
        } catch (SystemException $e) {
            echo "  Built-in password mechanism failed.\n";
            $userPassword = promptForPassword();
            if ($userPassword === null) {
                throw $e;
            }
            echo "  Trying with user-provided password...\n";
            $sqlPath = decryptGpgToTempSql($selectedPath, $userPassword, $backupFolder);
        }
        TempFileRegistry::register($sqlPath);
    } else {
        $sqlPath = extractSqlFromBackupWithFallback($selectedPath, $targetConfig['password'] ?? '', $backupFolder);
        TempFileRegistry::register($sqlPath);
    }

    MiscUtility::safeCliEcho('Resetting ' . $targetLabel . ' database...');
    recreateDatabase($targetConfig);

    MiscUtility::safeCliEcho('Restoring database from ' . $basename . '...');
    importSqlDump($targetConfig, $sqlPath);

    // PITR suggestion (unchanged)
    $metaGuess = preg_replace('/\.(sql\.(gz|zst)\.gpg|sql\.zip)$/', '.meta.json', $selectedPath);
    if (!is_file($metaGuess)) {
        $base = preg_replace('/\.(sql\.(gz|zst)\.gpg|sql\.zip)$/', '', basename($selectedPath));
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
        MiscUtility::safeCliEcho("  $cmd\n");
        echo "Adjust --to to your desired timestamp (YYYY-MM-DD HH:MM:SS, in UTC or with timezone offset).\n";
    } else {
        echo "Note: PITR suggestion unavailable (no .meta.json found for this backup).\n";
    }
}


function handleMysqlCheck(array $intelisDbConfig, ?array $interfacingDbConfig, array $parsed): void
{
    $targetOption = extractTargetOption($parsed);
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

function handlePurgeBinlogs(array $intelisDbConfig, ?array $interfacingDbConfig, array $parsed): void
{
    $targetOption = extractTargetOption($parsed);
    $days = extractDaysOption($parsed, 7);
    $targets = resolveMaintenanceTargets($targetOption, $intelisDbConfig, $interfacingDbConfig);

    $sql = sprintf('PURGE BINARY LOGS BEFORE DATE(NOW() - INTERVAL %d DAY);', $days);

    foreach ($targets as $target) {
        echo sprintf('Purging binary logs older than %d day(s) for %s database...', $days, $target['label']) . PHP_EOL;

        // Get binlog sizes BEFORE purging
        $sizeBefore = getBinlogTotalSize($target['config']);

        $result = runMysqlQuery($target['config'], $sql);
        if ($result !== '') {
            foreach (explode("\n", $result) as $line) {
                if ($line !== '') {
                    echo '  ' . $line . PHP_EOL;
                }
            }
        }

        // Get binlog sizes AFTER purging
        $sizeAfter = getBinlogTotalSize($target['config']);
        $freed = $sizeBefore - $sizeAfter;

        echo '  Log cleanup completed for ' . $target['label'] . PHP_EOL;

        if ($freed > 0) {
            echo '  Freed: ' . formatFileSize($freed) . ' (' . formatFileSize($sizeBefore) . ' → ' . formatFileSize($sizeAfter) . ')' . PHP_EOL;
        } else {
            echo '  No space freed (no old binlogs found)' . PHP_EOL;
        }
    }
}

/**
 * Get total size of all binary logs for a database connection
 */
function getBinlogTotalSize(array $dbConfig): int
{
    try {
        $sql = 'SHOW BINARY LOGS';
        $result = runMysqlQuery($dbConfig, $sql);

        $totalSize = 0;
        $lines = explode("\n", trim($result));

        // Skip header line and parse output
        foreach (array_slice($lines, 1) as $line) {
            if (empty($line)) {
                continue;
            }

            // Output format: "mysql-bin.000001\t1234567"
            $parts = preg_split('/\s+/', $line);
            if (isset($parts[1]) && is_numeric($parts[1])) {
                $totalSize += (int) $parts[1];
            }
        }

        return $totalSize;
    } catch (Exception $e) {
        // If we can't get size, return 0 to avoid breaking the purge operation
        LoggerUtility::logWarning('Warning: Could not calculate binlog size: ' . $e->getMessage());
        return 0;
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

    if ($allFiles === []) {
        echo 'No import files found in ' . $backupFolder . PHP_EOL;
        return null;
    }

    if (shouldUseFzf()) {
        echo "Launching fzf selector (press Esc to cancel)…\n";
        return selectFileWithFzf($allFiles, 'Select import file (Esc to cancel)');
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

function isZipPasswordProtected(string $zipPath, ?string $allowedDirectory = null): bool
{
    $realPath = realpath($zipPath);
    if ($realPath === false) {
        return true;
    }

    if ($allowedDirectory !== null && !validateSecureFilePath($realPath, $allowedDirectory)) {
        return true;
    }

    return ArchiveUtility::isPasswordProtected($realPath);
}

function extractUnprotectedZip(string $zipPath, string $backupFolder): string
{
    $tempDir = getTempDir($backupFolder);

    try {
        return ArchiveUtility::decompressToFile($zipPath, $tempDir);
    } catch (\RuntimeException $e) {
        throw new SystemException('Failed to extract ZIP archive: ' . $e->getMessage());
    }
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
    $tempDir = getTempDir($backupFolder);

    try {
        return ArchiveUtility::extractPasswordProtectedZip($zipPath, $password, $tempDir);
    } catch (\RuntimeException $e) {
        throw new SystemException('Failed to extract password-protected ZIP: ' . $e->getMessage());
    }
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

        if ($password === false || trim((string) $password) === '') {
            return null;
        }

        return trim((string) $password);
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

function confirmSafetyBackupSelection(string $path): bool
{
    $basename = basename($path);
    echo "You selected a pre-restore safety backup ({$basename}). Restore from this snapshot? (y/N): ";
    $input = trim(fgets(STDIN) ?: '');
    return in_array(strtolower($input), ['y', 'yes'], true);
}

function extractSqlFromBackup(string $zipPath, string $dbPassword, string $backupFolder): string
{
    $token = extractRandomTokenFromBackup($zipPath);
    $zipPassword = $dbPassword . $token;
    $tempDir = getTempDir($backupFolder);

    try {
        return ArchiveUtility::extractPasswordProtectedZip($zipPath, $zipPassword, $tempDir);
    } catch (\RuntimeException $e) {
        throw new SystemException('Failed to extract backup: ' . $e->getMessage());
    }
}

function extractRandomTokenFromBackup(string $path): string
{
    $name = basename($path);

    if (str_ends_with($name, '.gpg')) {
        $name = substr($name, 0, -4);
    }

    foreach (['.zst', '.gz', '.zip'] as $suffix) {
        if (str_ends_with($name, $suffix)) {
            $name = substr($name, 0, -strlen($suffix));
            break;
        }
    }

    if (str_ends_with($name, '.sql')) {
        $name = substr($name, 0, -4);
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

    $baseName = implode('-', array_filter($parts));
    $sqlPath = $backupFolder . DIRECTORY_SEPARATOR . $baseName . '.sql';
    $compressedPath = null;
    $gpgPath = null;

    $dsn = sprintf('mysql:host=%s;dbname=%s', $config['host'], $config['db']);
    if (!empty($config['port'])) {
        $dsn .= ';port=' . $config['port'];
    }

    // Snapshot BEFORE dump (for PITR)
    $startSnap = collectBinlogSnapshot($config);

    $tblCount = (int) trim(runMysqlQuery(
        $config,
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$config['db']}'"
    )) ?: null;

    $spinner = MiscUtility::spinnerStart($tblCount ?: null, "Dumping database `{$config['db']}` … this can take a while on large datasets");

    try {
        // Check if mysqldump is available
        if (!commandExists('mysqldump')) {
            throw new SystemException('mysqldump not found. Please install MySQL client tools.');
        }

        // Build mysqldump command
        $command = ['mysqldump'];
        $command[] = '--host=' . $config['host'];
        if (!empty($config['port'])) {
            $command[] = '--port=' . $config['port'];
        }
        $command[] = '--user=' . $config['username'];
        $command[] = '--single-transaction';  // Use InnoDB transactions instead of table locks
        $command[] = '--skip-lock-tables';    // Don't lock tables during backup
        $command[] = '--skip-add-locks';      // Don't add LOCK TABLES statements to SQL output
        $command[] = '--routines';            // Include stored procedures and functions
        $command[] = '--triggers';            // Include triggers
        $command[] = '--events';              // Include events
        $command[] = '--add-drop-table';      // Add DROP TABLE IF EXISTS
        $command[] = '--result-file=' . $sqlPath;
        $command[] = $config['db'];

        $env = buildProcessEnv([
            'MYSQL_PWD' => $config['password'] ?? '',
        ]);

        $process = new Process($command, null, $env);
        $process->setTimeout(null);
        $process->start();

        // Poll process and update spinner
        $lastTick = microtime(true);
        while ($process->isRunning()) {
            usleep(100000); // 100ms
            $now = microtime(true);
            if ($now - $lastTick >= 0.5) {
                MiscUtility::spinnerAdvance($spinner);
                $lastTick = $now;

                // Update message based on file size
                if (file_exists($sqlPath)) {
                    $size = filesize($sqlPath);
                    $spinner->setMessage(sprintf('Dumping… %s', formatFileSize($size)));
                }
            }
        }

        $process->wait();

        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            throw new SystemException("mysqldump failed: {$error}");
        }

        if (!file_exists($sqlPath) || filesize($sqlPath) === 0) {
            throw new SystemException('mysqldump produced an empty file');
        }

        // Compress using the best available backend
        $backend = ArchiveUtility::pickBestBackend();
        $spinner->setMessage(sprintf(
            "Compressing with %s …",
            strtoupper($backend)
        ));
        MiscUtility::spinnerAdvance($spinner);
        $compressedPath = ArchiveUtility::compressFile($sqlPath, $sqlPath, $backend);
        MiscUtility::deleteFile($sqlPath);

        $spinner->setMessage("Encrypting with GPG (AES-256) …");
        MiscUtility::spinnerAdvance($spinner);

        $passphrase = ($config['password'] ?? '') . $randomString;
        $gpgPath = $compressedPath . '.gpg';
        $gpgMade = encryptWithGpg($compressedPath, $gpgPath, $passphrase);
        if (!$gpgMade) {
            MiscUtility::deleteFile($compressedPath);
            throw new SystemException('GPG encryption failed.');
        }
        MiscUtility::deleteFile($compressedPath);
    } catch (\Throwable $e) {
        if (isset($spinner)) {
            MiscUtility::spinnerFinish($spinner);
        }
        MiscUtility::deleteFile($sqlPath);
        if (isset($compressedPath)) {
            MiscUtility::deleteFile($compressedPath);
        }
        if ($gpgPath !== null) {
            MiscUtility::deleteFile($gpgPath);
        }
        throw new SystemException("Failed to create database dump for {$config['db']}: " . $e->getMessage());
    }

    // Snapshot AFTER dump (for PITR)
    $endSnap = collectBinlogSnapshot($config);

    // Write PITR metadata (same format as before)
    $meta = [
        'db' => $config['db'],
        'backup_base' => $baseName,
        'created_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'app_tz' => getAppTimezone(),
        'server' => $endSnap['server'],
        'snapshot' => [
            'start' => $startSnap['master_status'],
            'end' => $endSnap['master_status'],
        ],
        'note' => $note,
        'version' => 1,
    ];
    $metaPath = $backupFolder . DIRECTORY_SEPARATOR . $baseName . '.meta.json';
    file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Warn if PITR won't be available
    if (empty($meta['snapshot']['end']['file'])) {
        $logBinStatus = $meta['server']['log_bin'] ?? 'OFF';
        if ($logBinStatus === 'ON') {
            echo "\n⚠️  Warning: Point-in-Time Recovery (PITR) will not be available for this backup.\n";
            echo "   Binary logging is enabled but no binlog position was captured.\n";
            echo "   This usually means no transactions have been logged yet.\n";
        }
    }

    $spinner->finish();
    return $gpgPath; // returns *.sql.<compression>.gpg
}



// File Operations and Listing Functions

function isCompressedSqlFile(string $path): bool
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['gz', 'zst'], true);
}

function extractCompressedSqlFile(string $path, string $backupFolder): string
{
    $tempDir = getTempDir($backupFolder);
    try {
        return ArchiveUtility::decompressToFile($path, $tempDir);
    } catch (\Throwable $e) {
        throw new SystemException('Failed to decompress SQL backup: ' . $e->getMessage());
    }
}

function getSortedBackups(string $backupFolder): array
{
    $patterns = [
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql.zip',
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql.gz.gpg',
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql.zst.gpg',
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql.gpg',
    ];

    $files = [];
    foreach ($patterns as $pattern) {
        $matches = glob($pattern) ?: [];
        $files = array_merge($files, $matches);
    }

    $backups = [];
    foreach ($files as $file) {
        $basename = basename($file);
        $backups[] = [
            'path' => $file,
            'basename' => $basename,
            'mtime' => @filemtime($file) ?: 0,
            'size' => @filesize($file) ?: 0,
            'is_safety' => isSafetyBackupBasename($basename),
        ];
    }

    usort($backups, static fn($a, $b): int => $b['mtime'] <=> $a['mtime']);
    return $backups;
}


function getSortedSqlFiles(string $backupFolder): array
{
    $patterns = [
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql',
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql.gz',
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql.zst',
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

    usort($sqlFiles, static fn($a, $b): int => $b['mtime'] <=> $a['mtime']);

    return $sqlFiles;
}

function splitBackupsBySafety(array $backups): array
{
    $normal = [];
    $safety = [];

    foreach ($backups as $backup) {
        $entry = $backup;
        $basename = $entry['basename'] ?? basename($entry['path'] ?? '');
        $isSafety = $entry['is_safety'] ?? (is_string($basename) && isSafetyBackupBasename($basename));
        $entry['is_safety'] = $isSafety;
        if ($isSafety) {
            $safety[] = $entry;
        } else {
            $normal[] = $entry;
        }
    }

    return [$normal, $safety];
}

function showBackupsWithIndex(array $backups, bool $groupSafety = false): void
{
    if (!$groupSafety) {
        foreach ($backups as $index => $backup) {
            $position = $index + 1;
            $timestamp = date('Y-m-d H:i:s', $backup['mtime']);
            $size = formatFileSize($backup['size']);
            echo sprintf('[%d] %s  %s  %s', $position, $backup['basename'], $timestamp, $size) . PHP_EOL;
        }
        return;
    }

    [$normal, $safety] = splitBackupsBySafety($backups);

    $index = 1;
    $render = function (array $items) use (&$index): void {
        foreach ($items as $item) {
            $timestamp = date('Y-m-d H:i:s', $item['mtime']);
            $size = formatFileSize($item['size']);
            echo sprintf('[%d] %s  %s  %s', $index, $item['basename'], $timestamp, $size) . PHP_EOL;
            $index++;
        }
    };

    if (!empty($normal)) {
        echo "Regular backups:\n";
        $render($normal);
    }

    if (!empty($safety)) {
        if (!empty($normal)) {
            echo PHP_EOL;
        }
        echo "Safety backups (pre-restore snapshots):\n";
        $render($safety);
    }
}

function promptForBackupSelection(array $backups, bool $groupSafety = false, bool $confirmSafety = false): ?string
{
    [$normal, $safety] = $groupSafety ? splitBackupsBySafety($backups) : [array_values($backups), []];
    $orderedBackups = $groupSafety ? array_merge($normal, $safety) : $normal;

    if (shouldUseFzf()) {
        $header = $groupSafety
            ? 'Select backup (safety backups listed last; Esc to cancel)'
            : 'Select backup (Esc to cancel)';
        $selected = selectFileWithFzf($orderedBackups, $header);
        if ($selected === null) {
            return null;
        }
        if ($confirmSafety && isSafetyBackupBasename(basename($selected)) && !confirmSafetyBackupSelection($selected)) {
            return null;
        }
        return $selected;
    }

    $count = count($orderedBackups);
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

        $selectedPath = $orderedBackups[$index - 1]['path'];

        if ($confirmSafety && isSafetyBackupBasename(basename((string) $selectedPath)) && !confirmSafetyBackupSelection($selectedPath)) {
            return null;
        }

        return $selectedPath;
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

    $env = buildProcessEnv([
        'MYSQL_PWD' => $config['password'] ?? '',
    ]);

    $process = new Process($command, null, $env);
    $process->setTimeout(null);

    $progressBar = null;
    $inputStream = null;
    $progressFinished = false;

    if ($inputData !== null && is_file($inputData)) {
        $source = fopen($inputData, 'rb');
        if ($source === false) {
            throw new SystemException('Could not read the SQL file. Please check file permissions.');
        }

        $fileSize = filesize($inputData) ?: 0;
        $bytesRead = 0;
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
        $inputStream = new InputStream();
        $process->setInput($inputStream);
        $process->start();

        try {
            while (!feof($source)) {
                $chunk = fread($source, 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }

                $inputStream->write($chunk);
                $bytesRead += strlen($chunk);

                if ($progressBar instanceof ProgressBar && $fileSize > 0) {
                    $percent = (int) (($bytesRead / $fileSize) * 100);
                    if ($percent > $lastPercent) {
                        $progressBar->setProgress($percent);
                        $lastPercent = $percent;
                    }
                }
            }
        } finally {
            fclose($source);
            if ($inputStream !== null) {
                $inputStream->close();
            }
            if ($progressBar instanceof ProgressBar && !$progressFinished) {
                $progressBar->finish();
                echo "\n";
                $progressFinished = true;
            }
        }

        $process->wait();
    } else {
        if ($inputData !== null) {
            $process->setInput($inputData);
        }
        $process->run();
    }

    $stdout = $process->getOutput();
    $stderr = $process->getErrorOutput();

    if (!$process->isSuccessful()) {
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

    if (!is_file($sqlFilePath)) {
        throw new SystemException("SQL file not found: $sqlFilePath");
    }

    echo "  Optimizing settings for fast import...\n";

    // Build all SQL to run in a SINGLE session
    $preSQL = [];
    $postSQL = [];

    // Try to disable binary logging (requires SUPER privilege)
    // We'll attempt this and note if it fails
    $preSQL[] = 'SET @original_sql_log_bin = @@SESSION.SQL_LOG_BIN;';
    $preSQL[] = 'SET SESSION SQL_LOG_BIN = IF(@original_sql_log_bin IS NOT NULL, 0, @@SESSION.SQL_LOG_BIN);';

    // Standard optimizations
    $preSQL[] = 'SET @original_foreign_key_checks = @@SESSION.FOREIGN_KEY_CHECKS;';
    $preSQL[] = 'SET @original_unique_checks = @@SESSION.UNIQUE_CHECKS;';
    $preSQL[] = 'SET FOREIGN_KEY_CHECKS=0;';
    $preSQL[] = 'SET UNIQUE_CHECKS=0;';

    // InnoDB performance tuning for imports
    $preSQL[] = 'SET @original_innodb_lock_wait_timeout = @@SESSION.INNODB_LOCK_WAIT_TIMEOUT;';
    $preSQL[] = 'SET SESSION INNODB_LOCK_WAIT_TIMEOUT = 300;';  // Increase lock timeout to 5 minutes

    echo "  - Foreign key checks: disabled\n";
    echo "  - Unique checks: disabled\n";
    echo "  - Lock wait timeout: 5 minutes\n";
    echo "  - Attempting to disable binary logging...\n";

    // Try to set GLOBAL InnoDB optimizations (requires SUPER privilege)
    // These dramatically speed up bulk imports by reducing disk I/O
    $preSQL[] = 'SET @original_innodb_flush_log = @@GLOBAL.INNODB_FLUSH_LOG_AT_TRX_COMMIT;';
    $preSQL[] = 'SET GLOBAL INNODB_FLUSH_LOG_AT_TRX_COMMIT = 0;';  // Don't flush to disk on every commit

    // Post-import: restore settings
    $postSQL[] = 'SET FOREIGN_KEY_CHECKS = @original_foreign_key_checks;';
    $postSQL[] = 'SET UNIQUE_CHECKS = @original_unique_checks;';
    $postSQL[] = 'SET SESSION SQL_LOG_BIN = @original_sql_log_bin;';
    $postSQL[] = 'SET SESSION INNODB_LOCK_WAIT_TIMEOUT = @original_innodb_lock_wait_timeout;';
    $postSQL[] = 'SET GLOBAL INNODB_FLUSH_LOG_AT_TRX_COMMIT = @original_innodb_flush_log;';  // Restore original

    // Build the complete SQL input
    $command = ['mysql'];
    $command[] = '--host=' . $config['host'];
    if (!empty($config['port'])) {
        $command[] = '--port=' . $config['port'];
    }
    $command[] = '--user=' . $config['username'];
    $command[] = '--database=' . $config['db'];
    $charset = $config['charset'] ?? 'utf8mb4';
    $command[] = '--default-character-set=' . $charset;

    $env = buildProcessEnv([
        'MYSQL_PWD' => $config['password'] ?? '',
    ]);

    $process = new Process($command, null, $env);
    $process->setTimeout(null);

    $inputStream = new InputStream();
    $process->setInput($inputStream);
    $process->start();

    try {
        // Send pre-SQL
        foreach ($preSQL as $sql) {
            $inputStream->write($sql . "\n");
        }

        // Stream the SQL file
        $source = fopen($sqlFilePath, 'rb');
        if ($source === false) {
            throw new SystemException('Could not read the SQL file.');
        }

        $fileSize = filesize($sqlFilePath) ?: 0;
        $bytesRead = 0;
        $progressBar = null;

        if ($fileSize > 1048576) {
            $out = console();
            $progressBar = new ProgressBar($out, 100);
            $progressBar->setFormat(" Importing SQL … %current%/%max%  [%bar%]  %elapsed:6s%  %memory:6s%");
            $progressBar->setBarCharacter("<fg=green>█</>");
            $progressBar->setEmptyBarCharacter("░");
            $progressBar->setProgressCharacter("<fg=green>█</>");
            $progressBar->start();
        }

        $lastPercent = 0;
        while (!feof($source)) {
            $chunk = fread($source, 262144); // 256KB chunks for optimal performance
            if ($chunk === false || $chunk === '') {
                break;
            }

            $inputStream->write($chunk);
            $bytesRead += strlen($chunk);

            if ($progressBar instanceof ProgressBar && $fileSize > 0) {
                $percent = (int) (($bytesRead / $fileSize) * 100);
                if ($percent > $lastPercent) {
                    $progressBar->setProgress($percent);
                    $lastPercent = $percent;
                }
            }
        }

        fclose($source);

        if ($progressBar instanceof ProgressBar) {
            $progressBar->finish();
            echo "\n";
        }

        // Now send post-SQL (COMMIT + restore settings)
        // This is where the delay happens - show a spinner with elapsed time
        echo "\n";

        foreach ($postSQL as $sql) {
            $inputStream->write($sql . "\n");
        }

        $inputStream->close();

        // Wait for process to complete and restore settings
        // Show progress feedback
        $out = console();
        $spinner = new ProgressBar($out);
        $spinner->setFormat("  Finalizing import... %elapsed:6s%  [<fg=yellow>%message%</>]");
        $spinner->setMessage('⠋');
        $spinner->start();

        $startTime = microtime(true);
        $lastUpdate = $startTime;
        $currentPhase = '';

        // Poll the process while it's running
        while ($process->isRunning()) {
            usleep(100000); // 100ms
            $spinner->advance();

            $elapsed = microtime(true) - $startTime;

            // Update spinner character
            $chars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
            $spinner->setMessage($chars[(int) ($spinner->getProgress() % count($chars))]);

            // Every 5 seconds, update the phase message
            if (microtime(true) - $lastUpdate > 5.0) {
                $lastUpdate = microtime(true);
                $newPhase = '';

                if ($elapsed < 10) {
                    $newPhase = 'Restoring settings...';
                } elseif ($elapsed < 30) {
                    $newPhase = 'Completing...';
                } else {
                    $newPhase = 'Almost done...';
                }

                if ($newPhase !== $currentPhase) {
                    $currentPhase = $newPhase;
                    $spinner->setFormat("  Committing changes to database... %elapsed:6s%  [<fg=yellow>%message%</>] <fg=cyan>({$currentPhase})</>");
                }
            }
        }

        $spinner->finish();
        echo "\n";

        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();

        if (!$process->isSuccessful()) {
            $errorMessage = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
            throw new SystemException("Database import failed: {$errorMessage}");
        }

        echo "  ✅ Import completed successfully\n";
        echo "  ✅ Database settings restored\n";

    } catch (\Throwable $e) {
        if (isset($inputStream)) {
            $inputStream->close();
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
        ['label' => 'OPTIMIZE', 'args' => ['--optimize']],
        ['label' => 'ANALYZE', 'args' => ['--analyze']],
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

        $env = buildProcessEnv([
            'MYSQL_PWD' => $config['password'] ?? '',
        ]);

        $process = new Process($command, null, $env);
        $process->setTimeout(null);
        $process->run();

        $stdout = $process->getOutput() ?: '';
        $stderr = $process->getErrorOutput() ?: '';

        if (!$process->isSuccessful()) {
            $msg = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
            throw new SystemException("Database maintenance failed during {$step['label']}: {$msg}");
        }

        $out = trim($stdout);
        $combined[] = $out !== '' ? "[{$step['label']}]\n{$out}" : "[{$step['label']}] OK";
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
    if (!preg_match('/^\w+$/', (string) $charset)) {
        throw new SystemException('Invalid database charset in configuration.');
    }

    $collation = $config['collation'] ?? null;
    if ($collation !== null && $collation !== '' && !preg_match('/^\w+$/', (string) $collation)) {
        throw new SystemException('Invalid database collation in configuration.');
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
function extractTargetOption(array $parsed): ?string
{
    // Check positional arguments first
    foreach ($parsed['positional'] as $arg) {
        $candidate = strtolower($arg);
        // Accept legacy names but they'll be normalized
        if (in_array($candidate, ['intelis', 'vlsm', 'primary', 'default', 'interfacing', 'interface', 'both', 'all'], true)) {
            return $candidate;
        }
    }

    // Check --target option
    $target = getOption($parsed, '--target');
    if ($target !== null) {
        $candidate = strtolower($target);
        if (in_array($candidate, ['intelis', 'vlsm', 'primary', 'default', 'interfacing', 'interface', 'both', 'all'], true)) {
            return $candidate;
        }
    }

    return null;
}

function extractDaysOption(array $parsed, int $default): int
{
    $days = getOption($parsed, '--days');
    if ($days !== null) {
        $value = (int) $days;
        if ($value < 1 && $default !== 0) {
            throw new SystemException('Days value must be greater than zero.');
        }
        return $value;
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

/**
 * Build a safe process environment that excludes HTTP headers
 * to prevent command injection attacks via user-controlled input.
 */
function buildSafeProcessEnv(array $extra = []): array
{
    $env = [];

    // Whitelist of safe environment variables
    $safeVars = [
        'PATH',
        'HOME',
        'USER',
        'SHELL',
        'LANG',
        'LC_ALL',
        'LC_CTYPE',
        'TERM',
        'PWD',
        'TMPDIR',
        'TMP',
        'TEMP',
        'TZ',
        'LOGNAME'
    ];

    if (isset($_ENV) && is_array($_ENV) && $_ENV !== []) {
        foreach ($safeVars as $var) {
            if (isset($_ENV[$var])) {
                $env[$var] = $_ENV[$var];
            }
        }
    } else {
        // Only copy safe, non-HTTP variables from $_SERVER
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && in_array($key, $safeVars, true)) {
                $env[$key] = (string) $value;
            }
        }
    }

    // Ensure PATH is always set
    if (empty($env['PATH'])) {
        $path = getenv('PATH');
        if (!is_string($path) || $path === '') {
            $path = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/homebrew/bin:/opt/homebrew/sbin';
        }
        $env['PATH'] = $path;
    }

    // Apply any extra variables (sanitized)
    foreach ($extra as $key => $value) {
        if ($value === null) {
            unset($env[$key]);
        } elseif (is_string($key) && preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
            // Only allow alphanumeric keys with underscores
            $env[$key] = (string) $value;
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
    if ($sourceFile === '') {
        return null;
    }

    $candidates = [];

    if (str_contains($sourceFile, DIRECTORY_SEPARATOR)) {
        $candidates[] = $sourceFile;
    } else {
        $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile;

        $lower = strtolower($sourceFile);
        $hasExt = preg_match('/\.(sql(\.(gz|zst)(\.gpg)?)?|zip)$/', $lower);

        if (!$hasExt) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql.gz';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql.zst';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql.gz.gpg';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql.zst.gpg';
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
    if ($requested === '') {
        return null;
    }

    $candidates = [];

    if (str_contains($requested, DIRECTORY_SEPARATOR)) {
        $candidates[] = $requested;
    } else {
        $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested;

        if (!preg_match('/\.(sql\.(gz|zst)\.gpg|sql\.zip)$/i', $requested)) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested . '.sql.gz.gpg';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested . '.sql.zst.gpg';
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
                MiscUtility::deleteFile($file);
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

    LoggerUtility::logError($e->getMessage(), [
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
function handleMaintain(array $intelisDbConfig, ?array $interfacingDbConfig, array $parsed): void
{
    $targetOption = extractTargetOption($parsed);
    $days = extractDaysOption($parsed, 7);
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
function handleVerify(string $backupFolder, array $parsed): void
{
    $backupFile = $parsed["positional"][0] ?? null;

    if ($backupFile === null) {
        $backups = getSortedBackups($backupFolder);
        if ($backups === []) {
            echo 'No backups found to verify.' . PHP_EOL;
            return;
        }
        echo "Select backup to verify:\n";
        showBackupsWithIndex($backups, true);
        $selectedPath = promptForBackupSelection($backups, true);
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

    MiscUtility::safeCliEcho("Verifying backup: {$basename} ({$fileSize})\n");
    echo str_repeat('-', 50) . "\n";
    echo "✅ File exists and is readable\n";

    if (str_ends_with($lower, '.sql.zip')) {
        echo "Checking ZIP integrity... ";
        if (!verifyBackupIntegrity($selectedPath)) {
            echo "❌ FAILED\n";
            echo "  Error: ZIP archive is corrupted or invalid\n";
            exit(CLI\ERROR);
        }
        echo "✅ PASSED\n";

        echo "Checking archive contents... ";
        $zip = new ZipArchive();
        $status = $zip->open($selectedPath);
        if ($status !== true) {
            echo "❌ FAILED\n";
            MiscUtility::safeCliEcho("  Error: Cannot open archive (code: {$status})\n");
            exit(CLI\ERROR);
        }
        $sqlFound = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && str_ends_with(strtolower($name), '.sql')) {
                $sqlFound = true;
                $stat = $zip->statIndex($i);
                $sqlSize = $stat ? formatFileSize($stat['size']) : 'unknown';
                echo "✅ PASSED\n";
                MiscUtility::safeCliEcho("  Found: {$name} ({$sqlSize})\n");
                break;
            }
        }
        if (!$sqlFound) {
            echo "❌ FAILED\n";
            echo "  Error: No SQL file found in archive\n";
            $zip->close();
            exit(CLI\ERROR);
        }
        $zip->close();

        echo "Checking encryption... ";
        echo isZipPasswordProtected($selectedPath, $backupFolder)
            ? "✅ Password protected (AES-256)\n"
            : "⚠ WARNING: Archive is not password protected\n";
    } elseif (isGpgBackupFile($lower)) {
        echo "Checking GPG structure... ";
        if (!verifyGpgStructure($selectedPath, $backupFolder)) {
            echo "❌ FAILED\n";
            echo "  Error: GPG packet structure unreadable\n";
            exit(CLI\ERROR);
        }
        echo "✅ PASSED\n";
        echo "Encryption: ✅ GPG symmetric (AES-256 expected)\n";
        $innerExt = strtolower(pathinfo(substr($selectedPath, 0, -4), PATHINFO_EXTENSION));
        $innerLabel = match ($innerExt) {
            'zst' => 'zstd (detected from filename)',
            'gz' => 'gzip (detected from filename)',
            'zip' => 'zip (detected from filename)',
            'sql' => 'none (raw SQL)',
            default => 'unknown',
        };
        echo "Inner compression: {$innerLabel}\n";
    } else {
        echo "❌ Unsupported file type for verification\n";
        exit(CLI\ERROR);
    }

    echo str_repeat('-', 50) . "\n";
    echo "✅ Backup verification PASSED\n";
    echo "\nBackup is valid and can be restored.\n";
}

/**
 * Clean old backups based on retention policy.
 * - Always retains a safety floor of the N most recent backups (default 3; override via INTELIS_DB_BACKUP_MIN_KEEP)
 * - Deletes matching *.meta.json alongside each removed backup
 * - Sweeps orphan *.meta.json files that no longer have a corresponding archive
 */
function handleClean(string $backupFolder, array $parsed): void
{
    $keepCount = extractKeepOption($parsed);
    $keepDays = extractDaysOption($parsed, 0);

    if ($keepCount === null && $keepDays === 0) {
        throw new SystemException('Please specify --keep=N or --days=N for retention policy');
    }

    $backups = getSortedBackups($backupFolder); // returns *.sql.zip and *.sql.{gz|zst}.gpg (sorted DESC by mtime)
    if ($backups === []) {
        echo "No backups found to clean.\n";
        return;
    }

    // Safety floor: keep at least N newest backups, no matter what
    $minKeepEnv = getenv('INTELIS_DB_BACKUP_MIN_KEEP');
    $minKeep = is_numeric($minKeepEnv) ? max((int) $minKeepEnv, 1) : 3;

    echo "Backup cleanup (safety floor: keep at least {$minKeep})\n";
    echo str_repeat('-', 50) . "\n";
    echo "Total backups: " . count($backups) . "\n";

    $toDelete = [];

    if ($keepCount !== null) {
        // Keep mode: effective keep is max(user keep, safety floor)
        $effectiveKeep = max((int) $keepCount, $minKeep);

        if (count($backups) <= $effectiveKeep) {
            echo "Retention policy: Keep {$keepCount} most recent (effective {$effectiveKeep} with safety floor)\n";
            echo "Action: Nothing to delete (have " . count($backups) . " backups)\n";
            return;
        }

        // Backups are sorted newest->oldest; delete everything after the effective keep
        $toDelete = array_slice($backups, $effectiveKeep);
        echo "Retention policy: Keep {$keepCount} most recent (effective {$effectiveKeep})\n";
    } elseif ($keepDays > 0) {
        // Days mode: select by age first
        $cutoffTime = time() - $keepDays * 86400;
        foreach ($backups as $b) {
            if ($b['mtime'] < $cutoffTime) {
                $toDelete[] = $b;
            }
        }

        echo "Retention policy: Keep backups newer than {$keepDays} day(s)\n";

        if ($toDelete === []) {
            echo "Action: Nothing to delete (all backups are within retention period)\n";
            return;
        }

        // Apply safety floor: ensure we leave at least $minKeep newest backups overall
        // We can delete at most (total - minKeep) items; pick the oldest ones first.
        $maxDeletable = max(0, count($backups) - $minKeep);

        // Sort candidates oldest->newest so we keep the relatively newer ones if we need to trim
        usort($toDelete, static fn($a, $b): int => $a['mtime'] <=> $b['mtime']);

        if (count($toDelete) > $maxDeletable) {
            $toDelete = array_slice($toDelete, 0, $maxDeletable);
            echo "(Safety floor applied: retaining at least {$minKeep} newest backups)\n";
        }

        if ($toDelete === []) {
            echo "Action: Nothing to delete after safety floor\n";
            return;
        }
    }

    echo "Backups to delete: " . count($toDelete) . "\n";
    echo str_repeat('-', 50) . "\n";

    // Show what will be deleted (including meta size if present)
    $totalSize = 0;
    foreach ($toDelete as $backup) {
        $ageDays = (int) floor((time() - $backup['mtime']) / 86400);
        $size = (int) $backup['size'];

        $basePath = preg_replace('/\.(sql\.(gz|zst)\.gpg|sql\.zip)$/i', '', (string) $backup['path']);
        $metaPath = $basePath . '.meta.json';
        if (is_file($metaPath)) {
            $size += (int) @filesize($metaPath);
        }

        $totalSize += $size;
        echo sprintf(
            "  %s (%s incl. meta, %d days old)\n",
            $backup['basename'],
            formatFileSize($size),
            $ageDays
        );
    }

    echo str_repeat('-', 50) . "\n";
    echo "Total space to free (incl. meta): " . formatFileSize($totalSize) . "\n\n";

    // Delete files immediately (non-interactive)
    echo "Proceeding with deletion...\n";

    $deleted = 0;
    $failed = 0;
    $metaDeleted = 0;

    foreach ($toDelete as $backup) {
        $path = $backup['path'];
        $basePath = preg_replace('/\.(sql\.(gz|zst)\.gpg|sql\.zip)$/i', '', (string) $path);
        $metaPath = $basePath . '.meta.json';

        if (MiscUtility::deleteFile($path)) {
            $deleted++;
            echo "✅ Deleted: " . $backup['basename'] . "\n";

            if (is_file($metaPath) && MiscUtility::deleteFile($metaPath)) {
                $metaDeleted++;
                echo "   └─ 🧹 Removed meta: " . basename($metaPath) . "\n";
            }
        } else {
            $failed++;
            echo "❌ Failed to delete: " . $backup['basename'] . "\n";
        }
    }

    // Sweep orphan meta files (no matching archive)
    $metaFiles = glob($backupFolder . DIRECTORY_SEPARATOR . '*.meta.json') ?: [];
    $orphanMeta = 0;
    $orphanFreed = 0;

    if ($metaFiles !== []) {
        // Build a set of basenames for existing archives (after deletions)
        $existing = [];
        foreach (getSortedBackups($backupFolder) as $b) {
            $existing[preg_replace('/\.(sql\.(gz|zst)\.gpg|sql\.zip)$/i', '', (string) $b['path'])] = true;
        }

        foreach ($metaFiles as $m) {
            $base = preg_replace('/\.meta\.json$/i', '', $m);
            if (empty($existing[$base])) {
                $sz = (int) @filesize($m);
                if (MiscUtility::deleteFile($m)) {
                    $orphanMeta++;
                    $orphanFreed += $sz;
                    echo "🧹 Removed orphan meta: " . basename($m) . "\n";
                }
            }
        }
    }

    echo str_repeat('-', 50) . "\n";
    echo "Cleanup complete: {$deleted} deleted, {$failed} failed\n";
    if ($metaDeleted > 0) {
        echo "Paired PITR meta removed: {$metaDeleted}\n";
    }
    if ($orphanMeta > 0) {
        echo "Orphan PITR meta removed: {$orphanMeta} (" . formatFileSize($orphanFreed) . ")\n";
        // Count orphan meta into “freed” tally for visibility
        $totalSize += $orphanFreed;
    }

    if ($deleted > 0 || $orphanMeta > 0) {
        echo "Freed (incl. meta): " . formatFileSize($totalSize) . "\n";
    }
}



/**
 * Extract --keep option from arguments
 */
function extractKeepOption(array $parsed): ?int
{
    $keep = getOption($parsed, '--keep');
    if ($keep !== null) {
        $value = (int) $keep;
        if ($value < 1) {
            throw new SystemException('Keep value must be greater than zero.');
        }
        return $value;
    }

    return null;
}


/**
 * Show database size information
 */
function handleSize(array $intelisDbConfig, ?array $interfacingDbConfig, array $parsed): void
{
    $targetOption = extractTargetOption($parsed);
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
            MiscUtility::safeCliEcho("❌ Failed to get size information: " . $e->getMessage() . "\n\n");
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
            if (trim($line) === '') {
                continue;
            }

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

        if ($missing !== []) {
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
            MiscUtility::safeCliEcho("   " . $e->getMessage() . "\n");
            $allPassed = false;
        }

        // Test 4: Database permissions
        echo "4. Database permissions... ";
        try {
            testDatabasePermissions($config);
            echo "✅ PASSED\n";
        } catch (SystemException $e) {
            echo "❌ FAILED\n";
            MiscUtility::safeCliEcho("   " . $e->getMessage() . "\n");
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
    } catch (SystemException) {
        throw new SystemException('Cannot list tables');
    }
}

function parseArgs(array $args): array
{
    $out = [];
    foreach ($args as $a) {
        if (preg_match('/^--([^=]+)=(.*)$/', (string) $a, $m)) {
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
        if (isset($parts[0]) && ($parts[0] !== '' && $parts[0] !== '0')) {
            $files[] = $parts[0];
        }
    }
    return $files;
}

function runPipeline(array $producer, array $consumer, array $env = []): int
{
    $env = buildProcessEnv($env);
    $producerCmd = implode(' ', array_map('escapeshellarg', $producer));
    $consumerCmd = implode(' ', array_map('escapeshellarg', $consumer));
    $pipeline = $producerCmd . ' | ' . $consumerCmd;

    $process = Process::fromShellCommandline($pipeline, null, $env);
    $process->setTimeout(null);
    $process->run();

    if (!$process->isSuccessful()) {
        $msg = trim($process->getErrorOutput()) !== '' ? $process->getErrorOutput() : $process->getOutput();
        throw new SystemException("PITR pipeline failed: {$msg}");
    }
    return 0;
}

function handlePitrInfo(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $parsed): void
{
    $targetOption = extractTargetOption($parsed) ?? 'intelis';
    $config = resolveTargetConfig($targetOption, $intelisDbConfig, $interfacingDbConfig);
    $label = normalizeTargetLabel($targetOption);

    echo "PITR readiness for {$label}\n";
    echo str_repeat('-', 50) . "\n";

    $snap = collectBinlogSnapshot($config);
    if (strtoupper((string) $snap['server']['log_bin']) !== 'ON' && $snap['server']['log_bin'] !== '1') {
        echo "❌ Binary logging is OFF. Enable log_bin to use PITR.\n";
        return;
    }
    echo "✅ Binary logging: ON\n";
    echo "  Server UUID: " . ($snap['server']['uuid'] ?? 'unknown') . "\n";
    echo "  Binlog base: " . ($snap['server']['log_bin_basename'] ?? 'unknown') . "\n";
    echo "  GTID mode:   " . ($snap['server']['gtid_mode'] ?? 'OFF') . "\n";

    $binlogs = getBinlogList($config);
    if ($binlogs === []) {
        echo "❌ No binary logs listed by server.\n";
        return;
    }
    echo "  Binlogs on server: " . count($binlogs) . " (e.g., {$binlogs[0]})\n";

    // Find most recent backup meta for this DB
    $pattern = $backupFolder . DIRECTORY_SEPARATOR . ($label === 'interfacing' ? 'interfacing' : 'intelis') . "-*.meta.json";
    $candidates = glob($pattern) ?: [];
    usort($candidates, static fn($a, $b): int => filemtime($b) <=> filemtime($a));

    if ($candidates === []) {
        echo "\nNo .meta.json found. Run a new backup to enable PITR suggestions.\n";
        return;
    }

    $metaPath = $candidates[0];
    $meta = json_decode(file_get_contents($metaPath), true);
    $fromFile = $meta['snapshot']['end']['file'] ?? null;
    $fromPos = $meta['snapshot']['end']['position'] ?? null;

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

function handlePitrRestore(string $backupFolder, array $intelisDbConfig, ?array $interfacingDbConfig, array $parsed): void
{
    $opts = parseArgs($parsed);
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
    $label = normalizeTargetLabel($targetOption);

    $meta = json_decode(file_get_contents($metaFile), true);
    if (!$meta || empty($meta['snapshot']['end']['file'])) {
        $logBinStatus = $meta['server']['log_bin'] ?? 'UNKNOWN';

        echo "❌ Point-in-Time Recovery is not available for this backup.\n\n";
        echo "Reason: Binary log position was not captured during backup creation.\n";
        echo "Binary logging status: {$logBinStatus}\n\n";

        if ($logBinStatus === 'ON') {
            echo "This can happen when:\n";
            echo "  • Binary logging was just enabled but no transactions have been logged yet\n";
            echo "  • All binary log files were purged before the backup\n";
            echo "  • MySQL couldn't provide SHOW MASTER STATUS output\n\n";
            echo "To enable PITR for future backups:\n";
            echo "  1. Ensure binary logging is enabled (it is)\n";
            echo "  2. Perform some database operations to generate binlog entries\n";
            echo "  3. Create a new backup - it will capture binlog positions\n";
        } else {
            echo "Binary logging is not enabled on this MySQL server.\n";
            echo "To enable PITR, you need to:\n";
            echo "  1. Enable binary logging in MySQL configuration\n";
            echo "  2. Restart MySQL\n";
            echo "  3. Create a new backup\n";
        }

        exit(1);
    }

    // Starting point
    $startFile = $meta['snapshot']['end']['file'];
    $startPos = (int) ($meta['snapshot']['end']['position'] ?? 4);
    $gtidMode = strtoupper((string) ($meta['server']['gtid_mode'] ?? 'OFF')) === 'ON';
    $executed = $meta['snapshot']['end']['gtid_executed'] ?? ($meta['server']['gtid_executed'] ?? '');

    echo "PITR replay for {$label}\n";
    echo str_repeat('-', 50) . "\n";
    echo "From snapshot: file={$startFile} pos={$startPos}\n";
    echo "To timestamp:  {$to}\n";
    echo "GTID mode:     " . ($gtidMode ? 'ON' : 'OFF') . "\n\n";

    // Build mysqlbinlog args (remote read; avoids local FS access to binlog dir)
    $binlogs = getBinlogList($config);
    if ($binlogs === []) {
        throw new SystemException('No binlogs available on the server.');
    }

    // Include files from $startFile onwards
    $selected = [];
    $include = false;
    foreach ($binlogs as $f) {
        if ($f === $startFile) {
            $include = true;
        }
        if ($include) {
            $selected[] = $f;
        }
    }
    if ($selected === []) {
        throw new SystemException("Start binlog {$startFile} not found on server. Retention may have purged it.");
    }

    // Producer: mysqlbinlog ...
    $producer = [
        'mysqlbinlog',
        '--read-from-remote-server',
        '--host=' . $config['host']
    ];
    if (!empty($config['port'])) {
        $producer[] = '--port=' . $config['port'];
    }
    $producer[] = '--user=' . $config['username'];
    $producer[] = '--stop-datetime=' . $to;

    $producer[] = $gtidMode && !empty($executed) ? '--exclude-gtids=' . $executed : '--start-position=' . max(4, $startPos);

    foreach ($selected as $f)
        $producer[] = $f;

    // Consumer: mysql (apply to target DB)
    $consumer = [
        'mysql',
        '--host=' . $config['host']
    ];
    if (!empty($config['port'])) {
        $consumer[] = '--port=' . $config['port'];
    }
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
        MiscUtility::safeCliEcho("  Warning: Could not set optimization flags: " . $e->getMessage() . "\n");
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
            } catch (\Throwable) {
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
        } catch (\Throwable) {
        }
        if ($sqlLogBinDisabled) {
            try {
                runMysqlQuery($config, 'SET SESSION SQL_LOG_BIN=1;');
            } catch (\Throwable) {
            }
        }

        throw $e;
    }
}
#!/usr/bin/env php
<?php

// bin/db-tools.php

if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . '/../bootstrap.php');

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use Ifsnop\Mysqldump as IMysqldump;
use App\Registries\ContainerRegistry;

const DEFAULT_OPERATION = 'backup';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$backupFolder = APPLICATION_PATH . '/../backups';
if (!is_dir($backupFolder)) {
    MiscUtility::makeDirectory($backupFolder);
}
$backupFolder = realpath($backupFolder) ?: $backupFolder;
$backupFolder = rtrim($backupFolder, DIRECTORY_SEPARATOR);

$arguments = $_SERVER['argv'] ?? [];
array_shift($arguments); // remove script name
$command = strtolower($arguments[0] ?? DEFAULT_OPERATION);
$commandArgs = array_slice($arguments, 1);

$mainConfig = SYSTEM_CONFIG['database'];
$interfacingEnabled = SYSTEM_CONFIG['interfacing']['enabled'] ?? false;
$interfacingConfig = $interfacingEnabled ? SYSTEM_CONFIG['interfacing']['database'] : null;

try {
    switch ($command) {
        case 'backup':
            handleBackup($backupFolder, $mainConfig, $interfacingConfig);
            exit(0);
        case 'list':
            handleList($backupFolder);
            exit(0);
        case 'restore':
            handleRestore($backupFolder, $mainConfig, $interfacingConfig, $commandArgs[0] ?? null);
            exit(0);
        case 'mysqlcheck':
            handleMysqlCheck($mainConfig, $interfacingConfig, $commandArgs);
            exit(0);
        case 'purge-binlogs':
        case 'purge-binlog':
            handlePurgeBinlogs($mainConfig, $interfacingConfig, $commandArgs);
            exit(0);
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
    LoggerUtility::log('error', $e->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $e->getTraceAsString()
    ]);
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

function printUsage(): void
{
    $script = basename(__FILE__);
    echo <<<USAGE
Usage: php bin/{$script} [command] [options]

Commands:
    backup              Create a new encrypted backup (default)
    list                List available backups
    restore [file]      Restore a backup; if no file supplied, an interactive selector is shown
    mysqlcheck [target] Run mysqlcheck optimize/analyze for the selected database(s)
    purge-binlogs [target] [--days=N]
                        Purge binary logs older than N days (default 7)
    help                Show this help message
USAGE;
}

function handleBackup(string $backupFolder, array $mainConfig, ?array $interfacingConfig): void
{
    echo "Creating main database backup...\n";
    $mainZip = createBackupArchive('vlsm', $mainConfig, $backupFolder);
    echo '  Created: ' . basename($mainZip) . PHP_EOL;

    if ($interfacingConfig) {
        echo "Creating interfacing database backup...\n";
        $interfacingZip = createBackupArchive('interfacing', $interfacingConfig, $backupFolder);
        echo '  Created: ' . basename($interfacingZip) . PHP_EOL;
    }
}

function handleList(string $backupFolder): void
{
    $backups = getSortedBackups($backupFolder);

    if (empty($backups)) {
        echo 'No backups found in ' . $backupFolder . PHP_EOL;
        return;
    }

    showBackupsWithIndex($backups);
}

function handleRestore(string $backupFolder, array $mainConfig, ?array $interfacingConfig, ?string $requestedFile): void
{
    $backups = getSortedBackups($backupFolder);
    if (empty($backups)) {
        echo 'No backups found to restore.' . PHP_EOL;
        return;
    }

    $selectedPath = null;

    if ($requestedFile) {
        $selectedPath = resolveBackupFile($requestedFile, $backupFolder);
        if (!$selectedPath) {
            throw new SystemException(sprintf('Backup file "%s" could not be found in %s', $requestedFile, $backupFolder));
        }
    } else {
        showBackupsWithIndex($backups);
        $selectedPath = promptForBackupSelection($backups);
        if ($selectedPath === null) {
            echo 'Restore cancelled.' . PHP_EOL;
            return;
        }
    }

    $basename = basename($selectedPath);
    $targetKey = detectDatabaseKey($basename);

    if ($targetKey === 'interfacing') {
        if (!$interfacingConfig) {
            throw new SystemException('Selected backup targets the interfacing database, but interfacing is not configured.');
        }
        $targetConfig = $interfacingConfig;
        $targetLabel = 'interfacing';
        $backupPrefix = 'interfacing-pre-restore';
    } else {
        $targetConfig = $mainConfig;
        $targetLabel = 'main';
        $backupPrefix = 'pre-restore-vlsm';
    }

    echo 'Creating safety backup of current ' . $targetLabel . ' database before restore...' . PHP_EOL;
    $note = 'restoreof-' . slugifyForFilename($basename, 32);
    $preRestoreZip = createBackupArchive($backupPrefix, $targetConfig, $backupFolder, $note);
    echo '  Created: ' . basename($preRestoreZip) . PHP_EOL;

    echo 'Decrypting and extracting backup...' . PHP_EOL;
    $sqlPath = extractSqlFromBackup($selectedPath, $targetConfig['password'] ?? '', $backupFolder);

    echo 'Resetting ' . $targetLabel . ' database...' . PHP_EOL;
    recreateDatabase($targetConfig);

    echo 'Restoring database from ' . $basename . '...' . PHP_EOL;
    importSqlDump($targetConfig, $sqlPath);

    if (is_file($sqlPath)) {
        unlink($sqlPath);
    }

    echo 'Restore completed successfully.' . PHP_EOL;
}

function handleMysqlCheck(array $mainConfig, ?array $interfacingConfig, array $args): void
{
    $targetOption = extractTargetOption($args);
    $targets = resolveMaintenanceTargets($targetOption, $mainConfig, $interfacingConfig);

    foreach ($targets as $target) {
        echo 'Running mysqlcheck for ' . $target['label'] . ' database...' . PHP_EOL;
        $output = runMysqlCheckCommand($target['config']);
        if ($output !== '') {
            foreach (explode("\n", $output) as $line) {
                if ($line !== '') {
                    echo '  ' . $line . PHP_EOL;
                }
            }
        }
        echo '  Completed for ' . $target['label'] . PHP_EOL;
    }
}

function handlePurgeBinlogs(array $mainConfig, ?array $interfacingConfig, array $args): void
{
    $targetOption = extractTargetOption($args);
    $days = extractDaysOption($args, 7);
    $targets = resolveMaintenanceTargets($targetOption, $mainConfig, $interfacingConfig);

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
        echo '  Completed for ' . $target['label'] . PHP_EOL;
    }
}

function showBackupsWithIndex(array $backups): void
{
    echo "Available backups:\n";
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

function getSortedBackups(string $backupFolder): array
{
    $pattern = $backupFolder . DIRECTORY_SEPARATOR . '*.sql.zip';
    $files = glob($pattern) ?: [];

    $backups = [];
    foreach ($files as $file) {
        $backups[] = [
            'path' => $file,
            'basename' => basename($file),
            'mtime' => @filemtime($file) ?: 0,
            'size' => @filesize($file) ?: 0,
        ];
    }

    usort($backups, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);

    return $backups;
}

function resolveBackupFile(string $requested, string $backupFolder): ?string
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
        if (!str_ends_with($requested, '.zip')) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested . '.zip';
        }
        if (!str_ends_with($requested, '.sql.zip')) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested . '.sql.zip';
        }
    }

    $backupFolderReal = realpath($backupFolder) ?: $backupFolder;
    $backupFolderReal = rtrim($backupFolderReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved && str_starts_with($resolved, $backupFolderReal) && is_file($resolved)) {
            return $resolved;
        }
    }

    return null;
}

function detectDatabaseKey(string $basename): string
{
    $tokens = explode('-', strtolower($basename));
    return in_array('interfacing', $tokens, true) ? 'interfacing' : 'main';
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

function createBackupArchive(string $prefix, array $config, string $backupFolder, ?string $note = null): string
{
    $randomString = MiscUtility::generateRandomString(12);
    $parts = [$prefix, date('dmYHis')];
    if ($note) {
        $parts[] = $note;
    }
    $parts[] = $randomString;

    $baseName = implode('-', array_filter($parts));
    $sqlFileName = $backupFolder . DIRECTORY_SEPARATOR . $baseName . '.sql';

    $dsn = sprintf('mysql:host=%s;dbname=%s', $config['host'], $config['db']);
    if (!empty($config['port'])) {
        $dsn .= ';port=' . $config['port'];
    }

    $dump = new IMysqldump\Mysqldump($dsn, $config['username'], $config['password'] ?? '');
    $dump->start($sqlFileName);

    $zipPath = $sqlFileName . '.zip';
    $zip = new ZipArchive();
    $zipStatus = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($zipStatus !== true) {
        throw new SystemException(sprintf('Failed to create zip archive. (Status code: %s)', $zipStatus));
    }

    $zipPassword = ($config['password'] ?? '') . $randomString;
    if (!$zip->setPassword($zipPassword)) {
        $zip->close();
        throw new SystemException('Set password failed');
    }

    $baseNameSql = basename($sqlFileName);
    if (!$zip->addFile($sqlFileName, $baseNameSql)) {
        $zip->close();
        throw new SystemException(sprintf('Add file failed: %s', $sqlFileName));
    }

    if (!$zip->setEncryptionName($baseNameSql, ZipArchive::EM_AES_256)) {
        $zip->close();
        throw new SystemException(sprintf('Set encryption failed: %s', $baseNameSql));
    }

    $zip->close();
    @unlink($sqlFileName);

    return $zipPath;
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

function extractRandomTokenFromBackup(string $zipPath): string
{
    $name = basename($zipPath);
    if (str_ends_with($name, '.zip')) {
        $name = substr($name, 0, -4);
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

function importSqlDump(array $config, string $sqlFilePath): void
{
    if (!commandExists('mysql')) {
        throw new SystemException('mysql command not found on PATH.');
    }

    $command = ['mysql'];
    $command[] = '--host=' . $config['host'];
    if (!empty($config['port'])) {
        $command[] = '--port=' . $config['port'];
    }
    $command[] = '--user=' . $config['username'];
    $charset = $config['charset'] ?? 'utf8mb4';
    $command[] = '--default-character-set=' . $charset;
    $command[] = $config['db'];

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
        throw new SystemException('Failed to open mysql process for restore.');
    }

    $source = fopen($sqlFilePath, 'rb');
    if (!$source) {
        fclose($pipes[0]);
        proc_close($process);
        throw new SystemException('Failed to open extracted SQL file.');
    }

    while (!feof($source)) {
        $chunk = fread($source, 8192);
        if ($chunk === false) {
            fclose($source);
            fclose($pipes[0]);
            proc_close($process);
            throw new SystemException('Failed to read SQL dump during restore.');
        }
        fwrite($pipes[0], $chunk);
    }

    fclose($source);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        throw new SystemException(sprintf('mysql exited with status %s. %s', $exitCode, trim($stderr)));
    }
}

function runMysqlCheckCommand(array $config): string
{
    if (!commandExists('mysqlcheck')) {
        throw new SystemException('mysqlcheck command not found on PATH.');
    }

    $command = ['mysqlcheck'];
    $command[] = '--host=' . $config['host'];
    if (!empty($config['port'])) {
        $command[] = '--port=' . $config['port'];
    }
    $command[] = '--user=' . $config['username'];
    $charset = $config['charset'] ?? 'utf8mb4';
    $command[] = '--default-character-set=' . $charset;
    $command[] = '--optimize';
    $command[] = '--auto-repair';
    $command[] = '--analyze';
    $command[] = $config['db'];

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
        throw new SystemException('Failed to open mysqlcheck process.');
    }

    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        $message = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
        throw new SystemException(sprintf('mysqlcheck exited with status %s. %s', $exitCode, $message));
    }

    return trim($stdout);
}

function runMysqlQuery(array $config, string $sql): string
{
    if (!commandExists('mysql')) {
        throw new SystemException('mysql command not found on PATH.');
    }

    $command = ['mysql'];
    $command[] = '--host=' . $config['host'];
    if (!empty($config['port'])) {
        $command[] = '--port=' . $config['port'];
    }
    $command[] = '--user=' . $config['username'];
    $charset = $config['charset'] ?? 'utf8mb4';
    $command[] = '--default-character-set=' . $charset;
    $command[] = '--batch';
    $command[] = '--raw';
    $command[] = '--silent';
    $command[] = '--execute=' . $sql;

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
        throw new SystemException('Failed to open mysql process for query execution.');
    }

    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        $message = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
        throw new SystemException(sprintf('mysql exited with status %s. %s', $exitCode, $message));
    }

    return trim($stdout);
}

function recreateDatabase(array $config): void
{
    $dbName = $config['db'] ?? '';
    if ($dbName === '') {
        throw new SystemException('Database name not configured.');
    }

    $sanitizedDb = '`' . str_replace('`', '``', $dbName) . '`';

    $charset = $config['charset'] ?? 'utf8mb4';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $charset)) {
        throw new SystemException('Invalid database charset configuration.');
    }

    $collation = $config['collation'] ?? null;
    if ($collation !== null && $collation !== '') {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $collation)) {
            throw new SystemException('Invalid database collation configuration.');
        }
    }

    $clauses = ' CHARACTER SET ' . $charset;
    if ($collation) {
        $clauses .= ' COLLATE ' . $collation;
    }

    $sql = sprintf(
        'DROP DATABASE IF EXISTS %1$s; CREATE DATABASE %1$s%2$s;',
        $sanitizedDb,
        $clauses
    );

    runMysqlQuery($config, $sql);
}

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
        if (in_array($candidate, ['main', 'primary', 'default', 'interfacing', 'interface', 'both', 'all'], true)) {
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

function resolveMaintenanceTargets(?string $targetOption, array $mainConfig, ?array $interfacingConfig): array
{
    $normalized = $targetOption ?? 'main';

    if (in_array($normalized, ['both', 'all'], true)) {
        if (!$interfacingConfig) {
            throw new SystemException('Interfacing database not configured; cannot target both databases.');
        }

        return [
            ['label' => 'main', 'config' => $mainConfig],
            ['label' => 'interfacing', 'config' => $interfacingConfig],
        ];
    }

    if (in_array($normalized, ['interfacing', 'interface'], true)) {
        if (!$interfacingConfig) {
            throw new SystemException('Interfacing database not configured.');
        }

        return [
            ['label' => 'interfacing', 'config' => $interfacingConfig],
        ];
    }

    return [
        ['label' => 'main', 'config' => $mainConfig],
    ];
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

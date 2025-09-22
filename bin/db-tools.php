#!/usr/bin/env php
<?php

// bin/db-tools.php - Database management CLI tool for InteLIS

if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . '/../bootstrap.php';

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
validateConfiguration($mainConfig, $interfacingConfig);
try {
    switch ($command) {
        case 'backup':
            handleBackup($backupFolder, $mainConfig, $interfacingConfig, $commandArgs);
            exit(0);
        case 'export':
            handleExport($backupFolder, $mainConfig, $interfacingConfig, $commandArgs);
            exit(0);
        case 'import':
            handleImport($backupFolder, $mainConfig, $interfacingConfig, $commandArgs);
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
    handleUserFriendlyError($e);
    exit(1);
}

function printUsage(): void
{
    $script = basename(__FILE__);
    echo <<<USAGE
Usage: php bin/{$script} [command] [options]

Commands:
    backup [target]         Create encrypted backup(s) (default: both if interfacing enabled, main otherwise)
    export <target> [file]  Export database as plain SQL file
                           target: main|interfacing
                           file: optional output filename
    import <target> [file]  Import SQL file to database (supports .sql, .sql.gz, .zip, and .sql.zip)
                           target: main|interfacing
                           file: path to file (optional - shows selection if omitted)
    list                    List available backups and SQL files
    restore [file]          Restore encrypted backup; shows selection if no file specified
    mysqlcheck [target]     Run database maintenance for the selected database(s)
    purge-binlogs [target] [--days=N]
                           Clean up binary logs older than N days (default 7)
    help                    Show this help message

Target options:
    main                    Main VLSM database (default)
    interfacing            Interfacing database
    both, all              Both databases (backup/mysqlcheck/purge-binlogs only)

Examples:
    php bin/{$script} backup main
    php bin/{$script} export main vlsm_backup.sql
    php bin/{$script} import interfacing
    php bin/{$script} import main backup.sql.zip
    php bin/{$script} restore
    php bin/{$script} mysqlcheck both

Note: For security, all file operations are restricted to the backups directory.
USAGE;
}

function validateConfiguration(array $mainConfig, ?array $interfacingConfig): void
{
    $required = ['host', 'username', 'db'];
    
    foreach ($required as $field) {
        if (empty($mainConfig[$field])) {
            throw new SystemException("Main database configuration missing: {$field}");
        }
    }
    
    if ($interfacingConfig) {
        foreach ($required as $field) {
            if (empty($interfacingConfig[$field])) {
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

function handleBackup(string $backupFolder, array $mainConfig, ?array $interfacingConfig, array $args): void
{
    $targetOption = extractTargetOption($args);
    $targets = resolveBackupTargets($targetOption, $mainConfig, $interfacingConfig);

    foreach ($targets as $target) {
        echo "Creating {$target['label']} database backup...\n";
        $prefix = $target['label'] === 'interfacing' ? 'interfacing' : 'main';
        $zip = createBackupArchive($prefix, $target['config'], $backupFolder);
        echo '  Created: ' . basename($zip) . PHP_EOL;
    }
}

function handleExport(string $backupFolder, array $mainConfig, ?array $interfacingConfig, array $args): void
{
    if (empty($args)) {
        throw new SystemException('Export target is required. Use: main|interfacing');
    }

    $targetOption = $args[0];
    $outputFile = $args[1] ?? null;

    $config = resolveTargetConfig($targetOption, $mainConfig, $interfacingConfig);
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

function handleImport(string $backupFolder, array $mainConfig, ?array $interfacingConfig, array $args): void
{
    if (count($args) < 1) {
        throw new SystemException('Import target is required. Use: main|interfacing [file]');
    }

    $targetOption = $args[0];
    $sourceFile = $args[1] ?? null;

    $config = resolveTargetConfig($targetOption, $mainConfig, $interfacingConfig);
    $label = normalizeTargetLabel($targetOption);

    // If no file specified, show interactive selection
    if ($sourceFile === null) {
        $sqlPath = promptForImportFileSelection($backupFolder);
        if ($sqlPath === null) {
            echo 'Import cancelled.' . PHP_EOL;
            return;
        }
    } else {
        // Resolve file path with security validation
        $sqlPath = resolveImportFileSecure($sourceFile, $backupFolder);
        if (!$sqlPath) {
            throw new SystemException("Import file not found or access denied: {$sourceFile}");
        }
    }

    $fileExtension = strtolower(pathinfo($sqlPath, PATHINFO_EXTENSION));
    $isZipFile = in_array($fileExtension, ['zip', 'gz'], true);

    echo "Creating safety backup of current {$label} database before import...\n";
    $note = 'pre-import-' . date('His');
    $preImportZip = createBackupArchive($label . '-backup', $config, $backupFolder, $note);
    echo '  Created: ' . basename($preImportZip) . PHP_EOL;

    if ($fileExtension === 'gz') {
        echo "Processing gzipped SQL file...\n";
        $extractedSqlPath = extractGzipFile($sqlPath, $backupFolder);
        TempFileRegistry::register($extractedSqlPath);
    } elseif ($isZipFile) {
        $isPasswordProtected = isZipPasswordProtected($sqlPath);

        if ($isPasswordProtected) {
            echo "Processing password-protected archive...\n";
            $extractedSqlPath = extractSqlFromBackupWithFallback($sqlPath, $config['password'] ?? '', $backupFolder);
            TempFileRegistry::register($extractedSqlPath);
        } else {
            echo "Processing unprotected archive...\n";
            $extractedSqlPath = extractUnprotectedZip($sqlPath, $backupFolder);
            TempFileRegistry::register($extractedSqlPath);
        }
    } else {
        echo "Processing SQL file...\n";
        $extractedSqlPath = $sqlPath;
    }

    echo "Resetting {$label} database...\n";
    recreateDatabase($config);

    echo "Importing data to {$label} database...\n";
    importSqlDump($config, $extractedSqlPath);

    // Cleanup is handled by TempFileRegistry shutdown function
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

function handleRestore(string $backupFolder, array $mainConfig, ?array $interfacingConfig, ?string $requestedFile): void
{
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
        showBackupsWithIndex($backups);
        $selectedPath = promptForBackupSelection($backups);
        if ($selectedPath === null) {
            echo 'Restore cancelled.' . PHP_EOL;
            return;
        }
    }

    // Integrity check
    if (!verifyBackupIntegrity($selectedPath)) {
        echo "Warning: Backup file may be corrupted. Continue anyway? (y/N): ";
        $input = trim(fgets(STDIN) ?: '');
        if (strtolower($input) !== 'y') {
            echo 'Restore cancelled due to integrity check failure.' . PHP_EOL;
            return;
        }
    }

    $basename = basename($selectedPath);
    $targetKey = detectDatabaseKey($basename);

    if ($targetKey === 'interfacing') {
        if (!$interfacingConfig) {
            throw new SystemException('The backup targets the interfacing database, but it is not configured.');
        }
        $targetConfig = $interfacingConfig;
        $targetLabel = 'interfacing';
        $backupPrefix = 'interfacing-pre-restore';
    } else {
        $targetConfig = $mainConfig;
        $targetLabel = 'main';
        $backupPrefix = 'pre-restore-main';
    }

    echo 'Creating safety backup of current ' . $targetLabel . ' database before restore...' . PHP_EOL;
    $note = 'restoreof-' . slugifyForFilename($basename, 32);
    $preRestoreZip = createBackupArchive($backupPrefix, $targetConfig, $backupFolder, $note);
    echo '  Created: ' . basename($preRestoreZip) . PHP_EOL;

    echo 'Decrypting and extracting backup...' . PHP_EOL;
    $sqlPath = extractSqlFromBackupWithFallback($selectedPath, $targetConfig['password'] ?? '', $backupFolder);
    TempFileRegistry::register($sqlPath);

    echo 'Resetting ' . $targetLabel . ' database...' . PHP_EOL;
    recreateDatabase($targetConfig);

    echo 'Restoring database from ' . $basename . '...' . PHP_EOL;
    importSqlDump($targetConfig, $sqlPath);

    echo 'Restore completed successfully.' . PHP_EOL;
}

function handleMysqlCheck(array $mainConfig, ?array $interfacingConfig, array $args): void
{
    $targetOption = extractTargetOption($args);
    $targets = resolveMaintenanceTargets($targetOption, $mainConfig, $interfacingConfig);

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

    try {
        $dump = new IMysqldump\Mysqldump($dsn, $config['username'], $config['password'] ?? '');
        $dump->start($sqlFileName);
    } catch (\Exception $e) {
        throw new SystemException("Failed to create database dump for {$config['db']}: " . $e->getMessage());
    }

    $zipPath = $sqlFileName . '.zip';
    $zip = new ZipArchive();
    $zipStatus = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($zipStatus !== true) {
        @unlink($sqlFileName);
        throw new SystemException(sprintf('Failed to create zip archive. (Status code: %s)', $zipStatus));
    }

    $zipPassword = ($config['password'] ?? '') . $randomString;
    if (!$zip->setPassword($zipPassword)) {
        $zip->close();
        @unlink($sqlFileName);
        throw new SystemException('Failed to set password for backup archive');
    }

    $baseNameSql = basename($sqlFileName);
    if (!$zip->addFile($sqlFileName, $baseNameSql)) {
        $zip->close();
        @unlink($sqlFileName);
        throw new SystemException(sprintf('Failed to add SQL file to archive: %s', $sqlFileName));
    }

    if (!$zip->setEncryptionName($baseNameSql, ZipArchive::EM_AES_256)) {
        $zip->close();
        @unlink($sqlFileName);
        throw new SystemException(sprintf('Failed to encrypt file in archive: %s', $baseNameSql));
    }

    $zip->close();
    @unlink($sqlFileName);

    return $zipPath;
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
            $lastProgress = 0;

            while (!feof($source)) {
                $chunk = fread($source, 8192);
                if ($chunk === false) break;

                fwrite($pipes[0], $chunk);
                $bytesRead += strlen($chunk);

                // Show progress every 10% for files larger than 1MB
                if ($fileSize > 1048576 && $fileSize > 0) {
                    $progress = intval(($bytesRead / $fileSize) * 100);
                    if ($progress >= $lastProgress + 10) {
                        echo "  Progress: {$progress}%\n";
                        $lastProgress = $progress;
                    }
                }
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
        // Pass database name in base command for import
        executeMysqlCommand($config, ['mysql', $config['db']], $sqlFilePath);
    } catch (SystemException $e) {
        throw new SystemException('Database import failed: ' . $e->getMessage());
    }
}

/**
 * Run MySQL check command using centralized execution
 */
function runMysqlCheckCommand(array $config): string
{
    if (!commandExists('mysqlcheck')) {
        throw new SystemException('MySQL maintenance tools are not installed. Please install MySQL client tools.');
    }

    try {
        // Database name is included in the base command for mysqlcheck
        return executeMysqlCommand($config, [
            'mysqlcheck',
            '--optimize',
            '--auto-repair',
            '--analyze',
            $config['db']
        ]);
    } catch (SystemException $e) {
        throw new SystemException('Database maintenance failed: ' . $e->getMessage());
    }
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

    $sql = sprintf(
        'DROP DATABASE IF EXISTS %1$s; CREATE DATABASE %1$s%2$s;',
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

function resolveTargetConfig(string $targetOption, array $mainConfig, ?array $interfacingConfig): array
{
    $normalized = normalizeTargetLabel($targetOption);

    if ($normalized === 'interfacing') {
        if (!$interfacingConfig) {
            throw new SystemException('The interfacing database is not configured.');
        }
        return $interfacingConfig;
    }

    return $mainConfig;
}

/**
 * Standardized target label normalization - only 'main' and 'interfacing'
 */
function normalizeTargetLabel(string $targetOption): string
{
    $normalized = strtolower(trim($targetOption));

    // Map all interfacing variants to 'interfacing'
    if (in_array($normalized, ['interfacing', 'interface'], true)) {
        return 'interfacing';
    }

    // Everything else maps to 'main' (including 'vlsm', 'primary', 'default')
    return 'main';
}

function resolveBackupTargets(?string $targetOption, array $mainConfig, ?array $interfacingConfig): array
{
    if ($targetOption === null) {
        // Default behavior: both if interfacing enabled, main otherwise
        if ($interfacingConfig) {
            return [
                ['label' => 'main', 'config' => $mainConfig],
                ['label' => 'interfacing', 'config' => $interfacingConfig],
            ];
        } else {
            return [
                ['label' => 'main', 'config' => $mainConfig],
            ];
        }
    }

    $normalized = strtolower($targetOption);

    if (in_array($normalized, ['both', 'all'], true)) {
        if (!$interfacingConfig) {
            throw new SystemException('The interfacing database is not configured; cannot target both databases.');
        }

        return [
            ['label' => 'main', 'config' => $mainConfig],
            ['label' => 'interfacing', 'config' => $interfacingConfig],
        ];
    }

    if (in_array($normalized, ['interfacing', 'interface'], true)) {
        if (!$interfacingConfig) {
            throw new SystemException('The interfacing database is not configured.');
        }

        return [
            ['label' => 'interfacing', 'config' => $interfacingConfig],
        ];
    }

    return [
        ['label' => 'main', 'config' => $mainConfig],
    ];
}

function resolveMaintenanceTargets(?string $targetOption, array $mainConfig, ?array $interfacingConfig): array
{
    $normalized = $targetOption ?? 'main';

    if (in_array($normalized, ['both', 'all'], true)) {
        if (!$interfacingConfig) {
            throw new SystemException('The interfacing database is not configured; cannot target both databases.');
        }

        return [
            ['label' => 'main', 'config' => $mainConfig],
            ['label' => 'interfacing', 'config' => $interfacingConfig],
        ];
    }

    if (in_array($normalized, ['interfacing', 'interface'], true)) {
        if (!$interfacingConfig) {
            throw new SystemException('The interfacing database is not configured.');
        }

        return [
            ['label' => 'interfacing', 'config' => $interfacingConfig],
        ];
    }

    return [
        ['label' => 'main', 'config' => $mainConfig],
    ];
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
        if (in_array($candidate, ['main', 'vlsm', 'primary', 'default', 'interfacing', 'interface', 'both', 'all'], true)) {
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
    if ($sourceFile === '') {
        return null;
    }

    $candidates = [];

    // If it contains a directory separator, treat as absolute/relative path
    if (str_contains($sourceFile, DIRECTORY_SEPARATOR)) {
        $candidates[] = $sourceFile;
    } else {
        // Look in backup folder only
        $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile;
        
        // Try common extensions if not already present
        $lowerSource = strtolower($sourceFile);
        if (!str_ends_with($lowerSource, '.sql') && !str_ends_with($lowerSource, '.zip') && !str_ends_with($lowerSource, '.gz')) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.zip';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql.zip';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql.gz';
        }
    }

    // Try to find the file with security validation
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && validateSecureFilePath($candidate, $backupFolder)) {
            // Only call realpath once, at the end
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
        if (!str_ends_with($requested, '.zip')) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested . '.zip';
        }
        if (!str_ends_with($requested, '.sql.zip')) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested . '.sql.zip';
        }
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && validateSecureFilePath($candidate, $backupFolder)) {
            // Only call realpath once, at the end
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

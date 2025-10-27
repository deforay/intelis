<?php

declare(strict_types=1);

namespace App\Services;

use Generator;
use RuntimeException;
use App\Services\TestsService;
use App\Utilities\MiscUtility;
use App\Utilities\ArchiveUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;

/**
 * Archives audit_* tables to audit-trail/{testKey}/{unique_id}.csv.{zst|gz|zip}.
 *
 * Features:
 * - Automatically uses best compression backend (zstd → pigz → gzip → zip)
 * - Maintains header alignment with current DB columns (reheaders old rows if needed)
 * - De-duplicates by dt_datetime
 * - Increments 'revision' column
 * - Updates metadata/last_processed_date for bulk runs
 * - Hot archive support for real-time viewing
 * - Public API for reading audit data
 *
 * Usage:
 *   $svc = new AuditArchiveService($db);
 *   
 *   // Write operations
 *   $svc->run();                      // Bulk archive all tables
 *   $svc->run('VL0622018');           // Archive single sample (hot archive)
 *   
 *   // Read operations
 *   $data = $svc->readAudit('vl', 'VL0622018');  // Read with hot archive
 */
final class AuditArchiveService
{
    private string $archiveRoot;
    private string $metadataPath;

    public function __construct(
        private readonly DatabaseService $db
    ) {
        $this->archiveRoot  = ROOT_PATH . '/audit-trail';
        $this->metadataPath = ROOT_PATH . '/metadata/archive.mdata.json';
    }

    /**
     * Run archiving for all audit tables, or only for a specific sample.
     *
     * @param string|null $sampleCode  If provided, archives only rows matching this sample code (hot archive mode)
     * @param callable|null $progress  Optional progress callback(string $msg)
     * @param bool $useLock            Use process lock to avoid concurrent runs
     * @return void
     */
    public function run(?string $sampleCode = null, ?callable $progress = null, bool $useLock = false): void
    {
        $lockFile = null;

        try {
            if ($useLock) {
                $lockFile = MiscUtility::getLockFile(__FILE__ . '.audit-archive');
                if (!MiscUtility::isLockFileExpired($lockFile)) {
                    $this->log($progress, 'Another archiving process is already running; exiting.');
                    return;
                }
                MiscUtility::touchLockFile($lockFile);
                MiscUtility::setupSignalHandler($lockFile);
            }

            MiscUtility::makeDirectory($this->archiveRoot);

            // Load metadata only for bulk mode (not for single sample hot archive)
            $metadata = empty($sampleCode) ? MiscUtility::loadMetadata($this->metadataPath) : [];

            // Build audit-table → testKey map using TestsService
            $auditToKey = $this->buildAuditTableMap();

            $this->runForTables($auditToKey, $sampleCode, $progress, $metadata, $lockFile, $useLock);

            $this->log($progress, 'Archiving process completed.');
        } catch (\Throwable $e) {
            $this->log($progress, 'Archiving error: ' . $e->getMessage());

            LoggerUtility::logError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'last_db_error' => $this->db->getLastError(),
                'last_db_query' => $this->db->getLastQuery(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            if ($lockFile && $useLock) {
                MiscUtility::deleteLockFile($lockFile);
            }
        }
    }

    /**
     * Read audit data for a specific sample.
     * Finds the archive file and returns parsed audit history.
     *
     * @param string $testType Test type key (e.g., 'vl', 'eid', 'covid19')
     * @param string $sampleCode Sample code or unique_id
     * @param bool $hotArchive If true, archives latest data before reading
     * @return array Array of audit records (each record is an associative array)
     */
    public function readAudit(string $testType, string $sampleCode, bool $hotArchive = true): array
    {
        // Hot archive: ensure data is up-to-date
        if ($hotArchive) {
            try {
                $this->run($sampleCode, null, false);
            } catch (\Throwable $e) {
                LoggerUtility::log('warning', "Hot archive failed for {$sampleCode}: " . $e->getMessage());
            }
        }

        // Get unique_id from sample code
        $uniqueId = $this->getUniqueIdFromSampleCode($testType, $sampleCode);
        if (!$uniqueId) {
            return [];
        }

        // Find and read the archive file
        $filePath = $this->resolveAuditFilePath($testType, $uniqueId);
        if (!$filePath) {
            return [];
        }

        return $this->readAuditDataFromCsv($filePath);
    }

    /**
     * Get unique_id for a sample code from the appropriate form table.
     *
     * @param string $testType Test type key
     * @param string $sampleCode Sample code to look up
     * @return string|null Unique ID if found, null otherwise
     */
    public function getUniqueIdFromSampleCode(string $testType, string $sampleCode): ?string
    {
        $tableName = TestsService::getTestTableName($testType);
        if (!$tableName) {
            return null;
        }

        $query = "SELECT unique_id FROM {$tableName} 
                  WHERE sample_code = ? OR remote_sample_code = ? OR external_sample_code = ?";
        $result = $this->db->rawQuery($query, [$sampleCode, $sampleCode, $sampleCode]);

        return $result[0]['unique_id'] ?? null;
    }

    /**
     * Find audit archive file path for a sample.
     * Tries canonical folder first, then test type, then aliases, then global scan.
     *
     * @param string $testType Test type key
     * @param string $uniqueId Unique sample ID
     * @return string|null Full path to archive file if found, null otherwise
     */
    public function resolveAuditFilePath(string $testType, string $uniqueId): ?string
    {
        $tests = TestsService::getTestTypes();

        // Normalize posted key (case-insensitive)
        if (!isset($tests[$testType])) {
            foreach ($tests as $k => $_) {
                if (strcasecmp($k, $testType) === 0) {
                    $testType = $k;
                    break;
                }
            }
        }

        if (!isset($tests[$testType])) {
            return null;
        }

        $table = $tests[$testType]['tableName'] ?? null;
        if (!$table) {
            return null;
        }

        // Find canonical and all aliases for this table
        $canonical = null;
        $aliases = [];
        foreach ($tests as $k => $meta) {
            if (($meta['tableName'] ?? null) === $table) {
                if ($canonical === null) {
                    $canonical = $k;
                } else {
                    $aliases[] = $k;
                }
            }
        }

        // Try directories in priority order
        $tryDirs = [];
        if ($canonical) $tryDirs[] = $canonical;
        $tryDirs[] = $testType;
        foreach ($aliases as $a) $tryDirs[] = $a;

        foreach ($tryDirs as $key) {
            $folder = preg_replace('/[^\w\-]+/', '-', $key);
            $dir = $this->archiveRoot . DIRECTORY_SEPARATOR . $folder;

            // Try extensions in priority order: .zst → .gz → .zip → plain
            foreach (['.csv.zst', '.csv.gz', '.csv.zip', '.csv'] as $ext) {
                $path = $dir . DIRECTORY_SEPARATOR . $uniqueId . $ext;
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        // Final fallback: scan ALL subfolders (legacy layouts)
        foreach (glob($this->archiveRoot . '/*', GLOB_ONLYDIR) as $dir) {
            foreach (['.csv.zst', '.csv.gz', '.csv.zip', '.csv'] as $ext) {
                $path = $dir . DIRECTORY_SEPARATOR . $uniqueId . $ext;
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Read audit data from CSV file (compressed or plain).
     * Uses ArchiveUtility for automatic format detection.
     *
     * @param string $filePath Path to CSV file
     * @return array Array of associative arrays (rows with column names as keys)
     */
    public function readAuditDataFromCsv(string $filePath): array
    {
        if (!is_file($filePath)) {
            return [];
        }

        try {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            // Get content - use ArchiveUtility for compressed files
            if ($ext === 'csv') {
                $content = @file_get_contents($filePath);
                if ($content === false) {
                    return [];
                }
            } else {
                // ArchiveUtility handles .zst, .gz, .zip automatically
                $content = ArchiveUtility::decompressToString($filePath);
            }

            // Parse CSV from temp stream
            $fp = fopen('php://temp', 'r+');
            if ($fp === false) {
                return [];
            }

            fwrite($fp, $content);
            rewind($fp);

            $headers = fgetcsv($fp);
            if ($headers === false || $headers === null) {
                fclose($fp);
                return [];
            }

            $rows = [];
            while (($row = fgetcsv($fp)) !== false) {
                $assoc = [];
                foreach ($headers as $i => $h) {
                    $assoc[$h] = $row[$i] ?? '';
                }
                $rows[] = $assoc;
            }
            fclose($fp);

            return $rows;
        } catch (\Throwable $e) {
            LoggerUtility::log('error', "Failed to read audit CSV {$filePath}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Build mapping of audit tables to test keys.
     *
     * @return array<string, string> ['audit_form_vl' => 'vl', ...]
     */
    private function buildAuditTableMap(): array
    {
        $tests = TestsService::getTestTypes();
        $auditToKey = [];

        foreach ($tests as $key => $meta) {
            $formTable = $meta['tableName'] ?? null;
            if (!$formTable) continue;

            $auditTable = 'audit_' . $formTable;

            // Keep the first key we see for this audit table (canonical)
            // So 'vl' wins over 'recency' if both point to form_vl
            if (!isset($auditToKey[$auditTable])) {
                $auditToKey[$auditTable] = $key;
            }
        }

        return $auditToKey;
    }

    /**
     * Archive specific tables.
     *
     * @param array<string, string> $auditToKey Map of audit table names to test keys
     * @param string|null $sampleCode Single sample code for hot archive, or null for bulk
     * @param callable|null $progress Progress callback
     * @param array $metadata Metadata array (modified by reference in bulk mode)
     * @param string|null $lockFile Lock file path for periodic touching
     * @param bool $useLock Whether lock is being used
     */
    private function runForTables(
        array $auditToKey,
        ?string $sampleCode,
        ?callable $progress,
        array &$metadata,
        ?string $lockFile,
        bool $useLock
    ): void {
        foreach ($auditToKey as $auditTable => $testKey) {
            $lastProcessedDate = $sampleCode ? null : ($metadata[$auditTable]['last_processed_date'] ?? null);

            // Folder is the test key, normalized to filesystem-friendly
            $folderName = preg_replace('/[^\w\-]+/', '-', $testKey);
            $targetDir  = $this->archiveRoot . DIRECTORY_SEPARATOR . $folderName;
            MiscUtility::makeDirectory($targetDir);

            $this->log($progress, "Archiving from {$auditTable} (test={$testKey})...");

            $currentHeaders = $this->getCurrentColumns($auditTable);

            // Get column indexes by name (robust against reordering)
            $idxRevision   = $this->idx($currentHeaders, 'revision') ?? 1;
            $idxDtDatetime = $this->idx($currentHeaders, 'dt_datetime') ?? 2;

            $counter = 0;
            foreach ($this->fetchRecords($auditTable, $lastProcessedDate, 1000, $sampleCode) as $record) {
                $counter++;

                // Touch lock file periodically to prevent expiration
                if ($counter % 10 === 0 && $useLock && $lockFile) {
                    MiscUtility::touchLockFile($lockFile);
                }

                if (!isset($record['unique_id'])) {
                    $this->log($progress, 'Skipping record without unique_id');
                    continue;
                }

                $uniqueId = $record['unique_id'];
                $baseName = $uniqueId;

                // Find existing archive (any format: .zst, .gz, .zip, or plain .csv)
                $existing = $this->resolveExistingCompressed($targetDir, $baseName);

                $headers = $currentHeaders;
                $rows    = [];
                $existingDtSet  = [];
                $lastRevisionNo = 0;

                if ($existing) {
                    $old = $this->readCompressedCsv($existing);

                    // Reheader old rows to match current header order
                    $rows = $this->reheaderIfNeeded($old['headers'], $currentHeaders, $old['rows']);

                    // Build dt_datetime set and find last revision number
                    foreach ($rows as $r) {
                        if (isset($r[$idxDtDatetime])) {
                            $existingDtSet[$this->jsonishScalar((string)$r[$idxDtDatetime])] = true;
                        }
                        if (isset($r[$idxRevision])) {
                            $revRaw = $this->jsonishScalar((string)$r[$idxRevision]);
                            if (is_numeric($revRaw)) {
                                $lastRevisionNo = max($lastRevisionNo, (int)$revRaw);
                            }
                        }
                    }
                }

                // De-duplicate by dt_datetime
                $thisDt = isset($record['dt_datetime']) ? (string)$record['dt_datetime'] : null;
                if ($thisDt !== null && isset($existingDtSet[$thisDt])) {
                    $this->log($progress, "Skipping duplicate dt_datetime={$record['dt_datetime']} for {$uniqueId}");

                    // Update metadata even for duplicates (advance last processed date)
                    if (!$sampleCode && $thisDt !== '') {
                        $lastProcessedDate = $record['dt_datetime'];
                        $this->updateLastProcessedDate($metadata, $auditTable, $lastProcessedDate);
                        MiscUtility::saveMetadata($this->metadataPath, $metadata);
                    }
                    continue;
                }

                // Increment revision
                $record['revision'] = $lastRevisionNo + 1;

                // Append new row
                $rows[] = $this->buildRow($headers, $record);

                // Remove all legacy format variants (converge to best format)
                $this->removeLegacyVariants($targetDir, $baseName);

                // Write compressed CSV using best available backend
                $dstBaseNoExt = $targetDir . DIRECTORY_SEPARATOR . $baseName;
                $out = $this->writeCompressedCsv($dstBaseNoExt, $headers, $rows);

                $this->log($progress, 'Wrote ' . basename($out));

                // Update metadata only in bulk mode
                if (!$sampleCode && !empty($record['dt_datetime'])) {
                    $lastProcessedDate = $record['dt_datetime'];
                    $this->updateLastProcessedDate($metadata, $auditTable, $lastProcessedDate);
                    MiscUtility::saveMetadata($this->metadataPath, $metadata);
                }
            }

            $this->log($progress, "Completed archiving for {$auditTable}.");
        }
    }

    /**
     * Fetch records from audit table in batches.
     *
     * @param string $tableName Audit table name
     * @param string|null $lastProcessedDate Filter records after this date
     * @param int $limit Batch size
     * @param string|null $sampleCode Filter by sample code (for hot archive)
     * @return Generator<array>
     */
    private function fetchRecords(
        string $tableName,
        ?string $lastProcessedDate = null,
        int $limit = 1000,
        ?string $sampleCode = null
    ): Generator {
        $offset = 0;

        while (true) {
            $query = $this->db->connection('default');

            if ($lastProcessedDate) {
                $query->where('dt_datetime', $lastProcessedDate, '>');
            }

            if ($sampleCode) {
                $query->where('unique_id', $sampleCode);
            }

            $query->orderBy('dt_datetime', 'asc');
            $batch = $query->get($tableName, [$offset, $limit]);

            if (!$batch || count($batch) === 0) break;

            foreach ($batch as $record) {
                yield $record;
            }

            $offset += $limit;
        }
    }

    /**
     * Get current column names from table.
     *
     * @param string $tableName Table name
     * @return array<string> Column names in order
     */
    private function getCurrentColumns(string $tableName): array
    {
        $columns = [];
        $result = $this->db->rawQuery("SHOW COLUMNS FROM `$tableName`");

        foreach ($result as $row) {
            $columns[] = $row['Field'];
        }

        return $columns;
    }

    /**
     * Find existing compressed CSV file (any format).
     * Uses simple file existence checks.
     *
     * @param string $dir Directory containing archives
     * @param string $baseName Base filename (without extension)
     * @return string|null Full path if found, null otherwise
     */
    private function resolveExistingCompressed(string $dir, string $baseName): ?string
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        $possibleExtensions = ['.csv.zst', '.csv.gz', '.csv.zip', '.csv'];

        foreach ($possibleExtensions as $ext) {
            $path = $dir . DIRECTORY_SEPARATOR . $baseName . $ext;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Read compressed or plain CSV file.
     * Uses ArchiveUtility for automatic format detection.
     *
     * @param string $path Full path to CSV file (compressed or plain)
     * @return array{headers: array<string>, rows: array<array<string>>}
     */
    private function readCompressedCsv(string $path): array
    {
        try {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            // Get content - use ArchiveUtility for compressed files
            if ($ext === 'csv') {
                $content = @file_get_contents($path);
                if ($content === false) {
                    return ['headers' => [], 'rows' => []];
                }
            } else {
                // ArchiveUtility handles .zst, .gz, .zip automatically
                $content = ArchiveUtility::decompressToString($path);
            }

            // Parse CSV content
            $f = fopen('php://temp', 'r+');
            if ($f === false) {
                return ['headers' => [], 'rows' => []];
            }

            fwrite($f, $content);
            rewind($f);

            $headers = fgetcsv($f);
            if ($headers === false || $headers === null) {
                fclose($f);
                return ['headers' => [], 'rows' => []];
            }

            $rows = [];
            while (($row = fgetcsv($f)) !== false) {
                $rows[] = $row;
            }
            fclose($f);

            return ['headers' => $headers, 'rows' => $rows];
        } catch (\Throwable $e) {
            LoggerUtility::log('error', "Failed to read compressed CSV {$path}: " . $e->getMessage());
            return ['headers' => [], 'rows' => []];
        }
    }

    /**
     * Remap existing rows to new header order.
     * Preserves cell values as-is, fills missing columns with empty string.
     *
     * @param array<string> $existingHeaders Old header order
     * @param array<string> $currentHeaders New header order
     * @param array<array<string>> $oldRows Rows in old order
     * @return array<array<string>> Rows in new order
     */
    private function reheaderIfNeeded(array $existingHeaders, array $currentHeaders, array $oldRows): array
    {
        // If headers match, no reordering needed
        if ($existingHeaders === $currentHeaders) {
            return $oldRows;
        }

        // Build mapping from new index to old index
        $map = [];
        foreach ($currentHeaders as $newIdx => $headerName) {
            $oldIdx = array_search($headerName, $existingHeaders, true);
            $map[$newIdx] = $oldIdx; // false if not found in old headers
        }

        // Remap each row
        $newRows = [];
        foreach ($oldRows as $row) {
            $mapped = [];
            foreach ($map as $newIdx => $oldIdx) {
                $mapped[$newIdx] = ($oldIdx !== false && isset($row[$oldIdx])) ? $row[$oldIdx] : '';
            }
            $newRows[] = $mapped;
        }

        return $newRows;
    }

    /**
     * Write CSV and compress using ArchiveUtility's best backend.
     *
     * @param string $dstBaseNoExt Destination path without .csv extension
     * @param array<string> $headers CSV headers
     * @param array<array<string>> $rows CSV rows
     * @return string Path to compressed file
     * @throws RuntimeException If write fails
     */
    private function writeCompressedCsv(string $dstBaseNoExt, array $headers, array $rows): string
    {
        $tmpCsv = tempnam(sys_get_temp_dir(), 'audit_');
        if ($tmpCsv === false) {
            throw new RuntimeException('Failed to create temp CSV file');
        }

        try {
            $csvH = fopen($tmpCsv, 'w');
            if ($csvH === false) {
                throw new RuntimeException('Failed to open temp CSV file');
            }

            fputcsv($csvH, $headers);
            foreach ($rows as $row) {
                fputcsv($csvH, $row);
            }
            fclose($csvH);

            // ArchiveUtility automatically selects best backend and adds appropriate extension
            $target = $dstBaseNoExt . '.csv';
            return ArchiveUtility::compressFile($tmpCsv, $target);
        } finally {
            @unlink($tmpCsv);
        }
    }

    /**
     * Remove all legacy format variants.
     * Forces convergence to the currently preferred compression format.
     *
     * @param string $dir Directory containing archives
     * @param string $baseName Base filename
     */
    private function removeLegacyVariants(string $dir, string $baseName): void
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        $variants = [
            "$baseName.csv.zst",
            "$baseName.csv.gz",
            "$baseName.csv.zip",
            "$baseName.csv" // legacy plain
        ];

        foreach ($variants as $variant) {
            @unlink($dir . DIRECTORY_SEPARATOR . $variant);
        }
    }

    /**
     * Build CSV row from database record.
     * Encodes arrays/objects as JSON, nulls as empty strings.
     *
     * @param array<string> $headers Column order
     * @param array $record Database record
     * @return array<string> CSV row values
     */
    private function buildRow(array $headers, array $record): array
    {
        $row = [];
        foreach ($headers as $h) {
            $v = $record[$h] ?? null;

            if (is_array($v) || is_object($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            }

            $row[] = $v ?? '';
        }
        return $row;
    }

    /**
     * Update metadata for last processed date.
     *
     * @param array $metadata Metadata array (modified by reference)
     * @param string $tableName Table name
     * @param string $lastProcessedDate Last processed datetime
     */
    private function updateLastProcessedDate(array &$metadata, string $tableName, string $lastProcessedDate): void
    {
        $metadata[$tableName]['last_processed_date'] = $lastProcessedDate;
    }

    /**
     * Get column index by name.
     *
     * @param array<string> $headers Column names
     * @param string $name Column name to find
     * @return int|null Index if found, null otherwise
     */
    private function idx(array $headers, string $name): ?int
    {
        $i = array_search($name, $headers, true);
        return ($i === false) ? null : $i;
    }

    /**
     * Normalize CSV cell value for comparison.
     * Handles JSON-encoded values like "5" → 5.
     *
     * @param string $s Cell value
     * @return string Normalized value
     */
    private function jsonishScalar(string $s): string
    {
        $d = json_decode($s, true);
        if (is_string($d) || is_numeric($d)) {
            return (string)$d;
        }

        // If it looks like a quoted string, strip quotes and unescape
        if (strlen($s) >= 2 && $s[0] === '"' && substr($s, -1) === '"') {
            return stripcslashes(substr($s, 1, -1));
        }

        return $s;
    }

    /**
     * Log message via callback or CLI echo.
     *
     * @param callable|null $progress Progress callback
     * @param string $msg Message to log
     */
    private function log(?callable $progress, string $msg): void
    {
        if ($progress) {
            $progress($msg);
        } elseif (php_sapi_name() === 'cli') {
            echo $msg . PHP_EOL;
        }
    }
}

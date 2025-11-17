<?php

declare(strict_types=1);

namespace App\Services;

use Generator;
use Throwable;
use RuntimeException;
use App\Services\TestsService;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Utilities\ArchiveUtility;

/**
 * Archives audit_* tables to audit-trail/{testKey}/{unique_id}.csv.{zst|gz|zip}.
 *
 * - Always re-writes/normalizes the target file using preferred backend:
 *   zstd (.zst) → pigz/gzip (.gz) → zip (.zip), regardless of original format.
 * - Keeps header alignment with current DB columns (reheaders old rows if needed).
 * - De-duplicates by dt_datetime.
 * - Increments 'revision' (uses column by name; falls back to index 1 for legacy).
 * - Updates metadata/last_processed_date for bulk runs.
 *
 * Usage:
 *   $svc = new AuditArchiveUtility($db);
 *   $svc->run();                // bulk
 *   $svc->run('VL0622018');     // single sample
 */
final readonly class AuditArchiveService
{
    private string $archiveRoot;
    private string $metadataPath;

    public function __construct(
        private DatabaseService $db
    ) {
        $this->archiveRoot  = VAR_PATH . '/audit-trail';
        $this->metadataPath = VAR_PATH . '/metadata/archive.mdata.json';
    }

    /**
     * Run archiving for all audit tables, or only for the given sample.
     *
     * @param string|null $sampleCode  If provided, archives only rows matching this sample code.
     * @param callable|null $progress  Optional progress callback(string $msg).
     * @param bool $useLock            Optional process lock to avoid concurrent runs.
     */
    public function run(?string $sampleCode = null, ?callable $progress = null, bool $useLock = false): void
    {
        $lockFile = null;

        try {
            if ($useLock) {
                $lockFile = MiscUtility::getLockFile(__FILE__ . '.audit-archive');
                if (!MiscUtility::isLockFileExpired($lockFile)) {
                    $this->log($progress, 'Another run is already active; exiting.');
                    return;
                }
                MiscUtility::touchLockFile($lockFile);
                MiscUtility::setupSignalHandler($lockFile);
            }

            MiscUtility::makeDirectory($this->archiveRoot);

            $metadata = $sampleCode === null || $sampleCode === '' || $sampleCode === '0' ? MiscUtility::loadMetadata($this->metadataPath) : [];

            // Build audit-table → testKey map using TestsService
            $tests = TestsService::getTestTypes();
            $auditToKey = [];
            foreach ($tests as $key => $meta) {
                $formTable = $meta['tableName'] ?? null;
                if (!$formTable) {
                    continue;
                }

                $auditTable = 'audit_' . $formTable;

                // keep the first key we see for this audit table (canonical),
                // so 'vl' wins over 'recency' if both point to form_vl
                if (!isset($auditToKey[$auditTable])) {
                    $auditToKey[$auditTable] = $key;
                }
            }


            foreach ($auditToKey as $auditTable => $testKey) {
                $lastProcessedDate = $sampleCode ? null : ($metadata[$auditTable]['last_processed_date'] ?? null);

                // Folder is the test key, normalized to filesystem-friendly
                $folderName = preg_replace('/[^\w\-]+/', '-', (string) $testKey);
                $targetDir  = $this->archiveRoot . DIRECTORY_SEPARATOR . $folderName;
                MiscUtility::makeDirectory($targetDir);

                $this->log($progress, "Archiving from {$auditTable} (test={$testKey})..");

                $currentHeaders = $this->getCurrentColumns($auditTable);

                // Robust indexes by name (fallbacks keep old assumption for legacy files)
                $idxRevision   = $this->idx($currentHeaders, 'revision') ?? 1;
                $idxDtDatetime = $this->idx($currentHeaders, 'dt_datetime') ?? 2;

                $counter = 0;
                foreach ($this->fetchRecords($auditTable, $lastProcessedDate, 1000, $sampleCode) as $record) {
                    $counter++;
                    if ($counter % 10 === 0 && $useLock) {
                        MiscUtility::touchLockFile($lockFile);
                    }

                    if (!isset($record['unique_id'])) {
                        $this->log($progress, 'Skipping record without unique_id');
                        continue;
                    }

                    $uniqueId = $record['unique_id'];
                    $baseName = $uniqueId;               // {uniqueId}.csv.<ext>
                    $existing = $this->resolveExistingCompressed($targetDir, $baseName);

                    $headers = $currentHeaders;
                    $rows    = [];

                    $existingDtSet  = [];
                    $lastRevisionNo = 0;

                    if ($existing) {
                        $old = $this->readCompressedCsv($existing);
                        // Map old rows to current headers (preserve cell strings as-is)
                        $rows = $this->reheaderIfNeeded($old['headers'], $currentHeaders, $old['rows']);

                        // Build dt_datetime set + last revision from the reheadered rows
                        foreach ($rows as $r) {
                            if (isset($r[$idxDtDatetime])) {
                                $existingDtSet[$this->jsonishScalar((string)$r[$idxDtDatetime])] = true;
                            }
                            if (isset($r[$idxRevision])) {
                                $revRaw = $this->jsonishScalar((string)$r[$idxRevision]); // handles "5" → 5
                                if (is_numeric($revRaw)) {
                                    $lastRevisionNo = max($lastRevisionNo, (int)$revRaw);
                                }
                            }
                        }
                    }

                    // De-dup by dt_datetime
                    $thisDt = isset($record['dt_datetime']) ? (string)$record['dt_datetime'] : null;
                    if ($thisDt !== null && isset($existingDtSet[$thisDt])) {
                        $this->log($progress, "Skipping duplicate dt_datetime={$record['dt_datetime']} for {$uniqueId}");
                        if (!$sampleCode && $thisDt !== '') {
                            $lastProcessedDate = $record['dt_datetime'];
                            $this->updateLastProcessedDate($metadata, $auditTable, $lastProcessedDate);
                            MiscUtility::saveMetadata($this->metadataPath, $metadata);
                        }
                        continue;
                    }

                    // Revision
                    $record['revision'] = $lastRevisionNo + 1;

                    // Append new row (values encoded like original writer)
                    $rows[] = $this->buildRow($headers, $record);

                    // Normalize to preferred compression:
                    // Remove old files (any extension), then write fresh compressed file.
                    $dstBaseNoExt = $targetDir . DIRECTORY_SEPARATOR . $baseName;
                    MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$baseName.csv.zst");
                    MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$baseName.csv.gz");
                    MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$baseName.csv.zip");
                    MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$baseName.csv"); // legacy plain

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

            $this->log($progress, 'Archiving process completed.');
        } catch (Throwable $e) {
            $this->log($progress, 'Archiving error: ' . $e->getMessage());
            /** @var DatabaseService|null $db */
            $db = $this->db ?? null;
            LoggerUtility::logError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'last_db_error' => $db?->getLastError(),
                'last_db_query' => $db?->getLastQuery(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            if ($useLock && $lockFile) {
                MiscUtility::deleteLockFile($lockFile);
            }
        }
    }

    /* ====================== Helpers ====================== */

    /** Yield batches from audit table; supports bulk (by dt) or single sample code. */
    private function fetchRecords(string $tableName, ?string $lastProcessedDate = null, int $limit = 1000, ?string $sampleCode = null): Generator
    {
        $offset = 0;
        while (true) {
            if ($sampleCode !== null && $sampleCode !== '' && $sampleCode !== '0') {
                $this->db->connection('default')->where(
                    "sample_code = '$sampleCode' OR remote_sample_code = '$sampleCode' OR external_sample_code = '$sampleCode'"
                );
            } elseif ($lastProcessedDate) {
                $this->db->connection('default')->where('dt_datetime', $lastProcessedDate, '>');
            }
            $this->db->connection('default')->orderBy('dt_datetime', 'asc');
            $batch = $this->db->connection('default')->get($tableName, [$offset, $limit]);

            if (!$batch || count($batch) === 0) {
                break;
            }
            foreach ($batch as $record) {
                yield $record;
            }
            $offset += $limit;
        }
    }

    /** DB columns ordered for a table. */
    private function getCurrentColumns(string $tableName): array
    {
        $cols = [];
        $result = $this->db->rawQuery("SHOW COLUMNS FROM `$tableName`");
        foreach ($result as $row) {
            $cols[] = $row['Field'];
        }
        return $cols;
    }

    /** Prefer existing file extension: .csv.zst → .csv.gz → .csv.zip → .csv */
    private function resolveExistingCompressed(string $dir, string $base): ?string
    {
        $candidates = ["$base.csv.zst", "$base.csv.gz", "$base.csv.zip", "$base.csv"];
        foreach ($candidates as $rel) {
            $p = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $rel;
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Read compressed or plain CSV into ['headers' => string[], 'rows' => string[][]].
     * For compressed content we rely on ArchiveUtility::decompressToString.
     */
    private function readCompressedCsv(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $content = @file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException("Failed to read CSV: $path");
            }
        } else {
            $content = ArchiveUtility::decompressToString($path);
        }

        $f = fopen('php://temp', 'r+');
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
    }

    /**
     * If headers changed, remap existing rows to match the new header order.
     * Keeps cell strings exactly as they appear in the CSV (already JSON-encoded or "null").
     */
    private function reheaderIfNeeded(array $existingHeaders, array $currentHeaders, array $oldRows): array
    {
        if ($existingHeaders === $currentHeaders) {
            return $oldRows;
        }

        $map = [];
        foreach ($currentHeaders as $newIdx => $h) {
            $map[$newIdx] = array_search($h, $existingHeaders, true);
        }

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

    public function archiveSample(string $testType, string $sampleCode, ?callable $progress = null): void
    {
        $tests = TestsService::getTestTypes();

        // normalize key (respect aliases in TestsService)
        if (!isset($tests[$testType])) {
            foreach ($tests as $k => $_) {
                if (strcasecmp((string) $k, $testType) === 0) {
                    $testType = $k;
                    break;
                }
            }
        }
        if (!isset($tests[$testType]['tableName'])) {
            throw new RuntimeException("Unknown test type: {$testType}");
        }

        $formTable  = $tests[$testType]['tableName'];
        $auditTable = 'audit_' . $formTable;

        // Reuse run() plumbing but only for this table + sample
        $this->runForTables([$auditTable => $testType], $sampleCode, $progress);
    }

    // factor the core loop so run() and archiveSample() can share it
    private function runForTables(array $auditToKey, ?string $sampleCode, ?callable $progress): void
    {
        $metadata = $sampleCode === null || $sampleCode === '' || $sampleCode === '0' ? MiscUtility::loadMetadata($this->metadataPath) : [];

        foreach ($auditToKey as $auditTable => $testKey) {
            $lastProcessedDate = $sampleCode ? null : ($metadata[$auditTable]['last_processed_date'] ?? null);

            $folderName = preg_replace('/[^\w\-]+/', '-', (string) $testKey);
            $targetDir  = $this->archiveRoot . DIRECTORY_SEPARATOR . $folderName;
            MiscUtility::makeDirectory($targetDir);

            $this->log($progress, "Archiving from {$auditTable} (test={$testKey})..");

            $currentHeaders = $this->getCurrentColumns($auditTable);
            $idxRevision   = $this->idx($currentHeaders, 'revision') ?? 1;
            $idxDtDatetime = $this->idx($currentHeaders, 'dt_datetime') ?? 2;

            $counter = 0;
            foreach ($this->fetchRecords($auditTable, $lastProcessedDate, 1000, $sampleCode) as $record) {
                $counter++;


                if (!isset($record['unique_id'])) {
                    $this->log($progress, 'Skipping record without unique_id');
                    continue;
                }

                $uniqueId = $record['unique_id'];
                $baseName = $uniqueId;               // {uniqueId}.csv.<ext>
                $existing = $this->resolveExistingCompressed($targetDir, $baseName);

                $headers = $currentHeaders;
                $rows    = [];

                $existingDtSet  = [];
                $lastRevisionNo = 0;

                if ($existing) {
                    $old = $this->readCompressedCsv($existing);
                    // Map old rows to current headers (preserve cell strings as-is)
                    $rows = $this->reheaderIfNeeded($old['headers'], $currentHeaders, $old['rows']);

                    // Build dt_datetime set + last revision from the reheadered rows
                    foreach ($rows as $r) {
                        if (isset($r[$idxDtDatetime])) {
                            $existingDtSet[$this->jsonishScalar((string)$r[$idxDtDatetime])] = true;
                        }
                        if (isset($r[$idxRevision])) {
                            $revRaw = $this->jsonishScalar((string)$r[$idxRevision]); // handles "5" → 5
                            if (is_numeric($revRaw)) {
                                $lastRevisionNo = max($lastRevisionNo, (int)$revRaw);
                            }
                        }
                    }
                }

                // De-dup by dt_datetime
                $thisDt = isset($record['dt_datetime']) ? (string)$record['dt_datetime'] : null;
                if ($thisDt !== null && isset($existingDtSet[$thisDt])) {
                    $this->log($progress, "Skipping duplicate dt_datetime={$record['dt_datetime']} for {$uniqueId}");
                    if (!$sampleCode && $thisDt !== '') {
                        $lastProcessedDate = $record['dt_datetime'];
                        $this->updateLastProcessedDate($metadata, $auditTable, $lastProcessedDate);
                        MiscUtility::saveMetadata($this->metadataPath, $metadata);
                    }
                    continue;
                }

                // Revision
                $record['revision'] = $lastRevisionNo + 1;

                // Append new row (values encoded like original writer)
                $rows[] = $this->buildRow($headers, $record);

                // Normalize to preferred compression:
                // Remove old files (any extension), then write fresh compressed file.
                $dstBaseNoExt = $targetDir . DIRECTORY_SEPARATOR . $baseName;
                MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$baseName.csv.zst");
                MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$baseName.csv.gz");
                MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$baseName.csv.zip");
                MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$baseName.csv"); // legacy plain

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
     * Build row for current header order from DB record (encode values like original writer).
     * 
     * @param array $headers Column names in desired order
     * @param mixed $record Database record (array, generator yield, or iterable)
     * @return array Row values matching header order
     */
    private function buildRow(array $headers, mixed $record): array
    {
        // Convert iterable to array if needed (for array access)
        if (!is_array($record)) {
            $record = is_iterable($record) ? iterator_to_array($record) : (array)$record;
        }

        $row = [];
        foreach ($headers as $h) {
            $v = $record[$h] ?? null;
            if (is_array($v) || is_object($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            }
            // use empty string for missing/null to keep CSV cleaner
            $row[] = $v ?? '';
        }
        return $row;
    }


    /** Write CSV (headers+rows) and compress using preferred backend (zst → gz → zip). */
    private function writeCompressedCsv(string $dstBaseNoExt, array $headers, array $rows): string
    {
        $backend = ArchiveUtility::pickBestBackend();

        $tmpCsv = tempnam(sys_get_temp_dir(), 'audit_');
        if ($tmpCsv === false) {
            throw new RuntimeException('Failed to create temp CSV file');
        }
        $csvH = fopen($tmpCsv, 'w');
        if ($csvH === false) {
            MiscUtility::deleteFile($tmpCsv);
            throw new RuntimeException('Failed to open temp CSV file');
        }
        fputcsv($csvH, $headers);
        foreach ($rows as $row) {
            fputcsv($csvH, $row);
        }
        fclose($csvH);

        // Target without compression ext; ArchiveUtility appends .zst/.gz/.zip
        $target = $dstBaseNoExt . '.csv';
        try {
            $out = ArchiveUtility::compressFile($tmpCsv, $target, $backend);
        } finally {
            MiscUtility::deleteFile($tmpCsv);
        }
        return $out;
    }

    /** Update metadata helper. */
    private function updateLastProcessedDate(array &$metadata, string $tableName, string $lastProcessedDate): void
    {
        $metadata[$tableName]['last_processed_date'] = $lastProcessedDate;
    }

    /** Index of a column name, null if missing. */
    private function idx(array $headers, string $name): ?int
    {
        $i = array_search($name, $headers, true);
        return ($i === false) ? null : $i;
    }

    /** Decode a "json-ish" scalar from CSV cell into plain string for comparisons. */
    private function jsonishScalar(string $s): string
    {
        $d = json_decode($s, true);
        if (is_string($d) || is_numeric($d)) {
            return (string)$d;
        }
        // If it looks like a quoted string, strip quotes and unescape
        if (strlen($s) >= 2 && $s[0] === '"' && str_ends_with($s, '"')) {
            return stripcslashes(substr($s, 1, -1));
        }
        return $s;
    }

    /** Small logging helper. */
    private function log(?callable $progress, string $msg): void
    {
        if ($progress) {
            $progress($msg);
        } elseif (php_sapi_name() === 'cli') {
            // default to echo for CLI usage
            MiscUtility::safeCliEcho($msg . PHP_EOL);
        }
    }

    public function getUniqueIdFromSampleCode($db, $tableName, $sampleCode)
    {
        $query = "SELECT unique_id FROM $tableName WHERE sample_code = ? OR remote_sample_code = ? OR external_sample_code = ?";
        $result = $db->rawQuery($query, [$sampleCode, $sampleCode, $sampleCode]);
        return $result[0]['unique_id'] ?? null; // Return unique_id if found, otherwise null
    }

    // Function to get column names for a specified table
    public function getColumns($db, $tableName)
    {
        $columnsSql = "SELECT COLUMN_NAME
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = ? AND table_name = ?
                        ORDER BY ordinal_position";
        return $db->rawQuery($columnsSql, [SYSTEM_CONFIG['database']['db'], $tableName]);
    }

    public function resolveAuditFilePath(string $testType, string $uniqueId): ?string
    {
        $tests = TestsService::getTestTypes();

        // Normalize posted key
        if (!isset($tests[$testType])) {
            foreach ($tests as $k => $_) {
                if (strcasecmp((string) $k, $testType) === 0) {
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

        $candidates = [];
        $push = function ($key) use (&$candidates, $uniqueId): void {
            $folder = preg_replace('/[^\w\-]+/', '-', $key);
            $base   = VAR_PATH . "/audit-trail/{$folder}/{$uniqueId}.csv";
            foreach (['.zst', '.gz', '.zip', ''] as $ext) $candidates[] = $base . $ext;
        };

        if ($canonical) {
            $push($canonical);
        }
        $push($testType);                // whatever user posted
        foreach ($aliases as $a) $push($a);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // Final fallback: scan ALL subfolders for a matching file (legacy layouts)
        foreach (glob(VAR_PATH . '/audit-trail/*', GLOB_ONLYDIR) as $dir) {
            foreach (['.csv.zst', '.csv.gz', '.csv.zip', '.csv'] as $ext) {
                $p = $dir . '/' . $uniqueId . $ext;
                if (is_file($p)) {
                    return $p;
                }
            }
        }
        return null;
    }

    public function readAuditDataFromCsvFlexible(string $filePath): array
    {
        if (!is_file($filePath)) {
            return [];
        }

        // Let ArchiveUtility detect and decompress. For plain CSV it can just read as-is.
        // If your ArchiveUtility expects only compressed files, you can guard with extension
        // and use file_get_contents for plain .csv; below assumes it handles both.
        try {
            $csvString = ArchiveUtility::decompressToString($filePath);
        } catch (Throwable) {
            // Fallback: plain CSV read
            $csvString = @file_get_contents($filePath);
            if ($csvString === false) {
                return [];
            }
        }

        // Parse CSV from a temp stream
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $csvString);
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
                // original archiver writes json_encode() values; fgetcsv already unquotes;
                // we’ll show as-is (including literal "null" when used).
                $assoc[$h] = $row[$i] ?? '';
            }
            $rows[] = $assoc;
        }
        fclose($fp);

        return $rows;
    }
}

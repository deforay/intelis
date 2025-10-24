<?php

require_once __DIR__ . "/../../bootstrap.php";

declare(ticks=1);

use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\TestsService;
use App\Services\ArchiveService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$cliMode = php_sapi_name() === 'cli';
$lockFile = MiscUtility::getLockFile(__FILE__);

if (!MiscUtility::isLockFileExpired($lockFile)) {
    if ($cliMode) echo "Another instance of the script is already running." . PHP_EOL;
    exit;
}

MiscUtility::touchLockFile($lockFile);
MiscUtility::setupSignalHandler($lockFile);

/** -------------------- Helpers -------------------- */

/** Update metadata for last processed date */
function updateLastProcessedDate(array &$metadata, string $tableName, string $lastProcessedDate): void
{
    $metadata[$tableName]['last_processed_date'] = $lastProcessedDate;
}

/** Batch generator (ordered by dt_datetime asc, with optional lower bound) */
function fetchRecords(DatabaseService $db, string $tableName, ?string $lastProcessedDate = null, int $limit = 1000): Generator
{
    $offset = 0;
    while (true) {
        if ($lastProcessedDate) {
            $db->connection('default')->where('dt_datetime', $lastProcessedDate, '>');
        }
        $db->connection('default')->orderBy('dt_datetime', 'asc');
        $batch = $db->connection('default')->get($tableName, [$offset, $limit]);

        if (!$batch || count($batch) === 0) break;
        foreach ($batch as $record) yield $record;
        $offset += $limit;
    }
}

/** Columns of a table (ordered) */
function getCurrentColumns(DatabaseService $db, string $tableName): array
{
    $columns = [];
    $result = $db->rawQuery("SHOW COLUMNS FROM `$tableName`");
    foreach ($result as $row) $columns[] = $row['Field'];
    return $columns;
}

/** Find an existing compressed CSV for a sample (zst > gz > zip > plain csv) */
function resolveExistingCompressed(string $dir, string $base): ?string
{
    $candidates = ["$base.csv.zst", "$base.csv.gz", "$base.csv.zip", "$base.csv"];
    foreach ($candidates as $rel) {
        $p = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $rel;
        if (is_file($p)) return $p;
    }
    return null;
}

/** Read compressed or plain CSV => ['headers' => string[], 'rows' => array<array<string>>] */
function readCompressedCsv(string $path): array
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        $content = @file_get_contents($path);
        if ($content === false) {
            return ['headers' => [], 'rows' => []];
        }
    } else {
        // compressed: .zst | .gz | .zip
        $content = ArchiveService::decompressToString($path);
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

/** Remap existing rows to new header order (keeps cells as-is; fills missing with empty string) */
function reheaderIfNeeded(array $existingHeaders, array $currentHeaders, array $oldRows): array
{
    if ($existingHeaders === $currentHeaders) return $oldRows;

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

/** For values read from CSV that might have quotes like "5" → 5, normalize to scalar string */
function jsonish_scalar(string $s): string
{
    $d = json_decode($s, true);
    if (is_string($d) || is_numeric($d)) return (string)$d;
    if (strlen($s) >= 2 && $s[0] === '"' && substr($s, -1) === '"') {
        return stripcslashes(substr($s, 1, -1));
    }
    return $s;
}

/** Write CSV (headers+rows) and compress using preferred backend (zst → gz → zip) */
function writeCompressedCsv(string $dstBaseNoExt, array $headers, array $rows): string
{
    $backend = ArchiveService::pickBestBackend();

    $tmpCsv = tempnam(sys_get_temp_dir(), 'audit_');
    $csvH   = fopen($tmpCsv, 'w');
    fputcsv($csvH, $headers);
    foreach ($rows as $row) {
        fputcsv($csvH, $row);
    }
    fclose($csvH);

    // We pass .csv (no compression extension); ArchiveService adds .zst/.gz/.zip
    $target = $dstBaseNoExt . '.csv';
    $out    = ArchiveService::compressFile($tmpCsv, $target, $backend);
    @unlink($tmpCsv);
    return $out;
}

/** Get column index by name (robust against reordering); returns null if not found */
function idx(array $headers, string $name): ?int
{
    $i = array_search($name, $headers, true);
    return ($i === false) ? null : $i;
}

/** Build row for current header order from $record (write plain scalars; arrays/objects JSON) */
function buildRow(array $headers, array $record): array
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

/** Remove all older on-disk representations so extension stays canonical */
function removeLegacyVariants(string $dir, string $baseName): void
{
    @unlink($dir . DIRECTORY_SEPARATOR . "$baseName.csv.zst");
    @unlink($dir . DIRECTORY_SEPARATOR . "$baseName.csv.gz");
    @unlink($dir . DIRECTORY_SEPARATOR . "$baseName.csv.zip");
    @unlink($dir . DIRECTORY_SEPARATOR . "$baseName.csv"); // legacy plain
}

/** -------------------- Main -------------------- */

try {
    $metadataPath = ROOT_PATH . DIRECTORY_SEPARATOR . "metadata" . DIRECTORY_SEPARATOR . "archive.mdata.json";
    $metadata = MiscUtility::loadMetadata($metadataPath);

    // Build audit-table → testKey map using TestsService (no hardcoding)
    $tests = TestsService::getTestTypes();
    $tests = TestsService::getTestTypes();
    $auditToKey = [];                // 'audit_form_vl' => 'vl' (canonical key)
    foreach ($tests as $key => $meta) {
        $formTable = $meta['tableName'] ?? null;
        if (!$formTable) continue;

        $auditTable = 'audit_' . $formTable;

        // keep the first key seen for a given table (so 'vl' wins over 'recency')
        if (!isset($auditToKey[$auditTable])) {
            $auditToKey[$auditTable] = $key;
        }
    }

    $archiveRoot = ROOT_PATH . "/audit-trail";
    MiscUtility::makeDirectory($archiveRoot);

    foreach ($auditToKey as $auditTable => $testKey) {
        $lastProcessedDate = $metadata[$auditTable]['last_processed_date'] ?? null;

        // folder name: keep test key, filesystem safe
        $folderName = preg_replace('/[^\w\-]+/', '-', $testKey);
        $targetDir  = $archiveRoot . DIRECTORY_SEPARATOR . $folderName;
        MiscUtility::makeDirectory($targetDir);

        if ($cliMode) echo "Archiving from {$auditTable} (test={$testKey})…" . PHP_EOL;

        $currentHeaders = getCurrentColumns($db, $auditTable);
        $idxRevision   = idx($currentHeaders, 'revision')   ?? 1; // fallback to old position assumption
        $idxDtDatetime = idx($currentHeaders, 'dt_datetime') ?? 2;

        $counter = 0;
        foreach (fetchRecords($db, $auditTable, $lastProcessedDate, 1000) as $record) {
            $counter++;
            if ($counter % 10 === 0) {
                MiscUtility::touchLockFile($lockFile);
            }

            if (!isset($record['unique_id'])) {
                if ($cliMode) echo "Skipping record without unique_id" . PHP_EOL;
                continue;
            }

            $uniqueId = $record['unique_id'];
            $baseName = $uniqueId; // {uniqueId}.csv.<ext>
            $existing = resolveExistingCompressed($targetDir, $baseName);

            $headers = $currentHeaders;
            $rows    = [];

            $existingDtSet  = [];
            $lastRevisionNo = 0;

            if ($existing) {
                $old = readCompressedCsv($existing);
                // Reheader old rows to current header order (cells preserved as-is)
                $rows = reheaderIfNeeded($old['headers'], $currentHeaders, $old['rows']);

                // Build dt_datetime set and last revision number from the reheadered rows
                foreach ($rows as $r) {
                    if (isset($r[$idxDtDatetime])) {
                        $existingDtSet[jsonish_scalar((string)$r[$idxDtDatetime])] = true;
                    }
                    if (isset($r[$idxRevision])) {
                        $revRaw = jsonish_scalar((string)$r[$idxRevision]);
                        if (is_numeric($revRaw)) {
                            $lastRevisionNo = max($lastRevisionNo, (int)$revRaw);
                        }
                    }
                }
            }

            // Dedup by dt_datetime
            $thisDt = isset($record['dt_datetime']) ? (string)$record['dt_datetime'] : null;
            if ($thisDt !== null && isset($existingDtSet[$thisDt])) {
                if ($cliMode) echo "Skipping duplicate dt_datetime {$record['dt_datetime']} for {$uniqueId}" . PHP_EOL;
                // Advance metadata in streaming mode
                if (!empty($record['dt_datetime'])) {
                    $lastProcessedDate = $record['dt_datetime'];
                    updateLastProcessedDate($metadata, $auditTable, $lastProcessedDate);
                    MiscUtility::saveMetadata($metadataPath, $metadata);
                }
                continue;
            }

            // Revision
            $record['revision'] = $lastRevisionNo + 1;

            // Append new row
            $rows[] = buildRow($headers, $record);

            // Always re-write using preferred backend; delete older variants so we converge to .zst
            removeLegacyVariants($targetDir, $baseName);
            $dstBaseNoExt = $targetDir . DIRECTORY_SEPARATOR . $baseName;

            $out = writeCompressedCsv($dstBaseNoExt, $headers, $rows);
            if ($cliMode) echo "Wrote " . basename($out) . PHP_EOL;

            // Update metadata (streaming)
            if (!empty($record['dt_datetime'])) {
                $lastProcessedDate = $record['dt_datetime'];
                updateLastProcessedDate($metadata, $auditTable, $lastProcessedDate);
                MiscUtility::saveMetadata($metadataPath, $metadata);
            }
        }

        if ($cliMode) echo "Completed {$auditTable}." . PHP_EOL;
    }

    if ($cliMode) echo "Archiving process completed." . PHP_EOL;
} catch (Throwable $e) {
    if ($cliMode) {
        echo "Some or all data could not be archived" . PHP_EOL;
        echo "An internal error occurred. Please check the logs." . PHP_EOL;
    }
    /** @var DatabaseService $db */
    $db = $db ?? ContainerRegistry::get(DatabaseService::class);
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db?->getLastError(),
        'last_db_query' => $db?->getLastQuery(),
        'trace' => $e->getTraceAsString(),
    ]);
} finally {
    MiscUtility::deleteLockFile(__FILE__);
}

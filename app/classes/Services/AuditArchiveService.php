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
                // Post-cutover (after run-once/prune-legacy-audit-tables.php has
                // dropped the legacy table), this table simply won't exist. Skip
                // silently so the cron task keeps draining audit_log instead of
                // throwing on the missing table.
                if (!$this->tableExists($auditTable)) {
                    continue;
                }

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
        $primaryKey = $tests[$testType]['primaryKey'] ?? null;
        $auditTable = 'audit_' . $formTable;

        // Legacy path: archive remaining rows from audit_form_* for this sample.
        // runForTables skips the table if it no longer exists (post-prune).
        $this->runForTables([$auditTable => $testType], $sampleCode, $progress);

        // v2 path: drain audit_log → file for THIS sample's record_id so the
        // view sees the latest revisions even between cron drains. Look up the
        // record_id by sample_code on the form table (we accept sample_code,
        // remote_sample_code or external_sample_code — mirroring how
        // getUniqueIdFromSampleCode resolves a sample).
        if ($primaryKey !== null && $this->tableExists('audit_log')) {
            $recordId = $this->db->rawQueryValue(
                "SELECT `{$primaryKey}` FROM `{$formTable}`
                  WHERE sample_code = ? OR remote_sample_code = ? OR external_sample_code = ?
                  LIMIT 1",
                [$sampleCode, $sampleCode, $sampleCode]
            );
            if ($recordId !== null && $recordId !== false && $recordId !== '') {
                $this->runFromAuditLog($formTable, (string) $recordId, $progress, false);
            }
        }
    }

    /**
     * Audit Trail v2 drain — read the generic `audit_log` table → write per-sample
     * compressed CSV files (same format the view already reads) → DELETE the
     * archived rows from `audit_log`. Self-pruning: the DB only ever holds the
     * un-archived tail. Re-archiving is impossible (rows are gone after write),
     * so we don't need de-dup-by-dt_datetime here.
     *
     * Optional (formTable, recordId) filter scopes the drain to one record (for
     * on-demand archive-then-view from audit-trail.php). Otherwise drains
     * everything in id-order batches.
     *
     * Revision in the file is renumbered to CONTINUE the existing file's max,
     * not the DB revision — this preserves a contiguous display timeline even
     * when legacy pre-cutover history and post-cutover audit_log rows share the
     * same file. DB precision (for safe DELETE) lives on `audit_log.id`.
     */
    public function runFromAuditLog(
        ?string $formTableFilter = null,
        ?string $recordIdFilter = null,
        ?callable $progress = null,
        bool $useLock = false
    ): void {
        $lockFile = null;
        try {
            if ($useLock) {
                $lockFile = MiscUtility::getLockFile(__FILE__ . '.audit-log-drain');
                if (!MiscUtility::isLockFileExpired($lockFile)) {
                    $this->log($progress, 'Another audit_log drain is already active; exiting.');
                    return;
                }
                MiscUtility::touchLockFile($lockFile);
                MiscUtility::setupSignalHandler($lockFile);
            }

            MiscUtility::makeDirectory($this->archiveRoot);

            // No-op on instances that haven't reached v5.5.3 yet (audit_log absent).
            if (!$this->tableExists('audit_log')) {
                return;
            }

            // form_table → testKey, for the file folder layout.
            $formToKey = [];
            foreach (TestsService::getTestTypes() as $key => $meta) {
                $tbl = $meta['tableName'] ?? null;
                if (!is_string($tbl) || $tbl === '') {
                    continue;
                }
                if (!isset($formToKey[$tbl])) {
                    $formToKey[$tbl] = $key;
                }
            }

            $batchSize = 500;
            while (true) {
                // Build the batch query. Use a small id-anchored window so a
                // concurrent insert during the drain doesn't deadlock the
                // DELETE that follows.
                $where  = '1=1';
                $params = [];
                if ($formTableFilter !== null) {
                    $where  .= ' AND form_table = ?';
                    $params[] = $formTableFilter;
                }
                if ($recordIdFilter !== null) {
                    $where  .= ' AND record_id = ?';
                    $params[] = $recordIdFilter;
                }
                $batch = $this->db->rawQuery(
                    "SELECT id, form_table, record_id, revision, action, dt_datetime, row_data
                       FROM audit_log
                      WHERE $where
                      ORDER BY id ASC
                      LIMIT $batchSize",
                    // Pass null (not an empty array) when there are no filters:
                    // MysqliDb::rawQuery() calls bind_param('') on an empty array,
                    // which is a hard fatal on PHP 8.1+ ("types must not be empty")
                    // and silently killed every unfiltered drain. null skips binding.
                    $params ?: null
                );
                if (!$batch || count($batch) === 0) {
                    break;
                }

                // Group rows by (folder, uniqueId-from-row_data). Rows without a
                // unique_id (or an unknown form_table) can't be filed — we delete
                // them so the queue still drains.
                $groups    = [];
                $orphanIds = [];
                foreach ($batch as $r) {
                    $form = (string) $r['form_table'];
                    if (!isset($formToKey[$form])) {
                        $orphanIds[] = (int) $r['id'];
                        continue;
                    }
                    $data = json_decode((string) $r['row_data'], true);
                    if (!is_array($data) || empty($data['unique_id'])) {
                        $orphanIds[] = (int) $r['id'];
                        continue;
                    }
                    $folder = preg_replace('/[^\w\-]+/', '-', (string) $formToKey[$form]);
                    $uid    = (string) $data['unique_id'];
                    $groups[$folder][$uid][] = ['row' => $r, 'data' => $data];
                }

                $archivedIds = [];
                foreach ($groups as $folder => $byUid) {
                    $targetDir = $this->archiveRoot . DIRECTORY_SEPARATOR . $folder;
                    MiscUtility::makeDirectory($targetDir);

                    foreach ($byUid as $uniqueId => $entries) {
                        // Derive a "current" header set: standards first, then the
                        // union of all columns we have on hand (existing file
                        // headers ∪ row_data keys from new entries). Union semantics
                        // are important: dropping a column from the form must NOT
                        // erase that column's history from older revisions.
                        $stdCols  = ['action', 'revision', 'dt_datetime'];
                        $dataCols = [];
                        foreach ($entries as $e) {
                            foreach (array_keys($e['data']) as $k) {
                                if (!in_array($k, $stdCols, true)) {
                                    $dataCols[$k] = true;
                                }
                            }
                        }

                        $existing      = $this->resolveExistingCompressed($targetDir, $uniqueId);
                        $existingRows  = [];
                        $existingHeaders = [];
                        if ($existing) {
                            $old = $this->readCompressedCsv($existing);
                            $existingHeaders = $old['headers'];
                            $existingRows    = $old['rows'];
                            foreach ($existingHeaders as $h) {
                                if (!in_array($h, $stdCols, true)) {
                                    $dataCols[$h] = true;
                                }
                            }
                        }

                        $currentHeaders = array_merge($stdCols, array_keys($dataCols));

                        // Align old rows to current headers (additive — never drops cells).
                        $reheaderedExisting = $existingHeaders === []
                            ? []
                            : $this->reheaderIfNeeded($existingHeaders, $currentHeaders, $existingRows);

                        // Compute the file's max revision so we can renumber the
                        // new rows to continue the sequence — keeps display
                        // contiguous across legacy + post-cutover history.
                        $idxRev = $this->idx($currentHeaders, 'revision') ?? 1;
                        $maxRev = 0;
                        foreach ($reheaderedExisting as $r) {
                            if (isset($r[$idxRev])) {
                                $v = $this->jsonishScalar((string) $r[$idxRev]);
                                if (is_numeric($v)) {
                                    $maxRev = max($maxRev, (int) $v);
                                }
                            }
                        }

                        // Append new entries in id order (chronological).
                        $newRows = [];
                        foreach ($entries as $e) {
                            $r    = $e['row'];
                            $data = $e['data'];
                            $maxRev++;
                            $record = $data + [
                                'action'      => $r['action'],
                                'revision'    => $maxRev,
                                'dt_datetime' => $r['dt_datetime'],
                            ];
                            $newRows[]      = $this->buildRow($currentHeaders, $record);
                            $archivedIds[]  = (int) $r['id'];
                        }

                        if ($newRows !== []) {
                            $allRows = [...$reheaderedExisting, ...$newRows];
                            // Normalize to preferred compression — remove any
                            // existing file in another extension first.
                            $dstBaseNoExt = $targetDir . DIRECTORY_SEPARATOR . $uniqueId;
                            MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$uniqueId.csv.zst");
                            MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$uniqueId.csv.gz");
                            MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$uniqueId.csv.zip");
                            MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$uniqueId.csv");
                            $this->writeCompressedCsv($dstBaseNoExt, $currentHeaders, $allRows);
                        }
                    }
                }

                // DELETE everything we processed in this batch. Files were
                // synced to disk above, so this is the point of no return for
                // these rows. Orphans (no unique_id / unknown form_table) are
                // dropped too so the queue keeps draining.
                $toDelete = array_merge($archivedIds, $orphanIds);
                if ($toDelete !== []) {
                    $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                    $this->db->rawQuery("DELETE FROM audit_log WHERE id IN ($placeholders)", $toDelete);
                }

                if ($useLock) {
                    MiscUtility::touchLockFile($lockFile);
                }
                if (count($batch) < $batchSize) {
                    break;
                }
            }
            $this->log($progress, 'audit_log drain completed.');
        } catch (Throwable $e) {
            $this->log($progress, 'audit_log drain error: ' . $e->getMessage());
            LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'last_db_error' => $this->db?->getLastError(),
                'last_db_query' => $this->db?->getLastQuery(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            if ($useLock && $lockFile) {
                MiscUtility::deleteLockFile($lockFile);
            }
        }
    }

    /** Used by runFromAuditLog (and the legacy run() in case audit_form_* tables are gone). */
    private function tableExists(string $table): bool
    {
        return (bool) $this->db->rawQueryValue(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$table]
        );
    }

    // factor the core loop so run() and archiveSample() can share it
    private function runForTables(array $auditToKey, ?string $sampleCode, ?callable $progress): void
    {
        $metadata = $sampleCode === null || $sampleCode === '' || $sampleCode === '0' ? MiscUtility::loadMetadata($this->metadataPath) : [];

        foreach ($auditToKey as $auditTable => $testKey) {
            // Post-cutover the legacy table may be gone — skip silently so
            // archiveSample() still works (the v2 audit_log drain happens
            // separately at the caller).
            if (!$this->tableExists($auditTable)) {
                continue;
            }

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

    /**
     * Read an archived audit CSV into rows keyed by column name.
     *
     * If $testType is provided, applies the v2 read-time rename aliases
     * (audit_column_aliases) so historical revisions stored under an old column
     * name are surfaced under the column's CURRENT name. Without $testType the
     * raw historical headers are returned unchanged (back-compat for any caller
     * that doesn't yet pass it). The alias map is empty on a fresh v5.5.3
     * install, so behavior is identical to today's until a rename migration
     * registers an alias.
     */
    public function readAuditDataFromCsvFlexible(string $filePath, ?string $testType = null): array
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

        // Resolve historical header names → current names via audit_column_aliases,
        // scoped to the form table that backs this $testType. Alias service returns
        // the original name unchanged when there's no mapping, so this is safe
        // (and a near-no-op) when the table is empty.
        $resolvedHeaders = $headers;
        if ($testType !== null) {
            $formTable = (string) (TestsService::getTestTypes()[$testType]['tableName'] ?? '');
            if ($formTable !== '') {
                $aliasService = AuditColumnAliasService::instance();
                $resolvedHeaders = $aliasService->resolveMany($formTable, $headers);
            }
        }

        $rows = [];
        while (($row = fgetcsv($fp)) !== false) {
            $assoc = [];
            foreach ($resolvedHeaders as $i => $h) {
                // original archiver writes json_encode() values; fgetcsv already unquotes;
                // we’ll show as-is (including literal "null" when used).
                // When two old names alias to the same current name (rename
                // collision), last write wins — acceptable edge for renames.
                $assoc[$h] = $row[$i] ?? '';
            }
            $rows[] = $assoc;
        }
        fclose($fp);

        return $rows;
    }
}

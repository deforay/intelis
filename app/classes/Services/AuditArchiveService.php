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
    /**
     * Rows buffered before they are filed to disk.
     *
     * Matches the page fetchRecordBatches() reads with, so filing a page lines
     * up with a database round trip rather than cutting across one.
     */
    private const ARCHIVE_BATCH = 1000;

    /**
     * No escape character: archives are written and read as RFC 4180.
     *
     * PHP's historical default escapes with a backslash, which loses data. A
     * field ending in one is written as "value\" — the backslash escapes the
     * closing quote, so on the way back the field swallows the delimiter and
     * everything after it on the line. An analyzer path such as C:\data\run.csv
     * in import_machine_file_name is enough to trigger it, and an audit trail
     * that cannot return what was written has failed at its one job.
     *
     * Disabling the escape is not a format change for existing archives. The
     * setting governs only how a backslash is treated, so for any content
     * without one both rules emit identical bytes and read back identical
     * values — verified over 20,000 generated cases carrying quotes, commas,
     * embedded newlines and unicode. Round-tripping under this rule is exact
     * for rows wider than one column, which every audit row is.
     */
    private const CSV_ESCAPE = '';

    /**
     * The rule archives were written under before the above.
     *
     * Only reached when a file does not parse cleanly as RFC 4180, which needs
     * a backslash to have been stored in the first place. See parseCsv().
     */
    private const LEGACY_CSV_ESCAPE = '\\';

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
                $this->archiveTable(
                    (string) $auditTable,
                    (string) $testKey,
                    $sampleCode,
                    $progress,
                    $metadata,
                    $lockFile,
                    $useLock
                );
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

    /**
     * Archive one legacy `audit_*` table, optionally narrowed to a single sample.
     *
     * The one implementation behind both entry points. run() and runForTables()
     * each used to carry their own copy of this loop, which is how they drifted:
     * the same bug had to be found twice.
     *
     * Rows are filed a sample at a time rather than one at a time. Archiving a
     * row means decompressing that sample's whole history, appending, and
     * recompressing it, so a per-row loop rewrote the file once per revision and
     * re-read everything it had just written — quadratic in a sample's revision
     * count, plus a bundle lookup in resolveExistingCompressed() for each pass.
     * The audit_log drain already groups by sample for this reason; this is the
     * legacy path catching up.
     *
     * $metadata is the bulk-run cursor state, updated in place. Its keys are
     * audit table names, so there is no fixed shape to declare.
     */
    private function archiveTable(
        string $auditTable,
        string $testKey,
        ?string $sampleCode,
        ?callable $progress,
        array &$metadata,
        ?string $lockFile = null,
        bool $useLock = false
    ): void {
        // Post-cutover (after run-once/prune-legacy-audit-tables.php has dropped
        // the legacy table) this table simply won't exist. Skip silently so the
        // cron task keeps draining audit_log, and archiveSample() keeps working,
        // instead of throwing on the missing table.
        if (!$this->tableExists($auditTable)) {
            return;
        }

        $lastProcessedDate = $sampleCode ? null : ($metadata[$auditTable]['last_processed_date'] ?? null);

        // Folder is the test key, normalized to filesystem-friendly
        $folderName = preg_replace('/[^\w\-]+/', '-', $testKey);
        $targetDir  = $this->archiveRoot . DIRECTORY_SEPARATOR . $folderName;
        MiscUtility::makeDirectory($targetDir);

        $this->log($progress, "Archiving from {$auditTable} (test={$testKey})..");

        $currentHeaders = $this->getCurrentColumns($auditTable);

        // Robust indexes by name (fallbacks keep old assumption for legacy files)
        $idxRevision   = $this->idx($currentHeaders, 'revision') ?? 1;
        $idxDtDatetime = $this->idx($currentHeaders, 'dt_datetime') ?? 2;

        foreach ($this->fetchRecordBatches($auditTable, $lastProcessedDate, self::ARCHIVE_BATCH, $sampleCode) as $batch) {
            // Group the batch by sample, preserving the dt_datetime order the
            // query returned them in.
            $bySample = [];
            $batchMaxDt = null;
            foreach ($batch as $record) {
                if (!isset($record['unique_id'])) {
                    $this->log($progress, 'Skipping record without unique_id');
                    continue;
                }
                $bySample[(string) $record['unique_id']][] = $record;

                if (!empty($record['dt_datetime'])) {
                    $dt = (string) $record['dt_datetime'];
                    if ($batchMaxDt === null || $dt > $batchMaxDt) {
                        $batchMaxDt = $dt;
                    }
                }
            }

            foreach ($bySample as $uniqueId => $records) {
                if ($useLock && $lockFile !== null) {
                    MiscUtility::touchLockFile($lockFile);
                }
                $this->archiveSampleRows(
                    $targetDir,
                    (string) $uniqueId,
                    $records,
                    $currentHeaders,
                    $idxRevision,
                    $idxDtDatetime,
                    $progress
                );
            }

            // The cursor advances once the whole batch is on disk. A crash
            // mid-batch replays at most one batch on the next run, and the
            // dt_datetime de-dup makes that replay a no-op. Duplicates count
            // toward the cursor too, so a batch of nothing but rows already
            // archived still moves the run forward.
            if (!$sampleCode && $batchMaxDt !== null) {
                $this->updateLastProcessedDate($metadata, $auditTable, $batchMaxDt);
                MiscUtility::saveMetadata($this->metadataPath, $metadata);
            }
        }

        $this->log($progress, "Completed archiving for {$auditTable}.");
    }


    /**
     * File every buffered audit row for one sample, rewriting its archive once.
     *
     * Reads the sample's existing history a single time, appends the new rows in
     * the order they were read (fetchRecordBatches() orders by dt_datetime), and
     * writes the file once. De-duplication is by dt_datetime against both the
     * existing rows and the rows appended earlier in this same call, so a batch
     * carrying a repeat cannot slip one through.
     *
     * @param array<int, array<string, mixed>> $records
     * @param string[] $currentHeaders
     */
    private function archiveSampleRows(
        string $targetDir,
        string $uniqueId,
        array $records,
        array $currentHeaders,
        int $idxRevision,
        int $idxDtDatetime,
        ?callable $progress
    ): void {
        $existing = $this->resolveExistingCompressed($targetDir, $uniqueId);

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

        $appended = false;
        foreach ($records as $record) {
            // De-dup by dt_datetime
            $thisDt = isset($record['dt_datetime']) ? (string)$record['dt_datetime'] : null;
            if ($thisDt !== null && isset($existingDtSet[$thisDt])) {
                $this->log($progress, "Skipping duplicate dt_datetime={$thisDt} for {$uniqueId}");
                continue;
            }
            if ($thisDt !== null) {
                $existingDtSet[$thisDt] = true;
            }

            // Revision
            $record['revision'] = ++$lastRevisionNo;

            // Append new row (values encoded like original writer)
            $rows[]   = $this->buildRow($headers, $record);
            $appended = true;
        }

        // Every row in the batch was already archived — leave the file alone
        // rather than rewriting it byte-identical, which would only cost the
        // next backup a re-copy.
        if (!$appended) {
            return;
        }

        // Normalize to preferred compression:
        // Remove old files (any extension), then write fresh compressed file.
        $dstBaseNoExt = $targetDir . DIRECTORY_SEPARATOR . $uniqueId;
        MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$uniqueId.csv.zst");
        MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$uniqueId.csv.gz");
        MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$uniqueId.csv.zip");
        MiscUtility::deleteFile($targetDir . DIRECTORY_SEPARATOR . "$uniqueId.csv"); // legacy plain

        $out = $this->writeCompressedCsv($dstBaseNoExt, $headers, $rows);
        $this->log($progress, 'Wrote ' . basename($out));
    }


    /**
     * Yield pages of rows from an audit table; bulk (by dt_datetime) or one sample.
     *
     * Pages rather than single rows because the caller files a whole page at a
     * time — see archiveTable(). Ordered by dt_datetime so a sample's revisions
     * arrive in the order they happened, which is the order they are appended in.
     *
     * @return Generator<int, array<int, array<string, mixed>>>
     */
    private function fetchRecordBatches(string $tableName, ?string $lastProcessedDate = null, int $limit = 1000, ?string $sampleCode = null): Generator
    {
        $where  = '1=1';
        $params = [];
        if ($sampleCode !== null && $sampleCode !== '' && $sampleCode !== '0') {
            $where  = '(sample_code = ? OR remote_sample_code = ? OR external_sample_code = ?)';
            $params = [$sampleCode, $sampleCode, $sampleCode];
        } elseif ($lastProcessedDate) {
            $where  = 'dt_datetime > ?';
            $params = [$lastProcessedDate];
        }

        $limit  = max(1, $limit);
        $offset = 0;
        while (true) {
            $batch = $this->db->rawQuery(
                "SELECT * FROM `$tableName` WHERE $where ORDER BY dt_datetime ASC LIMIT $limit OFFSET $offset",
                $params
            );

            if (!$batch || count($batch) === 0) {
                break;
            }
            yield $batch;
            if (count($batch) < $limit) {
                break;
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

        // Nothing loose. A sample that went quiet long enough will have settled
        // into a month bundle, so lift it back out before answering "no history
        // here" — every caller of this treats null as an empty past and rewrites
        // the file from scratch, which would drop everything already archived
        // for a sample that is simply being worked on again.
        $testType = basename(rtrim($dir, DIRECTORY_SEPARATOR));

        return AuditBundleService::unbundleSample($testType, $base);
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

        return $this->parseCsv($content);
    }

    /**
     * Parse archive CSV, preferring RFC 4180 and falling back to the legacy rule.
     *
     * The header row fixes the width of every row beneath it, which is enough to
     * tell a good parse from a bad one: a mis-parsed field swallows the
     * delimiter that should have ended it, so the row comes back short. A file
     * can only be mis-parsed this way if a backslash was stored in it, which is
     * why the fallback re-reads under LEGACY_CSV_ESCAPE and keeps that reading
     * only when the header vouches for the whole file.
     *
     * Some pre-fix files cannot be recovered by either rule. "a\"b" is a valid
     * encoding of two different values and the writer left no way to tell them
     * apart; that loss happened when the row was written. The width check
     * recovers what is recoverable and prefers the correct rule otherwise.
     *
     * @return array{headers: string[], rows: array<int, array<int, string|null>>}
     */
    private function parseCsv(string $content): array
    {
        $strict = self::parseCsvWith($content, self::CSV_ESCAPE);
        if ($strict['headers'] === []) {
            return $strict;
        }

        $width = count($strict['headers']);
        $fits  = static function (array $parsed) use ($width): bool {
            foreach ($parsed['rows'] as $row) {
                if (count($row) !== $width) {
                    return false;
                }
            }
            return true;
        };

        if ($fits($strict)) {
            return $strict;
        }

        // Only pre-fix files carrying a backslash reach here.
        $legacy = self::parseCsvWith($content, self::LEGACY_CSV_ESCAPE);
        if (count($legacy['headers']) === $width && $fits($legacy)) {
            return $legacy;
        }

        return $strict;
    }

    /**
     * Read a CSV string under one escape rule.
     *
     * @return array{headers: string[], rows: array<int, array<int, string|null>>}
     */
    private static function parseCsvWith(string $content, string $escape): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $headers = fgetcsv($handle, escape: $escape);
        if ($headers === false || $headers === null) {
            fclose($handle);
            return ['headers' => [], 'rows' => []];
        }

        $rows = [];
        while (($row = fgetcsv($handle, escape: $escape)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

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
            // Samples that threw this run (corrupt file, bad read, disk error).
            // Their rows stay in audit_log; we skip re-attempting them on later
            // batches so one bad sample can't slow or spam the whole drain.
            $failedUids = [];
            // Ascending id cursor. It advances past every fetched batch whether
            // or not those rows were deleted, so skipped/failed samples are never
            // re-fetched within a run — guaranteeing forward progress and loop
            // termination even if an entire batch fails to archive.
            $afterId = 0;
            while (true) {
                // Build the batch query, walking strictly forward by id.
                $where  = 'id > ?';
                $params = [$afterId];
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
                    $params
                );
                if (!$batch || count($batch) === 0) {
                    break;
                }

                // Advance the cursor past the highest id in this batch up front,
                // so a batch that fails wholesale still moves the loop forward.
                foreach ($batch as $br) {
                    $afterId = max($afterId, (int) $br['id']);
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
                        // Already failed earlier this run — leave its rows in
                        // audit_log and don't retry until the next run.
                        if (isset($failedUids[$uniqueId])) {
                            continue;
                        }
                        try {
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

                            // Append new entries in id order (chronological). Collect
                            // this sample's ids locally — they only join the batch
                            // delete list after the file write below succeeds.
                            $newRows = [];
                            $newIds  = [];
                            foreach ($entries as $e) {
                                $r    = $e['row'];
                                $data = $e['data'];
                                $maxRev++;
                                $record = $data + [
                                    'action'      => $r['action'],
                                    'revision'    => $maxRev,
                                    'dt_datetime' => $r['dt_datetime'],
                                ];
                                $newRows[] = $this->buildRow($currentHeaders, $record);
                                $newIds[]  = (int) $r['id'];
                            }

                            if ($newRows !== []) {
                                $allRows = [...$reheaderedExisting, ...$newRows];
                                $dstBaseNoExt = $targetDir . DIRECTORY_SEPARATOR . $uniqueId;
                                // Write FIRST (overwrites the same-format file), then
                                // prune any other-format leftovers. If the write throws,
                                // the prior archive and the source rows are left intact
                                // for a clean retry — no half-written history loss.
                                $written = $this->writeCompressedCsv($dstBaseNoExt, $currentHeaders, $allRows);
                                foreach (["$uniqueId.csv.zst", "$uniqueId.csv.gz", "$uniqueId.csv.zip", "$uniqueId.csv"] as $leftover) {
                                    $lf = $targetDir . DIRECTORY_SEPARATOR . $leftover;
                                    if ($lf !== $written) {
                                        MiscUtility::deleteFile($lf);
                                    }
                                }
                                // Write succeeded — now safe to drain these rows.
                                foreach ($newIds as $id) {
                                    $archivedIds[] = $id;
                                }
                            }
                        } catch (Throwable $e) {
                            // One sample failing (corrupt .zst, bad read, disk error)
                            // must NOT wedge the whole drain. Log and skip: this
                            // sample's rows stay in audit_log (never added to
                            // $archivedIds) so they retry on a later run, while the
                            // rest of the batch and queue keep draining.
                            $failedUids[$uniqueId] = true;
                            $this->log($progress, "audit_log drain: skipping sample '$uniqueId' in '$folder': " . $e->getMessage());
                            LoggerUtility::logError('audit_log drain: sample skipped: ' . $e->getMessage(), [
                                'uniqueId' => $uniqueId,
                                'folder'   => $folder,
                                'file'     => $e->getFile(),
                                'line'     => $e->getLine(),
                            ]);
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
            $this->archiveTable((string) $auditTable, (string) $testKey, $sampleCode, $progress, $metadata);
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
        fputcsv($csvH, $headers, escape: self::CSV_ESCAPE);
        foreach ($rows as $row) {
            fputcsv($csvH, $row, escape: self::CSV_ESCAPE);
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

        $parsed  = $this->parseCsv($csvString);
        $headers = $parsed['headers'];
        if ($headers === []) {
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
        foreach ($parsed['rows'] as $row) {
            $assoc = [];
            foreach ($resolvedHeaders as $i => $h) {
                // original archiver writes json_encode() values; the parser already
                // unquotes; we'll show as-is (including literal "null" when used).
                // When two old names alias to the same current name (rename
                // collision), last write wins — acceptable edge for renames.
                $assoc[$h] = $row[$i] ?? '';
            }
            $rows[] = $assoc;
        }

        return $rows;
    }
}

<?php

namespace App\Services;

use App\Services\TestsService;
use App\Utilities\LoggerUtility;

class AuditTrailService
{
    private static $compressionType = null;

    /**
     * Detect available compression type
     */
    private static function detectCompressionType(): string
    {
        if (self::$compressionType !== null) {
            return self::$compressionType;
        }

        // Check for zstd
        exec('which zstd 2>/dev/null', $output, $returnVar);
        if ($returnVar === 0) {
            self::$compressionType = 'zstd';
            return 'zstd';
        }

        // Check for pigz
        exec('which pigz 2>/dev/null', $output, $returnVar);
        if ($returnVar === 0) {
            self::$compressionType = 'pigz';
            return 'pigz';
        }

        // Fallback to gzip
        self::$compressionType = 'gzip';
        return 'gzip';
    }

    /**
     * Read CSV lines from compressed file using generator
     */
    private static function readCsvLines(string $compressedFile): \Generator
    {
        if (!file_exists($compressedFile)) {
            return;
        }

        $type = self::detectCompressionType();

        $command = match ($type) {
            'zstd' => "zstd -d -c " . escapeshellarg($compressedFile) . " 2>/dev/null",
            'pigz' => "pigz -d -c " . escapeshellarg($compressedFile) . " 2>/dev/null",
            'gzip' => "gzip -d -c " . escapeshellarg($compressedFile) . " 2>/dev/null",
            default => "gzip -d -c " . escapeshellarg($compressedFile) . " 2>/dev/null"
        };

        $handle = popen($command, 'r');
        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            yield str_getcsv($line);
        }

        pclose($handle);
    }

    /**
     * Find the audit trail file for a given sample
     */
    private static function findAuditFile(string $testType, string $uniqueId): ?string
    {
        $archivePath = ROOT_PATH . "/audit-trail/{$testType}";

        if (!is_dir($archivePath)) {
            return null;
        }

        // Try different extensions in priority order
        $extensions = ['.csv.zst', '.csv.gz'];

        foreach ($extensions as $ext) {
            $filePath = "{$archivePath}/{$uniqueId}{$ext}";
            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        return null;
    }

    /**
     * Get audit trail history for a sample
     * 
     * @param array $sampleData Sample data containing unique_id
     * @param string $testType Test type (vl, eid, covid19, etc.)
     * @param array $options Optional filters and sorting
     * @return array Array of audit records
     */
    public static function getAuditHistory(array $sampleData, string $testType, array $options = []): array
    {
        try {
            $uniqueId = $sampleData['unique_id'] ?? null;

            if (empty($uniqueId)) {
                LoggerUtility::logError("Missing unique_id in sample data", ['testType' => $testType]);
                return [];
            }

            // Find the audit file
            $auditFile = self::findAuditFile($testType, $uniqueId);

            if ($auditFile === null) {
                // No audit trail exists for this sample
                return [];
            }

            $auditHistory = [];
            $headers = [];
            $lineNumber = 0;

            foreach (self::readCsvLines($auditFile) as $row) {
                $lineNumber++;

                // First line is headers
                if ($lineNumber === 1) {
                    $headers = $row;
                    continue;
                }

                // Convert CSV row to associative array
                $record = [];
                foreach ($headers as $index => $header) {
                    $value = $row[$index] ?? 'null';
                    // Decode JSON-encoded values
                    $record[$header] = $value === 'null' ? null : json_decode($value, true);
                }

                // Apply filters if specified
                if (isset($options['action']) && $record['action'] !== $options['action']) {
                    continue;
                }

                if (isset($options['from_date']) && strtotime($record['dt_datetime'] ?? '') < strtotime($options['from_date'])) {
                    continue;
                }

                if (isset($options['to_date']) && strtotime($record['dt_datetime'] ?? '') > strtotime($options['to_date'])) {
                    continue;
                }

                $auditHistory[] = $record;
            }

            // Sort by revision (default: descending - newest first)
            $sortOrder = $options['sort_order'] ?? 'desc';
            usort($auditHistory, function ($a, $b) use ($sortOrder) {
                $comparison = ($a['revision'] ?? 0) <=> ($b['revision'] ?? 0);
                return $sortOrder === 'desc' ? -$comparison : $comparison;
            });

            return $auditHistory;
        } catch (\Exception $e) {
            LoggerUtility::logError("Error reading audit trail", [
                'testType' => $testType,
                'uniqueId' => $uniqueId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get specific revision from audit trail
     */
    public static function getRevision(array $sampleData, string $testType, int $revision): ?array
    {
        $history = self::getAuditHistory($sampleData, $testType);

        foreach ($history as $record) {
            if (($record['revision'] ?? 0) === $revision) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Get latest revision from audit trail
     */
    public static function getLatestRevision(array $sampleData, string $testType): ?array
    {
        $history = self::getAuditHistory($sampleData, $testType, ['sort_order' => 'desc']);

        return !empty($history) ? $history[0] : null;
    }

    /**
     * Compare two revisions and return the differences
     */
    public static function compareRevisions(array $revision1, array $revision2, array $fieldsToCompare = []): array
    {
        $differences = [];

        // If no specific fields specified, compare all fields
        if (empty($fieldsToCompare)) {
            $fieldsToCompare = array_unique(array_merge(
                array_keys($revision1),
                array_keys($revision2)
            ));
        }

        foreach ($fieldsToCompare as $field) {
            // Skip audit metadata fields
            if (in_array($field, ['action', 'revision', 'dt_datetime'])) {
                continue;
            }

            $value1 = $revision1[$field] ?? null;
            $value2 = $revision2[$field] ?? null;

            // Handle array/object comparisons
            if (is_array($value1) || is_array($value2)) {
                if (json_encode($value1) !== json_encode($value2)) {
                    $differences[$field] = [
                        'old' => $value1,
                        'new' => $value2
                    ];
                }
            } else if ($value1 !== $value2) {
                $differences[$field] = [
                    'old' => $value1,
                    'new' => $value2
                ];
            }
        }

        return $differences;
    }

    /**
     * Get changes between consecutive revisions
     */
    public static function getChangeLog(array $sampleData, string $testType): array
    {
        $history = self::getAuditHistory($sampleData, $testType, ['sort_order' => 'asc']);

        if (count($history) < 2) {
            return [];
        }

        $changeLog = [];

        for ($i = 1; $i < count($history); $i++) {
            $previous = $history[$i - 1];
            $current = $history[$i];

            $differences = self::compareRevisions($previous, $current);

            if (!empty($differences)) {
                $changeLog[] = [
                    'from_revision' => $previous['revision'],
                    'to_revision' => $current['revision'],
                    'action' => $current['action'],
                    'datetime' => $current['dt_datetime'],
                    'changes' => $differences
                ];
            }
        }

        return $changeLog;
    }

    /**
     * Get audit statistics for a sample
     */
    public static function getAuditStats(array $sampleData, string $testType): array
    {
        $history = self::getAuditHistory($sampleData, $testType);

        if (empty($history)) {
            return [
                'total_revisions' => 0,
                'first_created' => null,
                'last_modified' => null,
                'actions' => [],
                'insert_count' => 0,
                'update_count' => 0,
                'delete_count' => 0
            ];
        }

        $actions = array_count_values(array_column($history, 'action'));

        // Sort history by datetime for chronological stats
        usort($history, function ($a, $b) {
            return strtotime($a['dt_datetime'] ?? '') <=> strtotime($b['dt_datetime'] ?? '');
        });

        return [
            'total_revisions' => count($history),
            'first_created' => $history[0]['dt_datetime'] ?? null,
            'last_modified' => end($history)['dt_datetime'] ?? null,
            'actions' => $actions,
            'insert_count' => $actions['insert'] ?? 0,
            'update_count' => $actions['update'] ?? 0,
            'delete_count' => $actions['delete'] ?? 0
        ];
    }

    /**
     * Export audit history to CSV
     */
    public static function exportToCSV(array $sampleData, string $testType, array $columns = []): string
    {
        $history = self::getAuditHistory($sampleData, $testType);

        if (empty($history)) {
            return '';
        }

        // Use specified columns or all columns from first record
        if (empty($columns)) {
            $columns = array_keys($history[0]);
        }

        $output = fopen('php://temp', 'r+');

        // Write headers
        fputcsv($output, $columns);

        // Write data rows
        foreach ($history as $record) {
            $row = [];
            foreach ($columns as $column) {
                $value = $record[$column] ?? '';
                // Convert arrays/objects to JSON for CSV export
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $row[] = $value;
            }
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Process audit data for a specific sample from the database
     * This ensures the CSV file is up-to-date with the latest changes
     * 
     * @param array $sampleData Sample data containing unique_id
     * @param string $testType Test type (vl, eid, covid19, etc.)
     */
    public static function processSampleAuditData(array $sampleData, string $testType): void
    {
        try {
            $uniqueId = $sampleData['unique_id'] ?? null;

            if (empty($uniqueId)) {
                return;
            }

            $auditTable = 'audit_' . TestsService::getTestTableName($testType);
            $idColumn = TestsService::getPrimaryColumn($testType);
            $entityId = $sampleData[$idColumn] ?? null;

            if (empty($entityId)) {
                return;
            }

            /** @var \App\Services\DatabaseService $db */
            $db = \App\Registries\ContainerRegistry::get(\App\Services\DatabaseService::class);

            // Get all audit records for this sample from the database
            $db->where($idColumn, $entityId);
            $db->orderBy('revision', 'asc');
            $auditRecords = $db->get($auditTable);

            if (empty($auditRecords)) {
                return;
            }

            $archivePath = ROOT_PATH . "/audit-trail/{$testType}";
            \App\Utilities\MiscUtility::makeDirectory($archivePath);

            $fileExtension = self::getFileExtension();
            $filePath = "{$archivePath}/{$uniqueId}{$fileExtension}";

            // Get current columns
            $currentHeaders = array_keys($auditRecords[0]);

            // Load existing revisions to avoid duplicates
            $existingRevisions = [];
            if (file_exists($filePath)) {
                $lineNumber = 0;
                foreach (self::readCsvLines($filePath) as $row) {
                    $lineNumber++;
                    if ($lineNumber === 1) continue; // Skip header

                    if (isset($row[1])) {
                        $existingRevisions[] = (int)json_decode($row[1]);
                    }
                }
            }

            // Create temp uncompressed file
            $tempFile = "{$filePath}.tmp.csv";
            $tempHandle = fopen($tempFile, 'w');

            if ($tempHandle === false) {
                throw new \Exception("Failed to create temp file");
            }

            // Write headers
            fputcsv($tempHandle, $currentHeaders);

            // Write existing records first (if file exists)
            if (file_exists($filePath)) {
                $lineNumber = 0;
                foreach (self::readCsvLines($filePath) as $row) {
                    $lineNumber++;
                    if ($lineNumber === 1) continue; // Skip header

                    fputcsv($tempHandle, $row);
                }
            }

            // Append new records
            foreach ($auditRecords as $record) {
                $revision = (int)$record['revision'];

                // Skip if already in file
                if (in_array($revision, $existingRevisions)) {
                    continue;
                }

                // Prepare row
                $rowToWrite = [];
                foreach ($currentHeaders as $header) {
                    $rowToWrite[] = array_key_exists($header, $record) ? json_encode($record[$header]) : "null";
                }

                fputcsv($tempHandle, $rowToWrite);
            }

            fclose($tempHandle);

            // Compress the file
            self::compress($tempFile, $filePath);

            // Clean up temp file
            @unlink($tempFile);
        } catch (\Exception $e) {
            \App\Utilities\LoggerUtility::logError("Error processing sample audit data", [
                'testType' => $testType,
                'uniqueId' => $uniqueId ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get file extension based on compression type
     */
    private static function getFileExtension(): string
    {
        $type = self::detectCompressionType();
        return match ($type) {
            'zstd' => '.csv.zst',
            'pigz', 'gzip' => '.csv.gz',
            default => '.csv.gz'
        };
    }

    /**
     * Compress a file
     */
    private static function compress(string $inputFile, string $outputFile): bool
    {
        $type = self::detectCompressionType();

        $command = match ($type) {
            'zstd' => sprintf('zstd -3 -T0 -q -f -o %s %s', escapeshellarg($outputFile), escapeshellarg($inputFile)),
            'pigz' => sprintf('pigz -c %s > %s', escapeshellarg($inputFile), escapeshellarg($outputFile)),
            'gzip' => sprintf('gzip -c %s > %s', escapeshellarg($inputFile), escapeshellarg($outputFile)),
            default => sprintf('gzip -c %s > %s', escapeshellarg($inputFile), escapeshellarg($outputFile))
        };

        exec($command . ' 2>/dev/null', $output, $returnVar);
        return $returnVar === 0;
    }
}

<?php

use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\TEST_FAILED;

// only run from command line
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    exit(0);
}

require_once __DIR__ . "/../bootstrap.php";

use App\Services\VlService;
use App\Utilities\LoggerUtility;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

$lockTargetFile = __FILE__;

if (!MiscUtility::isLockFileExpired($lockTargetFile)) {
    LoggerUtility::log('warning', 'update-vl-suppression is already running; exiting.');
    if ($isCli) {
        echo "Another instance is already running. Exiting." . PHP_EOL;
    }
    exit(0);
}

MiscUtility::touchLockFile($lockTargetFile);
MiscUtility::setupSignalHandler($lockTargetFile);

// Simple configuration
$batchSize = 2000;
$offset = 0;
$totalUpdated = 0;
$totalProcessed = 0;
$totalInvalidFixed = 0;
$startTime = microtime(true);
$exitCode = 0;

try {
    // First, fix any ACCEPTED results that have blank/null result values
    // Set status based on whether sample_code is created or not
    // if sample_code is set, it means sample has been registered at lab
$fixInvalidBatchSize = 500;
$fixInvalidSelectSql = "SELECT vl_sample_id
                        FROM form_vl
                        WHERE IFNULL(result_status, 0) = ?
                        AND (result IS NULL OR result = '')
                        LIMIT ?";

$maxAttempts = 3;

do {
    $affected = 0;
    $rows = $db->rawQuery($fixInvalidSelectSql, [ACCEPTED, $fixInvalidBatchSize]);
    if ($rows === false || $rows === []) {
        break;
    }

    $ids = array_column($rows, 'vl_sample_id');
    $affected = count($ids);

    if ($affected === 0) {
        break;
    }

    $placeholders = implode(',', array_fill(0, $affected, '?'));
    $fixInvalidUpdateSql = "UPDATE form_vl
                            SET result_status = CASE 
                                    WHEN sample_code IS NOT NULL THEN ?
                                    ELSE ?
                                END,
                                data_sync = 0
                            WHERE vl_sample_id IN ($placeholders)
                            AND IFNULL(result_status, 0) = ?
                            AND (result IS NULL OR result = '')";

    $attempt = 0;
    while ($attempt < $maxAttempts) {
        try {
            $params = array_merge(
                [
                    RECEIVED_AT_TESTING_LAB,
                    RECEIVED_AT_CLINIC
                ],
                $ids,
                [
                    ACCEPTED
                ]
            );

            $fixResult = $db->rawQuery($fixInvalidUpdateSql, $params);
            if ($fixResult !== false) {
                $totalInvalidFixed += $db->count;
            }
            break;
        } catch (Throwable $e) {
            $attempt++;
            if (stripos($e->getMessage(), 'Lock wait timeout exceeded') !== false && $attempt < $maxAttempts) {
                LoggerUtility::log('warning', 'Lock wait detected while fixing invalid VL results; retrying batch ' . $attempt);
                usleep(200000); // 200ms backoff
                continue;
            }

            throw $e;
        }
    }
    MiscUtility::touchLockFile($lockTargetFile);
} while ($affected >= $fixInvalidBatchSize);

$sql = "SELECT vl_sample_id, result_status, result
        FROM form_vl
        WHERE vl_sample_id > ?
            AND vl_result_category IS NULL
            AND (
                result_status = ? 
                OR (result_status = ? AND result IS NOT NULL)
            )
        ORDER BY vl_sample_id
        LIMIT ?";

$params = [
    REJECTED,
    ACCEPTED,
];

// Process batches in continuous loop
do {
    $lastProcessedId = $offset;
    $batchParams = array_merge([$lastProcessedId], $params, [$batchSize]);
    $result = $db->rawQuery($sql, $batchParams);
    $batchCount = count($result);

    if ($batchCount === 0) {
        break;
        }

        $totalProcessed += $batchCount;

        // Group updates by category for bulk operations
        $updateGroups = [];

        foreach ($result as $row) {
            try {

                $vlResultCategory = $vlService->getVLResultCategory($row['result_status'], $row['result']);

                if (!empty($vlResultCategory)) {
                    $dataToUpdate = ['vl_result_category' => $vlResultCategory];

                    // Determine status change
                    if ($vlResultCategory == 'failed' || $vlResultCategory == 'invalid') {
                        $dataToUpdate['result_status'] = TEST_FAILED;
                    } elseif ($vlResultCategory == 'rejected') {
                        $dataToUpdate['result_status'] = REJECTED;
                    }

                    // Group by update type
                    $updateKey = serialize($dataToUpdate);
                    if (!isset($updateGroups[$updateKey])) {
                        $updateGroups[$updateKey] = [
                            'data' => $dataToUpdate,
                            'ids' => []
                        ];
                    }
                    $updateGroups[$updateKey]['ids'][] = $row['vl_sample_id'];
                }
            } catch (Exception $e) {
                // Log individual row errors but continue processing
                LoggerUtility::logError("Error processing sample ID {$row['vl_sample_id']}: " . $e->getMessage());
                continue;
            }
        }

        // Execute bulk updates
        foreach ($updateGroups as $group) {
            if (isset($group['ids']) && $group['ids'] !== []) {
                try {
                    $placeholders = str_repeat('?,', count($group['ids']) - 1) . '?';

                    $updateSql = "UPDATE form_vl SET ";
                    $updateParams = [];

                    foreach ($group['data'] as $field => $value) {
                        $updateSql .= "{$field} = ?, ";
                        $updateParams[] = $value;
                    }

                    $updateSql = rtrim($updateSql, ', ');
                    $updateSql .= " WHERE vl_sample_id IN ({$placeholders})";
                    $updateParams = array_merge($updateParams, $group['ids']);

                    $updateResult = $db->rawQuery($updateSql, $updateParams);
                    if ($updateResult !== false) {
                        $totalUpdated += count($group['ids']);
                    }
                } catch (Exception $e) {
                    // Log bulk update errors
                    LoggerUtility::logError("Bulk update failed for " . count($group['ids']) . " records: " . $e->getMessage(), [
                        'sample_ids' => array_slice($group['ids'], 0, 10),
                        'update_data' => $group['data']
                    ]);
                }
            }
        }

        $lastRow = end($result);
        $offset = (int)($lastRow['vl_sample_id'] ?? $offset);
        reset($result);
        MiscUtility::touchLockFile($lockTargetFile);
} while ($batchCount > 0);
    if (!$isCli) {
        $duration = round(microtime(true) - $startTime, 2);
        echo "Completed! Invalid fixed: {$totalInvalidFixed}, Processed: {$totalProcessed}, Updated: {$totalUpdated}, Duration: {$duration}s\n";
    }
} catch (Throwable $e) {
    // Critical error logging
    LoggerUtility::logError("VL category update script failed critically", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'invalid_results_fixed' => $totalInvalidFixed,
        'records_processed_before_failure' => $totalProcessed,
        'records_updated_before_failure' => $totalUpdated,
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
        'trace' => $e->getTraceAsString(),
    ]);

    $exitCode = 1;
} finally {
    MiscUtility::deleteLockFile($lockTargetFile);
}

exit($exitCode);

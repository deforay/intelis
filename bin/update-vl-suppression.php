<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../bootstrap.php");

use App\Services\VlService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

// Simple configuration
$batchSize = 2000;
$offset = 0;
$totalUpdated = 0;
$startTime = microtime(true);

try {
    $sql = "SELECT vl_sample_id, result_status, result
            FROM form_vl
            WHERE vl_result_category IS NULL
                AND ((result_status IN (?, ?)) OR (result IS NOT NULL))
            ORDER BY vl_sample_id
            LIMIT ? OFFSET ?";

    $params = [
        SAMPLE_STATUS\REJECTED,
        SAMPLE_STATUS\ACCEPTED,
    ];

    // Process batches
    do {
        $batchParams = array_merge($params, [$batchSize, $offset]);
        $result = $db->rawQuery($sql, $batchParams);
        $batchCount = count($result);

        if ($batchCount === 0) {
            break;
        }

        // Group updates by category for bulk operations
        $updateGroups = [];

        foreach ($result as $row) {
            try {
                $vlResultCategory = $vlService->getVLResultCategory($row['result_status'], $row['result']);

                if (!empty($vlResultCategory)) {
                    $dataToUpdate = ['vl_result_category' => $vlResultCategory];

                    // Determine status change
                    if ($vlResultCategory == 'failed' || $vlResultCategory == 'invalid') {
                        $dataToUpdate['result_status'] = SAMPLE_STATUS\TEST_FAILED;
                    } elseif ($vlResultCategory == 'rejected') {
                        $dataToUpdate['result_status'] = SAMPLE_STATUS\REJECTED;
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
            if (!empty($group['ids'])) {
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

                    $result = $db->rawQuery($updateSql, $updateParams);
                    if ($result !== false) {
                        $totalUpdated += count($group['ids']);
                    }

                } catch (Exception $e) {
                    // Log bulk update errors
                    LoggerUtility::logError("Bulk update failed for " . count($group['ids']) . " records: " . $e->getMessage(), [
                        'sample_ids' => array_slice($group['ids'], 0, 10), // Log first 10 IDs only
                        'update_data' => $group['data']
                    ]);
                }
            }
        }

        $offset += $batchSize;

    } while ($batchCount === $batchSize);

    // Success logging
    //$duration = round(microtime(true) - $startTime, 2);
    // LoggerUtility::logInfo("VL Result Category update completed successfully", [
    //     'records_updated' => $totalUpdated,
    //     'duration_seconds' => $duration,
    //     'avg_records_per_second' => $totalUpdated > 0 ? round($totalUpdated / $duration) : 0
    // ]);

} catch (Throwable $e) {
    // Critical error logging
    LoggerUtility::logError("VL category update script failed critically", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'offset_reached' => $offset,
        'records_updated_before_failure' => $totalUpdated,
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
        'trace' => $e->getTraceAsString(),
    ]);


    exit(1);
}

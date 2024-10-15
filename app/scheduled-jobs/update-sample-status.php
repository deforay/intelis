#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . "/../../bootstrap.php";

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

foreach (SYSTEM_CONFIG['modules'] as $module => $isModuleEnabled) {

    try {
        $tableName = $isModuleEnabled ? TestsService::getTestTableName($module) : null;
        if (!empty($tableName)) {
            //FAILED SAMPLES
            if ($module === 'vl') {
                //FAILED SAMPLES
                $batchSize = 100;
                $offset = 0;
                $statusCodes = [
                    SAMPLE_STATUS\REJECTED,
                    SAMPLE_STATUS\TEST_FAILED
                ];
                while (true) {
                    // Fetch a batch of rows
                    $db->where("result_status", $statusCodes, 'NOT IN');
                    $db->where("(result LIKE 'fail%' OR result = 'failed' OR result LIKE 'err%' OR result LIKE 'error')");
                    $db->orderBy("vl_sample_id", "ASC"); // Ensure consistent ordering
                    $db->pageLimit = $batchSize;
                    $rows = $db->get($tableName, [$offset, $batchSize], "vl_sample_id");

                    if (empty($rows)) {
                        // No more rows to process
                        break;
                    }

                    // Extract the IDs of the rows to update
                    $ids = array_column($rows, 'vl_sample_id');

                    // Update the rows in the current batch
                    $db->where("vl_sample_id", $ids, 'IN');
                    $db->update(
                        $tableName,
                        [
                            "result_status" => SAMPLE_STATUS\TEST_FAILED,
                            "data_sync" => 0,
                            "last_modified_datetime" => DateUtility::getCurrentDateTime()
                        ]
                    );

                    // Move to the next batch
                    $offset += $batchSize;
                }
            }

            //EXPIRING SAMPLES
            $expiryDays = (int) ($general->getGlobalConfig('sample_expiry_after_days') ?? 365);
            if (empty($expiryDays) || $expiryDays <= 0) {
                $expiryDays = 365; // by default, we consider samples more than 1 year as expired
            }

            // if sample is not yet tested, then update it to Expired if it is older than expiryDays
            $statusCodes = [
                SAMPLE_STATUS\ON_HOLD,
                SAMPLE_STATUS\REORDERED_FOR_TESTING,
                SAMPLE_STATUS\RECEIVED_AT_CLINIC,
                SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB
            ];

            $db->where("result_status", $statusCodes, 'IN');
            $db->where("DATEDIFF(CURRENT_DATE, `sample_collection_date`) > $expiryDays");
            $db->update(
                $tableName,
                [
                    "result_status" => SAMPLE_STATUS\EXPIRED,
                    "locked" => "yes"
                ]
            );

            if ($general->isLISInstance()) {
                // If sample is Expired but still within the expiry limit, then update it to Received at Testing Lab
                $db->where("result_status = " . SAMPLE_STATUS\EXPIRED);
                $db->where("(result IS NULL OR result = '')");
                $db->where("sample_code IS NOT NULL");
                $db->where("(is_sample_rejected = 'no' OR is_sample_rejected IS NULL OR is_sample_rejected = '')");
                $db->where("DATEDIFF(CURRENT_DATE, `sample_collection_date`) <= $expiryDays");
                $db->update(
                    $tableName,
                    [
                        "result_status" => SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB,
                        "locked" => "no"
                    ]
                );
            }

            //LOCKING SAMPLES
            $lockAfterDays = (int) ($general->getGlobalConfig('sample_lock_after_days') ?? 14);
            if (empty($lockAfterDays) || $lockAfterDays <= 0) {
                $lockAfterDays = 14;
            }

            $statusCodes = [
                SAMPLE_STATUS\REJECTED,
                SAMPLE_STATUS\ACCEPTED
            ];
            $db->where("result_status", $statusCodes, 'IN');
            $db->where("locked NOT LIKE 'yes'");
            $db->where("DATEDIFF(CURRENT_DATE, `last_modified_datetime`) > $lockAfterDays");
            $db->update($tableName, ["locked" => "yes"]);
        }
    } catch (Throwable $e) {
        LoggerUtility::logError($e->getMessage(), [
            'module' => $module,
            'last_query' => $db->getLastQuery(),
            'last_db_error' => $db->getLastError(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString()
        ]);
        continue;
    }
}

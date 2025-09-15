<?php

$isCli = php_sapi_name() === 'cli';

// only run from command line
if ($isCli === false) {
    exit(0);
}

require_once __DIR__ . "/../bootstrap.php";

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

    if ($isModuleEnabled === false) {
        continue;
    }

    if ($isCli) {
        echo PHP_EOL . "------------------" . PHP_EOL;
        echo "PROCESSING " . strtoupper($module) . PHP_EOL;
        echo "------------------" . PHP_EOL;
    }
    $tableName = $isModuleEnabled ? TestsService::getTestTableName($module) : null;
    if (!empty($tableName)) {

        $primaryKey = TestsService::getPrimaryColumn($module);

        // BLOCK 1: LOCKING SAMPLES

        if ($isCli) {
            echo "Processing locking samples for $module" . PHP_EOL;
        }
        $batchSize = 100;
        $offset = 0;
        $lockAfterDays = (int) ($general->getGlobalConfig('sample_lock_after_days') ?? 14);
        $lockAfterDays = $lockAfterDays > 7 ? $lockAfterDays : 14;

        $statusCodes = [
            SAMPLE_STATUS\REJECTED,
            SAMPLE_STATUS\ACCEPTED
        ];
        $batchNumber = 0;
        while (true) {
            try {

                $db->reset();
                $db->where("result_status IN  (" . implode(",", $statusCodes) . ")");
                $db->where("IFNULL(locked, 'no') = 'no'");
                $db->where("DATEDIFF(CURRENT_DATE, `last_modified_datetime`) > $lockAfterDays");
                $db->pageLimit = $batchSize;
                $rows = $db->get($tableName, [$offset, $batchSize], $primaryKey);


                if (empty($rows)) {
                    echo "$batchNumber batches of $batchSize samples processed." . PHP_EOL;
                    break;
                }
                $batchNumber++;

                $db->beginTransaction();
                $ids = array_column($rows, $primaryKey);

                $db->reset();
                $db->where($primaryKey, $ids, 'IN');
                $db->update(
                    $tableName,
                    [
                        "locked" => "yes"
                    ]
                );
                $db->commitTransaction();


                $offset += $batchSize;
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_error' => $db->getLastError(),
                    'last_db_query' => $db->getLastQuery(),
                    'trace' => $e->getTraceAsString(),
                ]);
                continue;
            }
        }

        // BLOCK 2: FAILED SAMPLES (ONLY FOR VL)
        if ($module === 'vl') {
            if ($isCli) {
                echo "Processing failed samples for $module" . PHP_EOL;
            }
            $batchSize = 100;
            $offset = 0;
            $statusCodes = [
                SAMPLE_STATUS\REJECTED,
                SAMPLE_STATUS\TEST_FAILED
            ];
            $batchNumber = 0;
            while (true) {
                try {

                    $db->reset();
                    $db->where("result_status NOT IN  (" . implode(",", $statusCodes) . ")");
                    $db->where("(result LIKE 'fail%' OR result = 'failed' OR result LIKE 'err%' OR result LIKE 'error')");
                    $db->orderBy($primaryKey, "ASC");
                    $db->pageLimit = $batchSize;
                    $rows = $db->get($tableName, [$offset, $batchSize], $primaryKey);


                    if (empty($rows)) {
                        echo "$batchNumber batches of $batchSize samples processed." . PHP_EOL;
                        break;
                    }


                    $ids = array_column($rows, $primaryKey);
                    $db->beginTransaction();
                    $db->reset();
                    $db->where($primaryKey, $ids, 'IN');
                    $db->update(
                        $tableName,
                        [
                            "result_status" => SAMPLE_STATUS\TEST_FAILED,
                            "data_sync" => 0,
                            "last_modified_datetime" => DateUtility::getCurrentDateTime()
                        ]
                    );

                    $db->commitTransaction();


                    $offset += $batchSize;
                } catch (Throwable $e) {
                    $db->rollbackTransaction();
                    LoggerUtility::logError($e->getMessage(), [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'last_db_error' => $db->getLastError(),
                        'last_db_query' => $db->getLastQuery(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    continue;
                }
            }
        }

        // BLOCK 3: EXPIRING SAMPLES
        if ($isCli) {
            echo "Processing expired samples for $module" . PHP_EOL;
        }

        $batchSize = 100;
        $offset = 0;
        $expiryDays = (int) ($general->getGlobalConfig('sample_expiry_after_days') ?? 365);
        $expiryDays = $expiryDays > 0 ? $expiryDays : 365;

        $statusCodes = [
            SAMPLE_STATUS\ON_HOLD,
            SAMPLE_STATUS\REORDERED_FOR_TESTING,
            SAMPLE_STATUS\RECEIVED_AT_CLINIC,
            SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB
        ];

        $batchNumber = 0;
        while (true) {
            try {

                $db->reset();
                $db->where("result_status IN  (" . implode(",", $statusCodes) . ")");
                $db->where("DATEDIFF(CURRENT_DATE, `sample_collection_date`) > $expiryDays");
                $db->pageLimit = $batchSize;
                $rows = $db->get($tableName, [$offset, $batchSize], $primaryKey);


                if (empty($rows)) {
                    echo "$batchNumber batches of $batchSize samples processed." . PHP_EOL;
                    break;
                }


                $ids = array_column($rows, $primaryKey);

                $db->beginTransaction();
                $db->reset();
                $db->where($primaryKey, $ids, 'IN');
                $db->update(
                    $tableName,
                    [
                        "result_status" => SAMPLE_STATUS\EXPIRED,
                        "locked" => "yes"
                    ]
                );
                $db->commitTransaction();


                $offset += $batchSize;
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_error' => $db->getLastError(),
                    'last_db_query' => $db->getLastQuery(),
                    'trace' => $e->getTraceAsString(),
                ]);
                continue;
            }
        }
    }
}

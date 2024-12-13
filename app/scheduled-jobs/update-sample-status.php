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

    $tableName = $isModuleEnabled ? TestsService::getTestTableName($module) : null;
    if (!empty($tableName)) {

        // BLOCK 1: FAILED SAMPLES
        if ($module === 'vl') {
            $batchSize = 100;
            $offset = 0;
            $statusCodes = [
                SAMPLE_STATUS\REJECTED,
                SAMPLE_STATUS\TEST_FAILED
            ];
            while (true) {
                try {
                    $db->beginTransaction();
                    $db->reset();
                    $db->where("result_status NOT IN  (" . implode(",", $statusCodes) . ")");
                    $db->where("(result LIKE 'fail%' OR result = 'failed' OR result LIKE 'err%' OR result LIKE 'error')");
                    $db->orderBy("vl_sample_id", "ASC");
                    $db->pageLimit = $batchSize;
                    $rows = $db->get($tableName, [$offset, $batchSize], "vl_sample_id");

                    if (empty($rows)) {
                        break;
                    }

                    $ids = array_column($rows, 'vl_sample_id');

                    $db->reset();
                    $db->where("vl_sample_id", $ids, 'IN');
                    $db->update(
                        $tableName,
                        [
                            "result_status" => SAMPLE_STATUS\TEST_FAILED,
                            "data_sync" => 0,
                            "last_modified_datetime" => DateUtility::getCurrentDateTime()
                        ]
                    );

                    $offset += $batchSize;
                    $db->commitTransaction();
                } catch (Throwable $e) {
                    $db->rollbackTransaction();
                    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
                    LoggerUtility::logError($e->getMessage(), [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    continue;
                }
            }
        }

        // BLOCK 2: EXPIRING SAMPLES
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

        while (true) {
            try {
                $db->beginTransaction();
                $db->reset();
                $db->where("result_status IN  (" . implode(",", $statusCodes) . ")");
                $db->where("DATEDIFF(CURRENT_DATE, `sample_collection_date`) > $expiryDays");
                $db->pageLimit = $batchSize;
                $rows = $db->get($tableName, [$offset, $batchSize], "sample_id");

                if (empty($rows)) {
                    break;
                }

                $ids = array_column($rows, 'sample_id');

                $db->reset();
                $db->where("sample_id", $ids, 'IN');
                $db->update(
                    $tableName,
                    [
                        "result_status" => SAMPLE_STATUS\EXPIRED,
                        "locked" => "yes"
                    ]
                );

                $offset += $batchSize;
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getMessage());
                continue;
            }
        }

        if ($general->isLISInstance()) {
            try {
                $db->beginTransaction();
                $db->reset();
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
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getMessage());
            }
        }

        // BLOCK 3: LOCKING SAMPLES
        $batchSize = 100;
        $offset = 0;
        $lockAfterDays = (int) ($general->getGlobalConfig('sample_lock_after_days') ?? 14);
        $lockAfterDays = $lockAfterDays > 0 ? $lockAfterDays : 14;

        $statusCodes = [
            SAMPLE_STATUS\REJECTED,
            SAMPLE_STATUS\ACCEPTED
        ];

        while (true) {
            try {
                $db->beginTransaction();
                $db->reset();
                $db->where("result_status IN  (" . implode(",", $statusCodes) . ")");
                $db->where("locked NOT LIKE 'yes'");
                $db->where("DATEDIFF(CURRENT_DATE, `last_modified_datetime`) > $lockAfterDays");
                $db->pageLimit = $batchSize;
                $rows = $db->get($tableName, [$offset, $batchSize], "sample_id");

                if (empty($rows)) {
                    break;
                }

                $ids = array_column($rows, 'sample_id');

                $db->reset();
                $db->where("sample_id", $ids, 'IN');
                $db->update(
                    $tableName,
                    [
                        "locked" => "yes"
                    ]
                );

                $offset += $batchSize;
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getMessage());
                continue;
            }
        }
    }
}

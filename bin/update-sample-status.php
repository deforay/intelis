#!/usr/bin/env php
<?php

// Refresh sample statuses (expired / on-hold / accepted / rejected / failed,
// etc.) across all test types. Cron-invoked.

require_once __DIR__ . "/../bootstrap.php";

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use const SAMPLE_STATUS\EXPIRED;
use const SAMPLE_STATUS\ON_HOLD;
use App\Services\DatabaseService;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\TEST_FAILED;
use App\Registries\ContainerRegistry;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Services\SampleStatusRepairService;
use const SAMPLE_STATUS\REORDERED_FOR_TESTING;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;


// only run from command line
$isCli = PHP_SAPI === 'cli';
if ($isCli === false) {
    exit(CLI\ERROR);
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var SampleStatusRepairService $statusRepair */
$statusRepair = ContainerRegistry::get(SampleStatusRepairService::class);

// How far back the nightly status repair looks. Older rows are the one-time
// pass in run-once/reconcile-accepted-without-result.php, not this job's work.
const REPAIR_WINDOW_MONTHS = 6;

$lockTargetFile = __FILE__;

if (!MiscUtility::isLockFileExpired($lockTargetFile)) {
    LoggerUtility::log('warning', 'update-sample-status is already running; exiting.');
    if ($isCli) {
        echo "Another instance is already running. Exiting." . PHP_EOL;
    }
    exit(CLI\ERROR);
}

MiscUtility::touchLockFile($lockTargetFile);
MiscUtility::setupSignalHandler($lockTargetFile);

$exitCode = 0;

try {
    foreach (SYSTEM_CONFIG['modules'] as $module => $isModuleEnabled) {

        if ($isModuleEnabled === false) {
            continue;
        }

        if ($isCli) {
            echo PHP_EOL . "------------------" . PHP_EOL;
            echo "PROCESSING " . strtoupper((string) $module) . PHP_EOL;
            echo "------------------" . PHP_EOL;
        }
        $tableName = $isModuleEnabled ? TestsService::getTestTableName($module) : null;
        if ($tableName !== null && $tableName !== '' && $tableName !== '0') {

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
                REJECTED,
                ACCEPTED
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
            MiscUtility::touchLockFile($lockTargetFile);

            // BLOCK 2: FAILED SAMPLES (ONLY FOR VL)
            if ($module === 'vl') {
                if ($isCli) {
                    echo "Processing failed samples for $module" . PHP_EOL;
                }
                $batchSize = 100;
                $offset = 0;
                $statusCodes = [
                    REJECTED,
                    TEST_FAILED
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
                                "result_status" => TEST_FAILED,
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
                MiscUtility::touchLockFile($lockTargetFile);
            }

            // BLOCK 3: STATUS THAT CONTRADICTS THE RECORD
            //
            // A sample marked Accepted or Awaiting Approval that holds no result
            // at all. Neither status can be true of a row with nothing to show.
            // SampleStatusRepairService carries the reasoning and the repair;
            // see it for what is put back and what is left alone.
            //
            // Kept to a recent window on purpose. This runs nightly on a server
            // the labs are using, and re-scanning years of settled history every
            // night to find nothing is not what a nightly job is for. Anything
            // older is the one-time pass in
            // run-once/reconcile-accepted-without-result.php, which does the
            // same repair over everything. What is left here is the ongoing
            // guard: whatever the write paths produce between now and their
            // fixes landing, caught within months rather than never.
            if ($isCli) {
                echo "Processing accepted-without-result samples for $module" . PHP_EOL;
            }

            $repair = $statusRepair->repairAcceptedWithoutResult(
                $module,
                REPAIR_WINDOW_MONTHS,
                // The lock file has to keep being touched or a long sweep looks
                // to the next invocation like a crashed one.
                static function () use ($lockTargetFile): void {
                    MiscUtility::touchLockFile($lockTargetFile);
                }
            );

            if ($isCli && $repair['repaired'] > 0) {
                echo $repair['repaired'] . " sample(s) put back to what the record proves ("
                    . $repair['datesCleared'] . " copied test date(s) cleared)." . PHP_EOL;
            }
            MiscUtility::touchLockFile($lockTargetFile);

            // BLOCK 4: EXPIRING SAMPLES
            if ($isCli) {
                echo "Processing expired samples for $module" . PHP_EOL;
            }

            $batchSize = 100;
            $offset = 0;
            $expiryDays = (int) ($general->getGlobalConfig('sample_expiry_after_days') ?? 365);
            $expiryDays = $expiryDays > 0 ? $expiryDays : 365;

            $statusCodes = [
                ON_HOLD,
                REORDERED_FOR_TESTING,
                RECEIVED_AT_CLINIC,
                RECEIVED_AT_TESTING_LAB
            ];

            $batchNumber = 0;
            while (true) {
                try {

                    $db->reset();
                    $db->where("result_status IN  (" . implode(",", $statusCodes) . ")");
                    // Falls back to the date the request was created when no
                    // collection date was recorded. DATEDIFF against NULL is
                    // NULL, so keying on the collection date alone meant a
                    // sample without one could never expire, however old --
                    // 23,291 of them on one instance, sitting in a pre-result
                    // status for good. Same COALESCE the indicators use to
                    // decide when a sample was registered.
                    $db->where("DATEDIFF(CURRENT_DATE, COALESCE(`sample_collection_date`, `request_created_datetime`)) > $expiryDays");
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
                            "result_status" => EXPIRED,
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
            MiscUtility::touchLockFile($lockTargetFile);
        }
    }
} catch (Throwable $e) {
    $exitCode = 1;
    LoggerUtility::logError("Sample status update script failed critically: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
        'trace' => $e->getTraceAsString(),
    ]);
} finally {
    MiscUtility::deleteLockFile($lockTargetFile);
}

exit($exitCode);

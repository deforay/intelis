#!/usr/bin/env php
<?php

// Auto-import interface results from connected analyzer / middleware databases.

require_once __DIR__ . "/../bootstrap.php";

// only run from command line
$isCli = PHP_SAPI === 'cli';

if ($isCli === false) {
    exit(CLI\ERROR);
}

// Handle Ctrl+C gracefully (if pcntl extension is available)
if ($isCli && function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function (): void {
        echo PHP_EOL . PHP_EOL;
        echo "⚠️  Interface sync cancelled by user." . PHP_EOL;
        exit(CLI\SIGINT); // Standard exit code for SIGINT
    });
}



declare(ticks=1);

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\InterfacingService;
use App\Services\InstrumentActivityService;
use App\Services\InstrumentUsageStatisticsService;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;
use Symfony\Component\Uid\Uuid;

if (!isset(SYSTEM_CONFIG['interfacing']['enabled']) || SYSTEM_CONFIG['interfacing']['enabled'] === false) {
    MiscUtility::safeCliEcho('⚠️  Interfacing is not enabled. Please enable it in configuration.' . PHP_EOL);
    LoggerUtility::logError('Interfacing is not enabled. Please enable it in configuration.');
    exit;
}



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TestResultsService $testResultsService */
$testResultsService = ContainerRegistry::get(TestResultsService::class);


$forceExecution = false; // Default: exclude locked samples
$lastInterfaceSync = null;
$silent = false;

foreach ($argv as $arg) {
    if (str_contains($arg, 'force')) {
        $forceExecution = true;
    }

    if (!isset($lastInterfaceSync)) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg) && DateUtility::isDateFormatValid($arg, 'Y-m-d')) {
            $lastInterfaceSync = DateUtility::getDateTime($arg, 'Y-m-d');
        } elseif (is_numeric($arg)) {
            $lastInterfaceSync = DateUtility::daysAgo((int) $arg);
        } elseif (preg_match('/^(\d+)force/', $arg, $matches)) {
            $lastInterfaceSync = DateUtility::daysAgo((int) $matches[1]);
            $forceExecution = true;
        }
    }

    if (str_contains($arg, 'silent')) {
        $silent = true;
    }
}

$lockFile = MiscUtility::getLockFile(__FILE__);

// Only handle lock file if force is NOT used
// Check if the lock file already exists
if (!$forceExecution && !MiscUtility::isLockFileExpired($lockFile)) {
    echo "Another instance of the script : " . basename(__FILE__) . " is already running." . PHP_EOL;
    exit;
}

MiscUtility::touchLockFile($lockFile); // Create or update the lock file
MiscUtility::setupSignalHandler($lockFile);



$mysqlConnected = false;
$sqliteConnected = false;

if (!empty(SYSTEM_CONFIG['interfacing']['database']['host']) && !empty(SYSTEM_CONFIG['interfacing']['database']['username'])) {
    $mysqlConnected = true;
    $db->addConnection('interface', SYSTEM_CONFIG['interfacing']['database']);
}

// Default to database value if no valid date or days were provided
if ($lastInterfaceSync === null) {
    $lastInterfaceSync = $db->connection('default')->getValue('s_vlsm_instance', 'last_interface_sync');
}

$labId = $general->getSystemConfig('sc_testing_lab_id');

if (empty($labId)) {
    LoggerUtility::logError("No Lab ID set in System Config. Skipping Interfacing Results");
    exit(CLI\ERROR);
}

$sqliteDb = null;
$syncedIds = [];
$unsyncedIds = [];
$latestAddedOn = null; // Track the latest added_on value


if (!empty(SYSTEM_CONFIG['interfacing']['sqlite3Path'])) {
    $sqliteConnected = true;
    $sqliteDb = new \PDO("sqlite:" . SYSTEM_CONFIG['interfacing']['sqlite3Path']);
}

try {
    $interfaceData = [];
    //get the value from interfacing DB
    if ($mysqlConnected) {
        if ($isCli) {
            echo "Connected to MySQL" . PHP_EOL;
        }

        if (!empty($lastInterfaceSync)) {
            $db->connection('interface')
                ->where(" (added_on > '$lastInterfaceSync' OR lims_sync_status = 0) ");
        } else {
            $db->connection('interface')
                ->where(" lims_sync_status = 0 ");
        }
        // result_status = 1 means Final (F). Failed runs (ASTM 'X') are stored with
        // result_status = 0 by vlsm-interfacing but carry results = 'Failed' — pull
        // those in too so the VL/EID/Hepatitis branches can mark them TEST_FAILED.
        $db->connection('interface')->where(
            "(result_status = 1 OR LOWER(TRIM(IFNULL(results,''))) IN ('failed','fail','failure','invalid','inconclusive','error','err'))"
        );
        $db->connection('interface')->orderBy('analysed_date_time', 'asc');
        $mysqlData = $db->connection('interface')->get('orders');
        if ($isCli) {
            echo "# of records from MySQL : " . count($mysqlData) . PHP_EOL;
        }
        $interfaceData = [...$interfaceData, ...$mysqlData]; // Add MySQL data
    }

    if ($sqliteConnected) {
        if ($isCli) {
            echo "Connected to sqlite" . PHP_EOL;
        }
        $where = [];
        // result_status = 1 means Final (F). Failed runs (ASTM 'X') are stored with
        // result_status = 0 by vlsm-interfacing but carry results = 'Failed' — pull
        // those in too so the VL/EID/Hepatitis branches can mark them TEST_FAILED.
        $where[] = " (result_status = 1 OR LOWER(TRIM(IFNULL(results,''))) IN ('failed','fail','failure','invalid','inconclusive','error','err')) ";
        if (!empty($lastInterfaceSync)) {
            $where[] = " (added_on > '$lastInterfaceSync' OR lims_sync_status = 0) ";
        }
        $where = implode(' AND ', $where);
        $interfaceQuery = "SELECT * FROM `orders`
                            WHERE $where
                            ORDER BY analysed_date_time ASC";

        $sqliteData = $sqliteDb->query($interfaceQuery)->fetchAll(PDO::FETCH_ASSOC);
        if ($isCli) {
            echo "# of records from SQLITE3 : " . count($sqliteData) . PHP_EOL;
        }
        $interfaceData = [...$interfaceData, ...$sqliteData]; // Add SQLite data
    }


    /** @var InterfacingService $interfacingService */
    $interfacingService = ContainerRegistry::get(InterfacingService::class);

    // Drop the log-unit row where the same order also reported a copies value.
    $filtered = $interfacingService->filterDuplicateUnits($interfaceData);

    $filteredIds = array_column($filtered, 'id');
    $skippedIds = [];

    foreach ($interfaceData as $row) {
        if (!in_array($row['id'], $filteredIds, true)) {
            $skippedIds[] = $row['id'];
        }
    }


    $interfaceData = $filtered;

    // A run with no new results is not the end of the run: the instrument activity and
    // the daily volume below still have to be collected, and on a quiet day they are
    // the only thing there is to collect.
    $numberOfResults = 0;
    $totalResults = count($interfaceData); // Get the total number of items
    if ($isCli) {
        echo $totalResults === 0
            ? "No results to process" . PHP_EOL
            : "Processing $totalResults filtered results from Interface Tool" . PHP_EOL;
    }

    foreach ($interfaceData as $key => $result) {
        // This is to prevent the lock file from being deleted by the signal handler
        // and to keep the script running
        // touch the lock file every 10 iterations to reduce the number of times disk is accessed
        if ($key % 10 === 0) {
            MiscUtility::touchLockFile($lockFile);
        }

        $db->connection('default')->beginTransaction();
        if ($isCli) {
            MiscUtility::progressBar($key + 1, $totalResults); // Update progress bar
        }

        $outcome = $interfacingService->importResult(
            $result,
            (int) $labId,
            includeLocked: $forceExecution,
            updateModifiedTime: !$silent
        );

        // A failed UPDATE is deliberately left unmarked: the row keeps lims_sync_status = 0
        // so the next run picks it up again, instead of being written off as unsyncable.
        if ($outcome['reason'] !== 'update_failed') {
            if ($outcome['synced']) {
                $syncedIds[] = $result['id'];
            } else {
                $unsyncedIds[] = $result['id'];
            }
        }

        if ($outcome['updated']) {
            $numberOfResults++;
        }

        if (!empty($result['added_on'])) {
            $addedOn = DateUtility::getDateTime((string) $result['added_on']);
            if ($addedOn !== null && ($latestAddedOn === null || $addedOn > $latestAddedOn)) {
                $latestAddedOn = $addedOn;
            }
        }

        $db->connection('default')->commitTransaction();
    }

    if ($numberOfResults > 0) {
        $importedBy = $_SESSION['userId'] ?? 'AUTO';
        $testResultsService->resultImportStats($numberOfResults, 'interface', $importedBy);
    }

    // Instrument activity the Interface Tool recorded alongside the results. Older
    // versions of the tool do not have this table, so its absence is not an error.
    if ($mysqlConnected) {
        try {
            $activityRows = $db->connection('interface')->rawQuery(
                "SELECT * FROM telemetry_events
                  WHERE remote_uploaded_at IS NULL
                  ORDER BY occurred_at ASC
                  LIMIT 1000"
            ) ?: [];

            if ($activityRows !== []) {
                /** @var InstrumentActivityService $instrumentActivity */
                $instrumentActivity = ContainerRegistry::get(InstrumentActivityService::class);
                $summary = $instrumentActivity->store(
                    $activityRows,
                    (int) $labId,
                    InstrumentActivityService::VIA_IMPORTER
                );

                // Mark everything that was read, not just what was newly stored: a row we
                // already held is still dealt with, and leaving it would re-read it forever.
                $handledIds = array_column($activityRows, 'event_id');
                if ($handledIds !== []) {
                    $placeholders = implode(',', array_fill(0, count($handledIds), '?'));
                    $db->connection('interface')->rawQuery(
                        "UPDATE telemetry_events
                            SET remote_uploaded_at = ?, remote_batch_id = ?
                          WHERE event_id IN ($placeholders)",
                        [DateUtility::getCurrentDateTime(), Uuid::v4()->toRfc4122(), ...$handledIds]
                    );
                }

                if ($isCli) {
                    echo "Instrument activity: {$summary['stored']} stored, "
                        . "{$summary['duplicates']} already held, {$summary['skipped']} skipped" . PHP_EOL;
                }
            }
        } catch (Throwable $activityError) {
            LoggerUtility::logInfo('Instrument activity not imported: ' . $activityError->getMessage());
        }

        // Daily test volume per instrument. Kept separate from the activity above
        // because a summary is revised through the day rather than being a one-off
        // event: the tool raises its revision each time, and only the exact revision
        // we were given is acknowledged, so a revision written while we were working
        // is picked up on the next run instead of being skipped.
        try {
            $usageRows = $db->connection('interface')->rawQuery(
                "SELECT * FROM usage_statistics_daily
                  WHERE revision > COALESCE(remote_uploaded_revision, 0)
                  ORDER BY activity_date ASC, id ASC
                  LIMIT 500"
            ) ?: [];

            if ($usageRows !== []) {
                /** @var InstrumentUsageStatisticsService $usageStatistics */
                $usageStatistics = ContainerRegistry::get(InstrumentUsageStatisticsService::class);

                $usageTotals = ['stored' => 0, 'updated' => 0, 'duplicates' => 0, 'stale' => 0, 'rejected' => 0];
                $usageFailed = 0;
                $usageRejectedRows = [];

                // Only the exact revision that was sent is acknowledged, so a revision the
                // tool wrote while we were working is picked up next run rather than skipped.
                $acknowledge = function (array $usageRow) use ($db): void {
                    $db->connection('interface')->rawQuery(
                        "UPDATE usage_statistics_daily
                            SET remote_uploaded_revision = ?
                          WHERE aggregate_id = ? AND revision = ?",
                        [$usageRow['revision'], $usageRow['aggregate_id'], $usageRow['revision']]
                    );
                };

                // One summary at a time, so a row that cannot be stored costs only itself.
                // Storing the batch in one call would mean a single bad row leaves every
                // row in the window unacknowledged, and the next run reads the same window
                // and fails on the same row -- usage statistics would never advance again.
                foreach ($usageRows as $usageRow) {
                    try {
                        $rowSummary = $usageStatistics->store(
                            [$usageRow],
                            (int) $labId,
                            InstrumentUsageStatisticsService::VIA_IMPORTER
                        );
                    } catch (Throwable $rowError) {
                        // Left unacknowledged on purpose: unlike a rejected summary this
                        // may well succeed next run, so it keeps its place in the queue.
                        $usageFailed++;
                        LoggerUtility::logError('Usage statistic could not be stored: ' . $rowError->getMessage(), [
                            'labId' => (int) $labId,
                            'aggregateId' => $usageRow['aggregate_id'] ?? null,
                        ]);
                        continue;
                    }

                    foreach ($usageTotals as $key => $value) {
                        $usageTotals[$key] = $value + $rowSummary[$key];
                    }

                    // A rejected summary is held back until we know it is an isolated bad
                    // row rather than a schema mismatch. Acknowledging it immediately
                    // would be one-way: if the tool's columns did not match, every row
                    // would be rejected and marked uploaded, and no amount of fixing this
                    // side afterwards could get the data back.
                    if ($rowSummary['rejected'] > 0) {
                        $usageRejectedRows[] = $usageRow;
                        LoggerUtility::logWarning('Usage statistic rejected as unusable', [
                            'labId' => (int) $labId,
                            'aggregateId' => $usageRow['aggregate_id'] ?? null,
                            'revision' => $usageRow['revision'] ?? null,
                        ]);
                        continue;
                    }

                    $acknowledge($usageRow);
                }

                // Something in this window was usable, so the rejects really are bad rows
                // and will be just as bad next run. Acknowledge them, or they would sit at
                // the front of the window forever and starve everything behind them.
                $usableRows = $usageTotals['stored'] + $usageTotals['updated']
                    + $usageTotals['duplicates'] + $usageTotals['stale'];
                if ($usageRejectedRows !== [] && $usableRows > 0) {
                    foreach ($usageRejectedRows as $rejectedRow) {
                        $acknowledge($rejectedRow);
                    }
                } elseif ($usageRejectedRows !== []) {
                    // Nothing at all was usable. That looks like the tool's table not
                    // matching what is expected rather than a handful of bad rows, so
                    // nothing is acknowledged and the data stays recoverable.
                    LoggerUtility::logError('Every usage statistic read was rejected; none acknowledged', [
                        'labId' => (int) $labId,
                        'read' => count($usageRows),
                    ]);
                }

                if ($isCli) {
                    echo "Usage statistics: {$usageTotals['stored']} stored, "
                        . "{$usageTotals['updated']} updated, {$usageTotals['duplicates']} already held, "
                        . "{$usageTotals['stale']} stale, {$usageTotals['rejected']} rejected, "
                        . "$usageFailed failed" . PHP_EOL;
                }
            }
        } catch (Throwable $usageError) {
            // Reading the table at all failed. Older versions of the tool do not have it,
            // which is the ordinary case and not an error.
            LoggerUtility::logInfo('Usage statistics not imported: ' . $usageError->getMessage());
        }
    }

} catch (Throwable $e) {
    $db->connection('default')->rollbackTransaction();
    // Asking for the interface connection when it was never added throws, which here
    // would replace the real error with a misleading one and skip the logging entirely.
    $context = [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_default_db_query' => $db->connection('default')->getLastQuery(),
        'last_default_db_error' => $db->connection('default')->getLastError(),
        'trace' => $e->getTraceAsString()
    ];
    if ($mysqlConnected) {
        $context['last_interface_db_query'] = $db->connection('interface')->getLastQuery();
        $context['last_interface_db_error'] = $db->connection('interface')->getLastError();
    }
    LoggerUtility::logError($e->getMessage(), $context);
} finally {

    $batchSize = 1000;

    $updateSyncStatus = function ($db, $sqliteDb, $ids, $status, $mysqlConnected, $sqliteConnected) use ($batchSize): void {
        if (!empty($ids)) {
            $currentDateTime = DateUtility::getCurrentDateTime();

            $totalBatches = ceil(count($ids) / $batchSize);

            for ($i = 0; $i < $totalBatches; $i++) {
                $batchIds = array_slice($ids, $i * $batchSize, $batchSize);

                // Update MySQL
                if ($mysqlConnected) {
                    $interfaceData = [
                        'lims_sync_status' => $status,
                        'lims_sync_date_time' => $currentDateTime,
                    ];

                    $db->connection('interface')->reset();
                    $db->connection('interface')->where('id', $batchIds, 'IN');
                    $db->connection('interface')->update('orders', $interfaceData);
                }

                // Update SQLite
                if ($sqliteConnected) {
                    $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
                    $sql = "UPDATE orders
                        SET lims_sync_status = ?, lims_sync_date_time = ?
                        WHERE id IN ($placeholders)";
                    $stmt = $sqliteDb->prepare($sql);
                    $stmt->bindValue(1, $status, PDO::PARAM_INT);
                    $stmt->bindValue(2, $currentDateTime);

                    foreach ($batchIds as $index => $id) {
                        $stmt->bindValue($index + 3, $id, PDO::PARAM_INT);
                    }

                    $stmt->execute();
                }
            }
        }
    };


    try {
        // The interface connection only exists when interfacing MySQL is configured. On a
        // SQLite-only install asking for it throws, and a throw here would escape the
        // catch below (which would ask for it again) and leave the lock file behind.
        if ($mysqlConnected) {
            $db->connection('interface')->beginTransaction();
        }

        // Update synced IDs
        $updateSyncStatus($db, $sqliteDb, $syncedIds, 1, $mysqlConnected, $sqliteConnected);

        // Update unsynced IDs
        $updateSyncStatus($db, $sqliteDb, $unsyncedIds, 2, $mysqlConnected, $sqliteConnected);
        $updateSyncStatus($db, $sqliteDb, $skippedIds, 2, $mysqlConnected, $sqliteConnected);

        if ($mysqlConnected) {
            $db->connection('interface')->commitTransaction();
        }
    } catch (Throwable $e) {
        if ($isCli) {
            echo "Error while syncing interface results. Please check error log for more details." . PHP_EOL;
        }
        $context = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'last_default_db_query' => $db->connection('default')->getLastQuery(),
            'last_default_db_error' => $db->connection('default')->getLastError(),
            'trace' => $e->getTraceAsString()
        ];
        if ($mysqlConnected) {
            $db->connection('interface')->rollbackTransaction();
            $context['last_interface_db_query'] = $db->connection('interface')->getLastQuery();
            $context['last_interface_db_error'] = $db->connection('interface')->getLastError();
        }
        LoggerUtility::logError($e->getMessage(), $context);
    }
    // Close SQLite connection
    if ($sqliteConnected && $sqliteDb instanceof \PDO) {
        $sqliteDb = null;
    }

    if ($latestAddedOn !== null) {
        // Update s_vlsm_instance with the maximum added_on
        $db->connection('default')->update('s_vlsm_instance', ['last_interface_sync' => $latestAddedOn]);
    }

    // Delete the lock file after execution completes
    MiscUtility::deleteLockFile($lockFile);
}

#!/usr/bin/env php
<?php

// Push outbound inter-lab referrals (REFERRED status) to the configured partner
// system, across every test module that supports lab-to-lab referrals.
//
// To enable referrals for a new test type, add an entry to $referralConfig below;
// the form table and primary key are resolved from TestsService.

use const SAMPLE_STATUS\REFERRED;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

require_once __DIR__ . '/../bootstrap.php';

// Per-module referral metadata. Key = test module; the form table and primary
// key come from TestsService, so only the referral-history target is listed here.
$referralConfig = [
    'tb' => [
        'historyTable'    => 'tb_referral_history',
        'historyIdColumn' => 'tb_id',
    ],
    'generic-tests' => [
        'historyTable'    => 'generic_referral_history',
        'historyIdColumn' => 'generic_id',
    ],
];

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$lockFile = MiscUtility::getLockFile(__FILE__);

if (!MiscUtility::isLockFileExpired($lockFile)) {
    echo "Another instance of " . basename(__FILE__) . " is already running." . PHP_EOL;
    exit(0);
}

MiscUtility::touchLockFile($lockFile);
MiscUtility::setupSignalHandler($lockFile);

$module = null;

try {
    // 1. Check if script is running in CLI mode
    if (PHP_SAPI !== 'cli') {
        echo "This script can only be run from the command line interface (CLI).";
        exit(0);
    }
    // 2. Check if script is running in STS mode
    if (!$general->isSTSInstance()) {
        echo "This script can only be run in STS mode.";
        exit(0);
    }

    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    foreach ($referralConfig as $module => $config) {
        // Skip modules that are not active on this instance.
        if (!TestsService::isTestActive($module)) {
            continue;
        }

        $tableName       = TestsService::getTestTableName($module);
        $primaryKey      = TestsService::getPrimaryColumn($module);
        $historyTable    = $config['historyTable'];
        $historyIdColumn = $config['historyIdColumn'];

        // Select samples that have been referred to a different lab.
        $db->reset();
        $db->where("lab_id != referred_to_lab_id");
        $db->where("referred_to_lab_id IS NOT NULL");
        $samplesToUpdate = $db->get($tableName);

        if (empty($samplesToUpdate)) {
            continue;
        }

        foreach ($samplesToUpdate as $sample) {
            $sampleId = $sample[$primaryKey];
            $oldLabId = $sample['lab_id'];
            $newLabId = $sample['referred_to_lab_id'];

            // Move the sample to the referred-to lab and flag it for re-sync.
            $updateData = [
                'lab_id'                 => $newLabId,
                'result_status'          => REFERRED,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync'              => 0
            ];

            $db->reset();
            $db->where($primaryKey, $sampleId);
            $updated = $db->update($tableName, $updateData);

            if ($updated) {
                $historyData = [
                    $historyIdColumn      => $sampleId,
                    'from_lab_id'         => $oldLabId,
                    'to_lab_id'           => $newLabId,
                    'reason_for_referral' => $sample['reason_for_referral'],
                    'referred_on_datetime' => $sample['referred_on_datetime'] ?? DateUtility::getCurrentDateTime(),
                    'referred_by'         => $sample['referred_by']
                ];

                $db->insert($historyTable, $historyData);
            }
        }
    }
} catch (Throwable $e) {
    echo "Error occurred: " . $e->getMessage() . PHP_EOL;
    LoggerUtility::logError($e->getMessage(), [
        'module'        => $module,
        'last_db_error' => isset($db) ? $db->getLastError() : null,
        'last_db_query' => isset($db) ? $db->getLastQuery() : null,
        'file'          => $e->getFile(),
        'line'          => $e->getLine(),
        'trace'         => $e->getTraceAsString(),
    ]);
} finally {
    MiscUtility::deleteLockFile(__FILE__);
}

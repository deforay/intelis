#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../../bootstrap.php");

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\TestsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

foreach (SYSTEM_CONFIG['modules'] as $module => $status) {
    $tableName = TestsService::getTestTableName($module);
    if ($status === true && !empty($tableName)) {
        //FAILED SAMPLES
        if ($module === 'vl') {
            $db->where("(result LIKE 'fail%' OR result = 'failed' OR result LIKE 'err%' OR result LIKE 'error')");
            $db->where("result_status != " . SAMPLE_STATUS\REJECTED); // not rejected
            $db->where("result_status != " . SAMPLE_STATUS\TEST_FAILED); // not already in failed status
            $db->update(
                $tableName,
                ["result_status" => SAMPLE_STATUS\TEST_FAILED]
            );
        }

        //EXPIRING SAMPLES
        $expiryDays = (int) ($general->getGlobalConfig('sample_expiry_after_days') ?? 365);
        if (empty($expiryDays) || $expiryDays <= 0) {
            $expiryDays = 365; // by default, we consider samples more than 1 year as expired
        }
        // if sample is not rejected, accepted or expired only then update it to expired
        $statusCodes = [SAMPLE_STATUS\REJECTED, SAMPLE_STATUS\ACCEPTED, SAMPLE_STATUS\EXPIRED];
        $db->where("result_status", $statusCodes, 'NOT IN');
        $db->where("(DATEDIFF(CURRENT_DATE, `sample_collection_date`)) > $expiryDays");
        $db->update(
            $tableName,
            [
                "result_status" => SAMPLE_STATUS\EXPIRED,
                "locked" => "yes"
            ]
        );


        //LOCKING SAMPLES
        $lockExpiryDays = (int) ($general->getGlobalConfig('sample_lock_after_days') ?? 14);
        if (empty($lockExpiryDays) || $lockExpiryDays <= 0) {
            $lockExpiryDays = 14;
        }
        if ($lockExpiryDays != null && $lockExpiryDays >= 0) {
            $db->where("(result_status = " . SAMPLE_STATUS\REJECTED . " OR result_status = " . SAMPLE_STATUS\ACCEPTED . ")"); // Samples that are Accepted, Rejected
            $db->where("locked NOT LIKE 'yes'");
            $db->where("(DATEDIFF(CURRENT_DATE, `last_modified_datetime`)) > $lockExpiryDays");
            $db->update($tableName, ["locked" => "yes"]);
        }
    }
}

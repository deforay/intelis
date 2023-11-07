#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../../bootstrap.php");

use App\Registries\ContainerRegistry;
use App\Services\VlService;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

$sql = sprintf(
    "SELECT vl_sample_id,
            result_value_absolute_decimal,
            result_value_text,
            result,
            result_status
    FROM form_vl
    WHERE (
            (result_status = %d OR result_status = %d)
            OR result is not null
        )
    AND vl_result_category is null",
    SAMPLE_STATUS\REJECTED,
    SAMPLE_STATUS\ACCEPTED
);

$result = $db->rawQuery($sql);

foreach ($result as $aRow) {

    $vlResultCategory = $vlService->getVLResultCategory($aRow['result_status'], $aRow['result']);

    if (!empty($vlResultCategory)) {

        $db->where('vl_sample_id', $aRow['vl_sample_id']);
        $dataToUpdate = [];
        $dataToUpdate['vl_result_category'] = $vlResultCategory;
        if ($vlResultCategory == 'failed' || $vlResultCategory == 'invalid') {
            $dataToUpdate['result_status'] = SAMPLE_STATUS\TEST_FAILED;
        } elseif ($vlResultCategory == 'rejected') {
            $dataToUpdate['result_status'] = SAMPLE_STATUS\REJECTED;
        }
        $res = $db->update("form_vl", $dataToUpdate);
    }
}

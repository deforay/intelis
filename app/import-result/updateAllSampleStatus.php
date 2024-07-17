<?php

use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


try {

    $db = ContainerRegistry::get(DatabaseService::class);

    $status = [
        'result_status' => SAMPLE_STATUS\ON_HOLD
    ];

    $db->where("imported_by = '" . $_SESSION['userId'] . "'");
    $db->where("(result LIKE 'fail%' OR result = 'failed' OR result LIKE 'err%' OR result LIKE 'error')");
    $result = $db->update('temp_sample_import', $status);



    $status = [
        'result_status' => SAMPLE_STATUS\ACCEPTED
    ];

    $db->where("imported_by = '" . $_SESSION['userId'] . "'");
    $db->where("result_status is null OR result_status = '' OR result_status = " . SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB);
    $result = $db->update('temp_sample_import', $status);
} catch (Exception $exc) {
    LoggerUtility::log("error", $db->getLastQuery());
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $exc->getTraceAsString(),
    ]);
}

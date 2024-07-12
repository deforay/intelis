<?php

use App\Utilities\LoggerUtility;


try {

    $status = [
        'result_status' => SAMPLE_STATUS\ACCEPTED
    ];

    $db->where("result_status is null OR result_status = '' OR result_status = " . SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB);
    $result = $db->update('temp_sample_import', $status);
} catch (Exception $exc) {
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $exc->getTraceAsString(),
    ]);
}

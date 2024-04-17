<?php

use App\Utilities\LoggerUtility;


try {

    $status = array(
        'result_status' => 7,
    );

    $db->where("result_status is null OR result_status = '' OR result_status = 6");
    $result = $db->update('temp_sample_import', $status);
} catch (Exception $exc) {
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $exc->getTraceAsString(),
    ]);
}

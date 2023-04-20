<?php


try {

    $status = array(
        'result_status' => 7,
    );

    $db->where("result_status is null OR result_status = '' OR result_status = 6");
    $result = $db->update('temp_sample_import', $status);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

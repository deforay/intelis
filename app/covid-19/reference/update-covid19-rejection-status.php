<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_covid19_sample_rejection_reasons";
try {

    // Sanitize values before using them below
    $_POST = array_map('htmlspecialchars', $_POST);

    $id = explode(",", $_POST['id']);
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'rejection_reason_status' => $_POST['status'],
            'updated_datetime'     =>  DateUtility::getCurrentDateTime(),
        );
        $db = $db->where('rejection_reason_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;

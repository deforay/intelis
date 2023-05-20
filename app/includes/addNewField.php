<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "rejection_type";
$value = trim($_POST['value']);
$data = 0;
if ($value != '') {
    $rej = "SELECT * FROM rejection_type WHERE rejection_type = ? ";
    $rejInfo = $db->rawQuery($rej, [$value]);

    if (empty($rejInfo)) {
        $data = array(
            'rejection_type' => $value,
            'updated_datetime' => DateUtility::getCurrentDateTime(),
        );

        $db->insert($tableName, $data);
        $lastId = $db->getInsertId();
    }
}

if ($data > 0) {
    $data = '1';
} else {
    $data = '0';
}
echo $data;

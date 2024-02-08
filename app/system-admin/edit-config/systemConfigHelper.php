<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$systemConfigFields = [
    'sc_testing_lab_id',
    'sc_user_type',
    'sup_email',
    'sup_password'
];

$globalConfigFields = [
    'default_time_zone'
];

$tableName = "system_config";
try {
    $currentDateTime = DateUtility::getCurrentDateTime();
    foreach ($systemConfigFields as $fieldName) {
        $data = [
            'value' => $_POST[$fieldName] ?? null
        ];
        $db->where('name', $fieldName);
        $db->update('system_config', $data);
    }

    foreach ($globalConfigFields as $fieldName) {
        $data = [
            'value' => $_POST[$fieldName] ?? null,
            'updated_on' => $currentDateTime
        ];
        $db->where('name', $fieldName);
        $db->update('global_config', $data);
    }



    // Clear file cache
    (ContainerRegistry::get(FileCacheUtility::class))->clear();

    $_SESSION['alertMsg'] = _translate("System Configuration updated successfully.");
    header("Location:index.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

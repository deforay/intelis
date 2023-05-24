<?php

use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

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
        $db = $db->where('name', $fieldName);
        $db->update('system_config', $data);
    }
    foreach ($globalConfigFields as $fieldName) {
        $data = [
            'value' => $_POST[$fieldName] ?? null,
            'updated_on' => $currentDateTime
        ];
        $db = $db->where('name', $fieldName);
        $db->update('global_config', $data);
    }

    $_SESSION['alertMsg'] = _("System Configuration updated successfully.");
    header("Location:index.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

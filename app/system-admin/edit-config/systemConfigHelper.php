<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;
use App\Services\ConfigService;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */

/** @var ConfigService $configService */
$configService = ContainerRegistry::get(ConfigService::class);

$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
$modulesToEnable = $_POST['enabledModules'];
$systemConfigFields = [
    'sc_testing_lab_id',
    'sc_user_type',
    'sup_email',
    'sup_password'
];

$globalConfigFields = [
    'vl_form',
    'default_time_zone',
    'app_locale'
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
            'updated_datetime' => $currentDateTime
        ];
        $db->where('name', $fieldName);
        $db->update('global_config', $data);
    }

    $updatedConfig = [
        'remoteURL' => $remoteUrl,
        'modules.vl' => in_array('vl', $modulesToEnable) ? true : false,
        'modules.eid' => in_array('eid', $modulesToEnable) ? true : false,
        'modules.covid19' => in_array('covid19', $modulesToEnable) ? true : false,
        'modules.hepatitis' => in_array('hepatitis', $modulesToEnable) ? true : false,
        'modules.tb' => in_array('tb', $modulesToEnable) ? true : false,
        'modules.cd4' => in_array('cd4', $modulesToEnable) ? true : false,
        'modules.generic-tests' => in_array('generic-tests', $modulesToEnable) ? true : false,
        'database.host' => (isset($_POST['dbHostName']) && !empty($_POST['dbHostName'])) ? $_POST['dbHostName'] : '127.0.0.1',
        'database.username' => (isset($_POST['dbUserName']) && !empty($_POST['dbUserName'])) ? $_POST['dbUserName'] : 'root',
        'database.password' => (isset($_POST['dbPassword']) && !empty($_POST['dbPassword'])) ? $_POST['dbPassword'] : 'zaq12345',
        'database.db' => (isset($_POST['dbName']) && !empty($_POST['dbName'])) ? $_POST['dbName'] : 'vlsm',
        'database.port' => (isset($_POST['dbPort']) && !empty($_POST['dbPort'])) ? $_POST['dbPort'] : 3306,
    ];


    $configService->updateConfig($updatedConfig);

    // Clear file cache
    (ContainerRegistry::get(FileCacheUtility::class))->clear();
    unset($_SESSION['instance']);

    $_SESSION['alertMsg'] = _translate("System Configuration updated successfully.");
    header("Location:index.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

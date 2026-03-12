<?php
// system-admin/edit-config/systemConfigHelper.php
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\ConfigService;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;

/** @var ConfigService $configService */
$configService = ContainerRegistry::get(ConfigService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$rawPost = $request->getParsedBody();
$_POST = _sanitizeInput($rawPost);

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
        'remoteURL' => $_POST['remoteURL'] ?? $general->getRemoteUrl(),
        'modules.vl' => in_array('vl', $modulesToEnable),
        'modules.eid' => in_array('eid', $modulesToEnable),
        'modules.covid19' => in_array('covid19', $modulesToEnable),
        'modules.hepatitis' => in_array('hepatitis', $modulesToEnable),
        'modules.tb' => in_array('tb', $modulesToEnable),
        'modules.cd4' => in_array('cd4', $modulesToEnable),
        'modules.generic-tests' => in_array('generic-tests', $modulesToEnable),
        'database.host' => !empty($rawPost['dbHostName']) ? $rawPost['dbHostName'] : '127.0.0.1',
        'database.username' => !empty($rawPost['dbUserName']) ? $rawPost['dbUserName'] : 'root',
        'database.password' => !empty($rawPost['dbPassword']) ? $rawPost['dbPassword'] : 'zaq12345',
        'database.db' => !empty($rawPost['dbName']) ? $rawPost['dbName'] : 'vlsm',
        'database.port' => !empty($rawPost['dbPort']) ? $rawPost['dbPort'] : 3306,
    ];
    $stsKey = SYSTEM_CONFIG['sts']['api_key'];
    if ($stsKey == '' || empty($stsKey) && trim((string) $_POST['sc_user_type']) === 'stsmode') {
        $updatedConfig['sts.api_key'] = $configService->generateAPIKeyForSTS();
    }


    $configService->updateConfig($updatedConfig);

    // Clear file cache
    (ContainerRegistry::get(FileCacheUtility::class))->clear();
    unset($_SESSION['instance']);

    $_SESSION['_systemAdmin']['alertMsg'] = _translate("System Configuration updated successfully.");
    header("Location:index.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

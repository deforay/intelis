<?php

use GuzzleHttp\Client;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\ConfigService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$userName = $_POST['userName'];
$emailId = $_POST['email'];
$loginId = $_POST['loginId'];
$password = $_POST['password'];
$vlForm = $_POST['vl_form'];
$timeZone = $_POST['default_time_zone'];
$locale = $_POST['app_locale'];
$remoteURL = $_POST['remoteURL'];
$modulesToEnable = $_POST['enabledModules'];

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var ConfigService $configService */
$configService = ContainerRegistry::get(ConfigService::class);

$activeModulesArr = SystemService::getActiveModules();

$stsURL = $general->getRemoteURL();

function changeModuleWithQuotes($moduleArr)
{
    return "'$moduleArr'";
}


try {
    if (!empty($userName) && !empty($password)) {

        // UPDATING s_vlsm_instance TABLE

        $data = [
            'vlsm_instance_id' => MiscUtility::generateULID(),
            'instance_mac_address' => MiscUtility::getMacAddress(),
            'instance_added_on' => DateUtility::getCurrentDateTime(),
            'instance_update_on' => DateUtility::getCurrentDateTime()
        ];

        // deleting just in case there is a row already inserted
        $db->delete('s_vlsm_instance');
        $db->insert('s_vlsm_instance', $data);


        // UPDATING SYSTEM CONFIG TABLE
        $instanceType = $_POST['instanceType'];
        $db->where('name', 'sc_user_type');
        $db->update("system_config", ['value' => $instanceType]);

        if (isset($_POST['testingLab']) && $_POST['testingLab'] != "") {
            $db->where('name', 'sc_testing_lab_id');
            $db->update("system_config", ['value' => $_POST['testingLab']]);
        }


        // UPDATING CONFIG FILE

        $updatedConfig = [
            'remoteURL' => $remoteURL,
            'modules.vl' => in_array('vl', $modulesToEnable),
            'modules.eid' => in_array('eid', $modulesToEnable),
            'modules.covid19' => in_array('covid19', $modulesToEnable),
            'modules.hepatitis' => in_array('hepatitis', $modulesToEnable),
            'modules.tb' => in_array('tb', $modulesToEnable),
            'modules.cd4' => in_array('cd4', $modulesToEnable),
            'modules.generic-tests' => in_array('generic-tests', $modulesToEnable),
            'database.host' => (!empty($_POST['dbHostName'])) ? $_POST['dbHostName'] : '127.0.0.1',
            'database.username' => (!empty($_POST['dbUserName'])) ? $_POST['dbUserName'] : 'root',
            'database.password' => (!empty($_POST['dbPassword'])) ? $_POST['dbPassword'] : 'zaq12345',
            'database.db' => (!empty($_POST['dbName'])) ? $_POST['dbName'] : 'vlsm',
            'database.port' => (!empty($_POST['dbPort'])) ? $_POST['dbPort'] : 3306,
        ];

        if (isset($instanceType) && trim($instanceType) == 'stsmode') {
            $updatedConfig['sts.api_key'] = $configService->generateAPIKeyForSTS();
        }


        $configService->updateConfig($updatedConfig);

        // If 'instance' is set in session, unset it
        if (isset($_SESSION['instance'])) {
            unset($_SESSION['instance']);
        }
        // Clear the file cache
        (ContainerRegistry::get(FileCacheUtility::class))->clear();


        $userPassword = $usersService->passwordHash($password);
        $userId = MiscUtility::generateUUID();

        $insertData = [
            'user_id' => $userId,
            'user_name' => $userName,
            'email' => $emailId,
            'login_id' => $loginId,
            'password' => $userPassword,
            'user_locale' => $locale,
            'role_id' => 1,
            'status' => 'active'
        ];
        $db->insert('user_details', $insertData);

        $configFields = [
            'vl_form',
            'default_time_zone',
            'app_locale'
        ];

        foreach ($configFields as $field) {
            if (isset($_POST[$field]) && !empty(trim((string) $_POST[$field]))) {
                $data = array('value' => trim((string) $_POST[$field]));
                $db->where('name', $field);
                $id = $db->update('global_config', $data);
            }
        }

        $modules = array_map("changeModuleWithQuotes", $activeModulesArr);

        $activeModules = implode(",", $modules);

        $privilegesSql = "SELECT p.privilege_id
                            FROM privileges AS p
                            INNER JOIN resources AS r ON r.resource_id=p.resource_id
                            WHERE r.module IN ($activeModules)";
        $privileges = $db->query($privilegesSql);
        foreach ($privileges as $privilege) {
            $privilegeId = $privilege['privilege_id'];
            $db->query("INSERT IGNORE INTO roles_privileges_map(role_id,privilege_id) VALUES (1,$privilegeId)");
        }

        if (!empty($stsURL) && $general->isLISInstance()) {
            $insertData['userId'] = $userId;
            $insertData['loginId'] = null; // We don't want to unintentionally end up creating admin users on STS
            $insertData['password'] = null; // We don't want to unintentionally end up creating admin users on STS
            $insertData['hashAlgorithm'] = 'phb'; // We don't want to unintentionally end up creating admin users on STS
            $insertData['role'] = 0; // We don't want to unintentionally end up creating admin users on STS
            $insertData['status'] = 'inactive';


            $apiUrl = $stsURL . "/api/v1.1/user/save-user-profile.php";
            $post = [
                'post' => json_encode($insertData),
                'x-api-key' => ConfigService::generateAPIKeyForSTS($stsURL)
            ];

            $client = new Client();
            $response = $client->post($apiUrl, [
                'form_params' => $post
            ]);

            $result = $response->getBody()->getContents();
        }

        $_SESSION['alertMsg'] = "New admin user added successfully";
    }
    header("Location:/login/login.php");
} catch (Throwable $exc) {
    LoggerUtility::log('error', $exc->getFile() . ':' . $exc->getLine()  . ':' .  $exc->getMessage(), [
        'exception' => $exc->getMessage(),
        'line' => __LINE__,
        'file' => __FILE__
    ]);
}

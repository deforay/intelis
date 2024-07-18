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
use App\Exceptions\SystemException;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = "user_details";
$userName = $_POST['userName'];
$emailId = $_POST['email'];
$loginId = $_POST['loginId'];
$password = $_POST['password'];
$vlForm = $_POST['vl_form'];
$timeZone = $_POST['default_time_zone'];
$locale = $_POST['app_locale'];
$remoteUrl = $_POST['remoteUrl'];
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


function changeModuleWithQuotes($moduleArr)
{
    return "'$moduleArr'";
}


try {
    if (!empty($userName) && !empty($password)) {

        // UPDATING s_vlsm_instance TABLE

        $data = [
            'vlsm_instance_id' => MiscUtility::generateUUID(),
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

        // If 'instance' is set in session, unset it
        if (isset($_SESSION['instance'])) {
            unset($_SESSION['instance']);
        }
        // Clear the file cache
        (ContainerRegistry::get(FileCacheUtility::class))->clear();


        $userPassword = $usersService->passwordHash($password);
        $userId = MiscUtility::generateUUID();

        $insertData = array(
            'user_id'           => $userId,
            'user_name'         => $userName,
            'email'             => $emailId,
            'login_id'          => $loginId,
            'password'          => $userPassword,
            'user_locale'       => $locale,
            'hash_algorithm'    => 'phb',
            'role_id'           => 1,
            'status'            => 'active'
        );
        $db->insert($tableName, $insertData);

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

        if (!empty($general->getRemoteURL()) && $general->isLISInstance()) {
            $insertData['userId'] = $userId;
            $insertData['loginId'] = null; // We don't want to unintentionally end up creating admin users on STS
            $insertData['password'] = null; // We don't want to unintentionally end up creating admin users on STS
            $insertData['hashAlgorithm'] = 'phb'; // We don't want to unintentionally end up creating admin users on STS
            $insertData['role'] = 0; // We don't want to unintentionally end up creating admin users on STS
            $insertData['status'] = 'inactive';


            $apiUrl = $general->getRemoteURL() . "/api/v1.1/user/save-user-profile.php";
            $post = [
                'post' => json_encode($insertData),
                'x-api-key' => MiscUtility::generateRandomString(18)
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
} catch (Exception | SystemException $exc) {
    LoggerUtility::log('error', $exc->getFile() . ':' . $exc->getLine()  . ':' .  $exc->getMessage(), [
        'exception' => $exc->getMessage(),
        'line' => __LINE__,
        'file' => __FILE__
    ]);
}

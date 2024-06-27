<?php

use App\Registries\AppRegistry;
use App\Utilities\MiscUtility;
use GuzzleHttp\Client;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\ConfigService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
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

$userType = $general->getSystemConfig('sc_user_type');

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


        $updatedConfig = [
            'remoteURL' => $remoteUrl,
            'modules.vl' => in_array('vl', $modulesToEnable) ? true : false,
            'modules.eid' => in_array('eid', $modulesToEnable) ? true : false,
            'modules.covid19' => in_array('covid19', $modulesToEnable) ? true : false,
            'modules.hepatitis' => in_array('hepatitis', $modulesToEnable) ? true : false,
            'modules.tb' => in_array('tb', $modulesToEnable) ? true : false,
            'modules.cd4' => in_array('cd4', $modulesToEnable) ? true : false,
            'modules.generic-tests' => in_array('generic-tests', $modulesToEnable) ? true : false,
        ];


        $configService->updateConfig($updatedConfig);


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

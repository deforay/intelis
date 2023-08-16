<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;
use App\Services\SystemService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$tableName = "user_details";
$configTableName = "global_config";
$userName = ($_POST['userName']);
$emailId = ($_POST['email']);
$loginId = ($_POST['loginId']);
$password = ($_POST['password']);
$vlForm = ($_POST['vl_form']);
$timeZone = ($_POST['default_time_zone']);
$locale = ($_POST['app_locale']);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$userType = $general->getSystemConfig('sc_user_type');

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$activeModulesArr = SystemService::getActiveModules();


function changeModuleWithQuotes($moduleArr)
{
    return "'$moduleArr'";
}


try {
    if (!empty($userName) && !empty($password)) {

        $userPassword = $usersService->passwordHash($password);
        $userId = $general->generateUUID();


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

        if (isset($_POST['vl_form']) && trim($_POST['vl_form']) != "") {
            $data = array('value' => trim($_POST['vl_form']));
            $db = $db->where('name', 'vl_form');
            $id = $db->update($configTableName, $data);
        }

        if (isset($_POST['default_time_zone']) && trim($_POST['default_time_zone']) != "") {
            $data = array('value' => trim($_POST['default_time_zone']));
            $db = $db->where('name', 'default_time_zone');
            $id = $db->update($configTableName, $data);
        }

        if (isset($_POST['app_locale']) && trim($_POST['app_locale']) != "") {
            $data = array('value' => trim($_POST['app_locale']));
            $db = $db->where('name', 'app_locale');
            $id = $db->update($configTableName, $data);
        }

        $modules = array_map("changeModuleWithQuotes", $activeModulesArr);

        $activeModules = implode(",", $modules);

        $privilegesSql = "SELECT p.privilege_id FROM privileges as p inner join resources as r on r.resource_id=p.resource_id WHERE r.module IN ($activeModules)";
        $privileges = $db->query($privilegesSql);
        foreach ($privileges as $privilege) {
            $privilegeId = $privilege['privilege_id'];
            $db->query("insert into roles_privileges_map(role_id,privilege_id) values (1,$privilegeId)");
        }

        if (!empty(SYSTEM_CONFIG['remoteURL']) && $userType == 'vluser') {
            $insertData['userId'] = $userId;
            $insertData['loginId'] = null; // We don't want to unintentionally end up creating admin users on STS
            $insertData['password'] = $usersService->passwordHash($general->generateRandomString()); // We don't want to unintentionally end up creating admin users on STS
            $insertData['hashAlgorithm'] = 'phb'; // We don't want to unintentionally end up creating admin users on STS
            $insertData['role'] = 0; // We don't want to unintentionally end up creating admin users on STS
            $insertData['status'] = 'inactive'; // so that we can retain whatever status is on server
            $apiUrl = SYSTEM_CONFIG['remoteURL'] . "/api/v1.1/user/save-user-profile.php";
            $post = array(
                'post' => json_encode($insertData),
                'x-api-key' => $general->generateRandomString(18)
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ($post));
            $result = curl_exec($ch);
            curl_close($ch);
            $deResult = json_decode($result, true);
        }

        $_SESSION['alertMsg'] = "New admin user added successfully";
    }
    header("Location:/login/login.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

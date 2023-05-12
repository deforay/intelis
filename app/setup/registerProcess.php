<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$tableName = "user_details";
$userName = ($_POST['userName']);
$emailId = ($_POST['email']);
$loginId = ($_POST['loginId']);
$password = ($_POST['password']);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$userType = $general->getSystemConfig('sc_user_type');

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


try {
    if (isset($userName) && !empty($userName) && isset($password) && !empty($password)) {

        $userPassword = $usersService->passwordHash($password);
        $userId = $general->generateUUID();


        $insertData = array(
            'user_id'           => $userId,
            'user_name'         => $userName,
            'email'             => $emailId,
            'login_id'          => $loginId,
            'password'          => $userPassword,
            'hash_algorithm'    => 'phb',
            'role_id'           => 1,
            'status'            => 'active'
        );
        $db->insert($tableName, $insertData);

        if (!empty(SYSTEM_CONFIG['remoteURL']) && $userType == 'vluser') {
            $insertData['userId'] = $userId;
            $insertData['loginId'] = null; // We don't want to unintentionally end up creating admin users on VLSTS
            $insertData['password'] = $usersService->passwordHash($general->generateRandomString()); // We don't want to unintentionally end up creating admin users on VLSTS
            $insertData['hashAlgorithm'] = 'phb'; // We don't want to unintentionally end up creating admin users on VLSTS
            $insertData['role'] = 0; // We don't want to unintentionally end up creating admin users on VLSTS
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

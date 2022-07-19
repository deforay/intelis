<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

$tableName = "user_details";
$userName = $db->escape($_POST['username']);
$emailId = $db->escape($_POST['email']);
$loginId = $db->escape($_POST['loginid']);
$password = $db->escape($_POST['password']);

$general = new \Vlsm\Models\General();
$userId = $general->generateUUID();


$user = new \Vlsm\Models\Users();

$userPassword = $user->passwordHash($password);

try {
    if (isset($_POST['username']) && !empty($_POST['username']) && isset($_POST['password']) && !empty($_POST['password'])) {
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
            $insertData['password'] = $user->passwordHash($general->generateRandomString()); // We don't want to unintentionally end up creating admin users on VLSTS
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
    header("location:/login/login.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

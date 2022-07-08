<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$userDb = new \Vlsm\Models\Users();
$tableName = "user_details";
$upId = 0;
/* To check the password update from the API */
$fromApiFalse = !isset($_POST['u']) && trim($_POST['u']) == "" && !isset($_POST['t']) && trim($_POST['t']) == "";
$fromApiTrue = isset($_POST['u']) && trim($_POST['u']) != "" && isset($_POST['t']) && trim($_POST['t']) != "" && SYSTEM_CONFIG['recency']['crosslogin'];

if ($fromApiTrue) {
    $_POST['userName'] = $_POST['u'];
    $_POST['password'] = $_POST['t'];
} else {
    $userId = base64_decode($_POST['userId']);
}

try {
    /* Check hash login id exist */
    $sha1protect = false;
    $hashCheckQuery = "SELECT `user_id`, `login_id`, `hash_algorithm`, `password` FROM user_details WHERE `login_id` = ?";
    $hashCheck = $db->rawQueryOne($hashCheckQuery, array($db->escape($_POST['userName'])));
    if (isset($hashCheck) && !empty($hashCheck['user_id']) && !empty($hashCheck['hash_algorithm'])) {
        if ($hashCheck['hash_algorithm'] == 'sha1') {
            $password = sha1($_POST['password'] . SYSTEM_CONFIG['passwordSalt']);
            $sha1protect = true;
        }
        if ($hashCheck['hash_algorithm'] == 'phb') {
            if (!password_verify($db->escape($_POST['password']), $hashCheck['password'])) {
                $_SESSION['alertMsg'] = _("Invalid password!");
                header("location:editProfile.php");
            }
            $password = $userDb->passwordHash($db->escape($_POST['password']), $userId);
        }
    } else {
        $password = sha1($_POST['password'] . SYSTEM_CONFIG['passwordSalt']);
    }
    // die($password);
    $queryParams = array($password);
    $admin = $db->rawQuery("SELECT * FROM user_details as ud WHERE ud.password = ?", $queryParams);
    if (count($admin) > 0) {
        $_SESSION['alertMsg'] = _("Your new password is too similar to your current password. Please try another password.");
    } else if (trim($_POST['userName']) != '') {
        if ($fromApiFalse) {
            $data = array(
                'user_name' => $_POST['userName'],
                'email' => $_POST['email'],
                'phone_number' => $_POST['phoneNo'],
            );
        }
        if ($fromApiTrue) {
            $data['user_name'] = $_POST['userName'];
            $data['password'] = $_POST['password'];
            $db = $db->where('user_name', $data['user_name']);
        } else {
            if (isset($_POST['password']) && trim($_POST['password']) != "") {
                if (SYSTEM_CONFIG['recency']['crosslogin']) {
                    $client = new \GuzzleHttp\Client();
                    $url = rtrim(SYSTEM_CONFIG['recency']['url'], "/");
                    $result = $client->post($url . '/api/update-password', [
                        'form_params' => [
                            'u' => $_POST['email'],
                            't' => $password
                        ]
                    ]);
                    $response = json_decode($result->getBody()->getContents());

                    if ($response->status == 'fail') {
                    }
                }
                $data['force_password_reset'] = $_SESSION['forcePasswordReset'] = 0;
            }
            $db = $db->where('user_id', $userId);
        }
        $upId = $db->update($tableName, $data);
        if ($fromApiTrue) {
            $response = array();
            if ($upId > 0) {
                $response['status'] = "success";
                $response['message'] = "Profile updated successfully!";
                print_r(json_encode($response));
            } else {
                $response['status'] = "fail";
                $response['message'] = "Profile not updated!";
                print_r(json_encode($response));
            }
        }
        /* Update Phb hash password */
        if ($sha1protect) {
            $password = $userDb->passwordHash($db->escape($_POST['password']), $userId);
            $db = $db->where('user_id', $userId);
            $db->update('user_details', array('password' => $password, 'hash_algorithm' => 'phb'));
        }
        if ($fromApiFalse) {
            $_SESSION['alertMsg'] = _("Your profile changes have been saved. You can continue using VLSM. Please click on any menu on the left to navigate");
        }
    }
    if ($fromApiFalse) {
        header("location:editProfile.php");
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

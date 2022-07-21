<?php

use Vlsm\Models\General;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$userModel = new \Vlsm\Models\Users();
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



    if (trim($_POST['userName']) != '') {
        if ($fromApiFalse) {
            $data = array(
                'user_name' => $_POST['userName'],
                'email' => $_POST['email'],
                'phone_number' => $_POST['phoneNo'],
            );
        }
        if ($fromApiTrue) {
            $data['user_name'] = $_POST['userName'];
            $decryptedPassword = General::decrypt($_POST['password'], base64_decode(SYSTEM_CONFIG['recency']['crossloginSalt']));
            $data['password'] = $decryptedPassword;            
            $db = $db->where('user_name', $data['user_name']);
        } else {
            if (isset($_POST['password']) && trim($_POST['password']) != "") {
                $userRow = $db->rawQueryOne("SELECT `password` FROM user_details as ud WHERE ud.user_id = ?", array($userId));
                if (password_verify($_POST['password'], $userRow['password'])) {
                    $_SESSION['alertMsg'] = _("Your new password cannot be same as the current password. Please try another password.");
                    header("location:editProfile.php");
                }

                if (SYSTEM_CONFIG['recency']['crosslogin']) {
                    $_SESSION['crossLoginPass']  = $newCrossLoginPassword = General::encrypt($_POST['password'], base64_decode(SYSTEM_CONFIG['recency']['crossloginSalt']));
                    $client = new \GuzzleHttp\Client();
                    $url = rtrim(SYSTEM_CONFIG['recency']['url'], "/");
                    $result = $client->post($url . '/api/update-password', [
                        'form_params' => [
                            'u' => $_POST['email'],
                            't' => $newCrossLoginPassword
                        ]
                    ]);
                    $response = json_decode($result->getBody()->getContents());

                    if ($response->status == 'fail') {
                        error_log('Recency profile not updated! for the user->' . $_POST['userName']);
                    }
                }

                $newPassword = $userModel->passwordHash($_POST['password']);
                $data['password'] = $newPassword;
                $data['hash_algorithm'] = 'phb';
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

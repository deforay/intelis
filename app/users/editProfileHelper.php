<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;
use GuzzleHttp\Client;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$userModel = ContainerRegistry::get(UsersService::class);
$tableName = "user_details";
$upId = 0;

/* Used to check if the password update is from the Recency Web App API */
$fromRecencyAPI = false;

if (SYSTEM_CONFIG['recency']['crosslogin'] && !empty($_POST['u']) && !empty($_POST['t'])) {
    $fromRecencyAPI = true;
}

if ($fromRecencyAPI === true) {
    $_POST['userName'] = $_POST['u'];
    $_POST['password'] = $_POST['t'];
} else {
    $userId = base64_decode($_POST['userId']);
}

try {

    if (!empty(trim($_POST['userName']))) {

        if ($fromRecencyAPI === true) {
            $data['user_name'] = $_POST['userName'];
            $decryptedPassword = CommonService::decrypt($_POST['password'], base64_decode(SYSTEM_CONFIG['recency']['crossloginSalt']));
            $data['password'] = $decryptedPassword;
            $db->where('user_name', $data['user_name']);
        } else {
            $data = array(
                'user_name' => $_POST['userName'],
                'email' => $_POST['email'],
                'phone_number' => $_POST['phoneNo'],
            );

            if (isset($_POST['password']) && trim($_POST['password']) != "") {
                $userRow = $db->rawQueryOne("SELECT `password` FROM user_details as ud WHERE ud.user_id = ?", array($userId));
                if (password_verify($_POST['password'], $userRow['password'])) {
                    $_SESSION['alertMsg'] = _("Your new password cannot be same as the current password. Please try another password.");
                    header("Location:editProfile.php");
                }

                if (SYSTEM_CONFIG['recency']['crosslogin']) {
                    $_SESSION['crossLoginPass']  = $newCrossLoginPassword = CommonService::encrypt($_POST['password'], base64_decode(SYSTEM_CONFIG['recency']['crossloginSalt']));
                    $client = new Client();
                    $url = rtrim(SYSTEM_CONFIG['recency']['url'], "/");
                    $result = $client->post($url . '/api/update-password', [
                        'form_params' => [
                            'u' => $_SESSION['loginId'],
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
            $db->where('user_id', $userId);
        }

        $upId = $db->update($tableName, $data);

        if ($fromRecencyAPI === true) {
            $response = [];
            if ($upId > 0) {
                $response['status'] = "success";
                $response['message'] = "Profile updated successfully!";
            } else {
                $response['status'] = "fail";
                $response['message'] = "Profile not updated!";
            }

            print_r(json_encode($response));
        } else {
            $_SESSION['alertMsg'] = _("Your profile changes have been saved. You can continue using VLSM. Please click on any menu on the left to navigate");
            header("Location:editProfile.php");
        }
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

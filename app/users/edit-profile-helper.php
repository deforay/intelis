<?php

use GuzzleHttp\Client;
use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var SystemService $systemService */
$systemService = ContainerRegistry::get(SystemService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

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
    $userId = null;
} else {
    $userId = base64_decode((string) $_POST['userId']);
}

try {

    if (!empty(trim((string) $_POST['userName']))) {

        if ($fromRecencyAPI === true) {
            $data['user_name'] = $_POST['userName'];
            $decryptedPassword = CommonService::decrypt($_POST['password'], base64_decode((string) SYSTEM_CONFIG['recency']['crossloginSalt']));
            $data['password'] = $decryptedPassword;
            $db->where('user_name', $data['user_name']);
        } else {
            $_SESSION['userLocale'] = $_POST['userLocale'] ?? 'en_US';
            $systemService->setLocale($_SESSION['userLocale']);
            $data = [
                'user_name' => $_POST['userName'],
                'email' => $_POST['email'],
                'user_locale' => $_SESSION['userLocale'],
                'phone_number' => $_POST['phoneNo'],
            ];

            if (isset($_POST['password']) && trim((string) $_POST['password']) != "") {
                $userRow = $db->rawQueryOne("SELECT `password` FROM user_details as ud WHERE ud.user_id = ?", [$userId]);
                if ($usersService->passwordVerify((string) $_POST['userName'], (string) $_POST['password'], (string) $userRow['password'])) {
                    $_SESSION['alertMsg'] = _translate("Your new password cannot be same as the current password. Please try another password.");
                    header("Location:edit-profile.php");
                }

                if (SYSTEM_CONFIG['recency']['crosslogin']) {
                    $_SESSION['crossLoginPass']  = $newCrossLoginPassword = CommonService::encrypt($_POST['password'], base64_decode((string) SYSTEM_CONFIG['recency']['crossloginSalt']));
                    $client = new Client();
                    $url = rtrim((string) SYSTEM_CONFIG['recency']['url'], "/");
                    $result = $client->post("$url/api/update-password", [
                        'form_params' => [
                            'u' => $_SESSION['loginId'],
                            't' => $newCrossLoginPassword
                        ]
                    ]);
                    $response = json_decode($result->getBody()->getContents());

                    if ($response->status == 'fail') {
                        LoggerUtility::log('error', 'Recency profile not updated! for the user->' . $_POST['userName']);
                    }
                }

                $newPassword = $usersService->passwordHash($_POST['password']);
                $data['password'] = $newPassword;
                $data['force_password_reset'] = 0;
                unset($_SESSION['forcePasswordReset']);
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
        } else {
            $_SESSION['alertMsg'] = _translate("Your profile changes have been saved. You can continue using the application.");
            header("Location:edit-profile.php");
        }
    }
} catch (Exception $exc) {
    throw new SystemException($exc->getMessage(), 500);
}

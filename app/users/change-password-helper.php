<?php

use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Client;
use App\Services\UsersService;
use App\Utilities\DateUtility;
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
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$wasUpdated = 0;

/* Used to check if the password update is from the Recency Web App API */
$fromRecencyAPI = false;

if (SYSTEM_CONFIG['recency']['crosslogin'] && !empty($_POST['u']) && !empty($_POST['t'])) {
    $fromRecencyAPI = true;
}

if ($fromRecencyAPI) {
    // Raw: 't' is ciphertext and the password it carries must survive byte for
    // byte - the sanitized copy has been through HTML Purifier. See _rawInput().
    $_POST['userName'] = _rawInput('u');
    $_POST['password'] = _rawInput('t');
    $userId = null;
} else {
    // A user may only edit their OWN profile. This endpoint is intentionally
    // open to every authenticated user (it is in the public allow-list so
    // anyone can change their own password), so we cannot gate it on a
    // privilege. Instead, ignore any client-supplied userId and bind the
    // update to the session user -- otherwise a logged-in user could POST
    // another account's id and reset that account's name/email/password.
    $userId = $_SESSION['userId'] ?? null;
    if (empty($userId)) {
        throw new SystemException(_translate('Your session has expired. Please log in again.'), 401);
    }
}

try {

    $wasUpdated = false;
    if (!in_array(trim((string) $_POST['confirmPassword']), ['', '0'], true)) {

        if ($fromRecencyAPI) {
            $decryptedPassword = CommonService::decrypt($_POST['confirmPassword'], base64_decode((string) SYSTEM_CONFIG['recency']['crossloginSalt']));
            $data['password'] = $decryptedPassword;
            $db->where('user_id', $userId);
            $data['updated_datetime'] = DateUtility::getCurrentDateTime();
            $wasUpdated = $db->update('user_details', $data);
        } else {

            // Raw, not sanitized: see _rawInput(). Hashing the purified copy
            // would store a password the user cannot log in with.
            $submittedPassword = _rawInput('confirmPassword');
            if (isset($submittedPassword) && trim((string) $submittedPassword) !== "") {
                $userRow = $db->rawQueryOne("SELECT `password`, `user_name` FROM user_details as ud WHERE ud.user_id = ?", [$userId]);
                if ($usersService->passwordVerify((string) $_SESSION['loginId'], (string) $submittedPassword, (string) $userRow['password'])) {
                    $_SESSION['alertMsg'] = _translate("Your new password cannot be same as the current password. Please try another password.");
                    header("Location:edit-profile.php");
                    exit;
                }

                if (SYSTEM_CONFIG['recency']['crosslogin']) {
                    $_SESSION['crossLoginPass'] = $newCrossLoginPassword = CommonService::encrypt($submittedPassword, base64_decode((string) SYSTEM_CONFIG['recency']['crossloginSalt']));
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
                        LoggerUtility::logError('Recency profile not updated! for the user->' . $userRow['user_name']);
                    }
                }

                $newPassword = $usersService->passwordHash($submittedPassword);
                $data['password'] = $newPassword;
                $data['force_password_reset'] = 0;
                unset($_SESSION['forcePasswordReset']);
            }
            $db->where('user_id', $userId);
            $data['updated_datetime'] = DateUtility::getCurrentDateTime();
            $wasUpdated = $db->update('user_details', $data);
        }


        if ($fromRecencyAPI) {
            $response = [];
            if ($wasUpdated !== false) {
                $response['status'] = "success";
                $response['message'] = "New Password updated successfully!";
                $general->activityLog('password-update', $userRow['user_name'] . ' profile updated via Recency API', 'user-profile-recency-api');
            } else {
                $response['status'] = "fail";
                $response['message'] = "Password not updated!";
            }
        } else {
            if ($wasUpdated !== false) {
                $_SESSION['alertMsg'] = _translate("Your password has been updated. You can continue using the application.");
                $general->activityLog('profile-update', $userRow['user_name'] . ' password updated', 'change-password');
                $_SESSION['userName'] = $userRow['user_name'];
            } else {
                $_SESSION['alertMsg'] = _translate("No changes were made to your profile.");
            }
            header("Location:change-password.php");
        }
    }
} catch (Exception $exc) {
    throw new SystemException($exc->getMessage(), 500);
}

<?php

use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Client;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
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

// A user may only edit their OWN profile. Every signed-in user may use this
// endpoint (anyone can change their own password), so we cannot gate it on a
// privilege. Instead, ignore any client-supplied userId and bind the update to
// the session user -- otherwise a logged-in user could POST another account's
// id and reset that account's name/email/password.
$userId = $_SESSION['userId'] ?? null;
if (empty($userId)) {
    throw new SystemException(_translate('Your session has expired. Please log in again.'), 401);
}

try {

    $wasUpdated = false;
    if (!in_array(trim((string) $_POST['userName']), ['', '0'], true)) {

        $_SESSION['userLocale'] = $_POST['userLocale'] ?? 'en_US';
        $systemService->setLocale($_SESSION['userLocale']);

        $data = [
            'user_name' => $_POST['userName'],
            'email' => $_POST['email'],
            'user_locale' => $_SESSION['userLocale'],
            'phone_number' => $_POST['phoneNo'],
        ];

        // Raw, not sanitized: see _rawInput(). Hashing the purified copy
        // would store a password the user cannot log in with.
        $submittedPassword = _rawInput('password');
        if (isset($submittedPassword) && trim((string) $submittedPassword) !== "") {
            $userRow = $db->rawQueryOne("SELECT `password` FROM user_details as ud WHERE ud.user_id = ?", [$userId]);
            $refusal = $usersService->passwordChangeRefusal(
                (string) $_SESSION['loginId'],
                (string) $userRow['password'],
                (string) (_rawInput('currentPassword') ?? ''),
                (string) $submittedPassword,
                (string) (_rawInput('confirmPassword') ?? '')
            );
            if ($refusal !== null) {
                // Nothing is saved, the profile fields included, so the user
                // fixes the password and submits the whole form again
                $_SESSION['alertMsg'] = $refusal;
                MiscUtility::redirect("edit-profile.php");
            }

            if (SYSTEM_CONFIG['recency']['crosslogin']) {
                $salt = base64_decode((string) SYSTEM_CONFIG['recency']['crossloginSalt']);
                $newCrossLoginPassword = CommonService::encrypt($submittedPassword, $salt);
                $_SESSION['crossLoginPass'] = $newCrossLoginPassword;
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
                    LoggerUtility::logError('Recency profile not updated! for the user->' . $_POST['userName']);
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

        if ($wasUpdated !== false) {
            $_SESSION['alertMsg'] = _translate(
                "Your profile changes have been saved. You can continue using the application."
            );
            $general->activityLog('profile-update', $_POST['userName'] . ' profile updated', 'user-profile');
            $_SESSION['userName'] = $_POST['userName'];
            $_SESSION['email'] = $_POST['email'];
        } else {
            $_SESSION['alertMsg'] = _translate("No changes were made to your profile.");
        }
        MiscUtility::redirect("edit-profile.php");
    }
} catch (Exception $exc) {
    throw new SystemException($exc->getMessage(), 500, $exc);
}

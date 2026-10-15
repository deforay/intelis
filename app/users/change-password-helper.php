<?php

use GuzzleHttp\Client;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

// Bound to the session user. Any user id in the request is ignored, so a
// signed-in user can change only their own password.
$userId = $_SESSION['userId'] ?? null;
if (empty($userId)) {
    throw new SystemException(_translate('Your session has expired. Please log in again.'), 401);
}

$userRow = $db->rawQueryOne("SELECT `password`, `user_name` FROM user_details WHERE user_id = ?", [$userId]);
if (empty($userRow)) {
    throw new SystemException(_translate('Your session has expired. Please log in again.'), 401);
}

// Raw, not sanitized: see _rawInput(). Checking or hashing the purified copy
// would compare or store a password other than the one the user typed.
$currentPassword = (string) (_rawInput('currentPassword') ?? '');
$newPassword = (string) (_rawInput('newPassword') ?? '');

$refusal = $usersService->passwordChangeRefusal(
    (string) $_SESSION['loginId'],
    (string) $userRow['password'],
    $currentPassword,
    $newPassword,
    (string) (_rawInput('confirmPassword') ?? '')
);
if ($refusal !== null) {
    // Back to the form; nothing has been written
    $_SESSION['alertMsg'] = $refusal;
    MiscUtility::redirect("change-password.php");
}

try {
    if (SYSTEM_CONFIG['recency']['crosslogin']) {
        $salt = base64_decode((string) SYSTEM_CONFIG['recency']['crossloginSalt']);
        $_SESSION['crossLoginPass'] = $newCrossLoginPassword = CommonService::encrypt($newPassword, $salt);
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

    $db->where('user_id', $userId);
    $wasUpdated = $db->update('user_details', [
        'password' => $usersService->passwordHash($newPassword),
        'force_password_reset' => 0,
        'updated_datetime' => DateUtility::getCurrentDateTime(),
    ]);

    if ($wasUpdated !== false) {
        unset($_SESSION['forcePasswordReset']);
        $_SESSION['alertMsg'] = _translate("Your password has been updated. You can continue using the application.");
        $general->activityLog('password-update', $userRow['user_name'] . ' password updated', 'change-password');
    } else {
        $_SESSION['alertMsg'] = _translate("Password not updated!");
    }
} catch (Exception $exc) {
    throw new SystemException($exc->getMessage(), 500, $exc);
}

MiscUtility::redirect("change-password.php");

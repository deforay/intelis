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

// Back to the form with a message. Nothing has been written at this point.
$refuse = function (string $message): void {
    $_SESSION['alertMsg'] = $message;
    MiscUtility::redirect("change-password.php");
};

// Raw, not sanitized: see _rawInput(). Checking or hashing the purified copy
// would compare or store a password other than the one the user typed.
$currentPassword = (string) (_rawInput('currentPassword') ?? '');
$newPassword = (string) (_rawInput('newPassword') ?? '');
$confirmPassword = (string) (_rawInput('confirmPassword') ?? '');

if ($currentPassword === '' || trim($newPassword) === '') {
    $refuse(_translate("Please enter your current password and a new password."));
}

$userRow = $db->rawQueryOne("SELECT `password`, `user_name` FROM user_details WHERE user_id = ?", [$userId]);
if (empty($userRow)) {
    throw new SystemException(_translate('Your session has expired. Please log in again.'), 401);
}

// The browser check is a convenience. This is the check that counts: without
// it, a direct POST or an unattended session could change the password.
$storedHash = (string) $userRow['password'];
if (!$usersService->passwordVerify((string) $_SESSION['loginId'], $currentPassword, $storedHash)) {
    $general->activityLog(
        'password-update',
        $userRow['user_name'] . ' entered a wrong current password',
        'change-password'
    );
    $refuse(_translate("Current password is incorrect."));
}

if ($newPassword !== $confirmPassword) {
    $refuse(_translate("New password and confirm password do not match."));
}

// Same rule as the form's own check
if (!preg_match('/^(?=.*[0-9])(?=.*[a-zA-Z])([a-zA-Z0-9!@#\$%\^\&*\)\(+=. _-]+){8,}$/', $newPassword)) {
    $refuse(_translate(
        "Password must be at least 8 characters long and must include AT LEAST one number, " .
            "one alphabet and may have special characters."
    ));
}

if ($newPassword === $currentPassword) {
    $refuse(_translate("Your new password cannot be same as the current password. Please try another password."));
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
    throw new SystemException($exc->getMessage(), 500);
}

MiscUtility::redirect("change-password.php");

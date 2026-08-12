<?php

use Psr\Http\Message\ServerRequestInterface;
use const CORE\SYSADMIN_SECRET_KEY_FILE;
use App\Services\UsersService;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

try {
    $secretKey = file_get_contents(SYSADMIN_SECRET_KEY_FILE);

    // Secret key and password are read raw: the sanitized copy has been through
    // HTML Purifier, which would break the key comparison and hash a password
    // that the login form (which reads $_POST directly) can never match.
    // See _rawInput().
    $submittedKey = (string) _rawInput('secretKey');
    $submittedPassword = _rawInput('password');

    if ($submittedKey == trim($secretKey)) {
        if (!empty($submittedPassword)) {
            $insertData = [
                'system_admin_email' => $_POST['email'] ?? null,
                'system_admin_login' => $_POST['loginid'],
                'system_admin_password' => $usersService->passwordHash($submittedPassword)
            ];
            $db->insert("system_admin", $insertData);
            MiscUtility::deleteFile(SYSADMIN_SECRET_KEY_FILE);
            $_SESSION['_systemAdmin']['alertMsg'] = _translate("System Admin added successfully");
            header("Location:/system-admin/login/login.php");
        }
    } else {
        $_SESSION['_systemAdmin']['alertMsg'] = _translate("Invalid Secret Key, Please enter valid key");
        header("Location:/system-admin/setup/index.php");
    }
} catch (Exception $exc) {
    $_SESSION['_systemAdmin']['alertMsg'] = _translate("Failed to add System Admin. Please try again.");
    header("Location:/system-admin/setup/index.php");
    LoggerUtility::logError($exc->getMessage(), [
        'trace' => $exc->getTraceAsString(),
        'file' => $exc->getFile(),
        'line' => $exc->getLine()
    ]);
}

<?php

use App\Services\UsersService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;

$_POST = _sanitizeInput($_POST);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

try {
    $secretKey = file_get_contents(CORE\SYSADMIN_SECRET_KEY_FILE);

    if ($_POST['secretKey'] == trim($secretKey)) {
        if (!empty($_POST['password'])) {
            $insertData = [
                'system_admin_email'    => $_POST['email'] ?? null,
                'system_admin_login'    => $_POST['loginid'],
                'system_admin_password' => $usersService->passwordHash($_POST['password'])
            ];
            $db->insert("system_admin", $insertData);
            unlink(CORE\SYSADMIN_SECRET_KEY_FILE);
            $_SESSION['alertMsg'] = _translate("System Admin added successfully");
            header("Location:/system-admin/login/login.php");
        }
    } else {
        $_SESSION['alertMsg'] = _translate("Invalid Secret Key, Please enter valid key");
        header("Location:/system-admin/setup/index.php");
    }
} catch (Exception $exc) {
    $_SESSION['alertMsg'] = _translate("Failed to add System Admin. Please try again.");
    header("Location:/system-admin/setup/index.php");
    LoggerUtility::log('error', $exc->getMessage(), [
        'trace' => $exc->getTraceAsString(),
        'file'  => $exc->getFile(),
        'line'  => $exc->getLine()
    ]);
}

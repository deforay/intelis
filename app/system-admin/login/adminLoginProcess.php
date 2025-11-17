<?php

use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\UsersService;




$tableName = "system_admin";
$adminUsername = trim((string) $_POST['username']);
$adminPassword = trim((string) $_POST['password']);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


try {
    $adminCount = $db->getValue("system_admin", "count(*)");
    if ($adminCount != 0) {
        if (isset($adminUsername)) {
            $params = [$adminUsername];
            $adminRow = $db->rawQueryOne("SELECT * FROM system_admin as ud WHERE ud.system_admin_login = ?", $params);

            if (!empty($adminRow) && $usersService->passwordVerify($adminUsername, $adminPassword, (string) $adminRow['system_admin_password'])) {
                $_SESSION['adminUserId'] = $adminRow['system_admin_id'];
                $_SESSION['adminUserName'] = ($adminRow['system_admin_name']);
                header("Location:/system-admin/edit-config/index.php");
            } else {
                throw new SystemException("Invalid username or password");
            }
        } else {
            throw new SystemException("Invalid username or password");
        }
    }
} catch (SystemException $exc) {
    error_log($exc->getMessage());

    $_SESSION['alertMsg'] = _translate("Please check your login credentials");
    header("Location:/system-admin/login/login.php");
}

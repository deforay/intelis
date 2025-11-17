<?php

use App\Registries\ContainerRegistry;
use App\Services\UsersService;




$tableName = "system_admin";

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

try {
    $userId = base64_decode((string) $_POST['userId']);
    if (isset($_POST['password']) && trim((string) $_POST['password']) !== "") {
        $data['system_admin_password'] = $usersService->passwordHash($_POST['password']);
        $db->where('system_admin_id', $userId);
        $db->update($tableName, $data);
        $_SESSION['alertMsg'] = _translate("Password updated successfully");
    }
    header("Location:/system-admin/edit-config/index.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

<?php

use App\Registries\ContainerRegistry;
use App\Services\UsersService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$tableName = "user_details";

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

try {
    $userId = base64_decode((string) $_POST['userId']);
    if (isset($_POST['password']) && trim((string) $_POST['password']) != "") {
        $data['password'] = $usersService->passwordHash($_POST['password']);
        $data['status'] = $_POST['status'];
        $db->where('user_id', $userId);
        $db->update($tableName, $data);
        $_SESSION['alertMsg'] = _translate("Password updated successfully");
    }
    header("Location:/system-admin/reset-password/reset-password.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

<?php

use App\Services\UsersService;
use App\Registries\ContainerRegistry;
use App\Utilities\DateUtility;

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

try {
    $userId = base64_decode((string) $_POST['userId']);
    if (isset($_POST['password']) && trim((string) $_POST['password']) !== "") {
        $data['password'] = $usersService->passwordHash($_POST['password']);
        $data['status'] = $_POST['status'];
        $data['updated_datetime'] = DateUtility::getCurrentDateTime();
        $db->where('user_id', $userId);
        $db->update('user_details', $data);
        $_SESSION['alertMsg'] = _translate("Password updated successfully");
    }
    header("Location:/system-admin/reset-password/reset-password.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

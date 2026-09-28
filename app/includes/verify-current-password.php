<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\UsersService;

/** @var UsersService $general */
$usersService = ContainerRegistry::get(UsersService::class);

$userId = (int) ($_SESSION['userId'] ?? 0);
$currentPassword = $_POST['currentPassword'] ?? '';

if ($userId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please login again.'
    ]);
    exit;
}

if ($currentPassword === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter your current password.'
    ]);
    exit;
}


$isValid = $usersService->validateCurrentPassword(
    $userId,
    $currentPassword
);

echo json_encode([
    'success' => $isValid,
    'message' => $isValid
        ? 'Current password is valid.'
        : 'Current password is incorrect.'
]);
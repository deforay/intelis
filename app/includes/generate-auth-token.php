<?php

use App\Registries\ContainerRegistry;
use App\Services\UsersService;

/** @var UsersService $general */
$usersService = ContainerRegistry::get(UsersService::class);

$size = $_POST['size'] ?? 6;

echo $usersService->generateAuthToken($size);

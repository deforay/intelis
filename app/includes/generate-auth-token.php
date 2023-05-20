<?php

use App\Registries\ContainerRegistry;
use App\Services\UsersService;

/** @var UsersService $general */
$usersService = ContainerRegistry::get(UsersService::class);

$size = htmlspecialchars($_POST['size']) ?? 6;

echo $usersService->generateAuthToken((int)$size);

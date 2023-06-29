<?php

use App\Registries\ContainerRegistry;
use App\Services\UsersService;

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$size = ($_POST['s']) ?? 8;

echo $usersService->generateAuthToken((int)$size);

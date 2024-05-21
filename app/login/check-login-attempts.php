<?php

use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$postParams = _sanitizeInput($request->getParsedBody());

$loginId = trim((string) $postParams['loginId']);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);



if (!empty($loginId) && $usersService->continuousFailedLogins($loginId) === true) {
	echo 1;
} else {
	echo 0;
}

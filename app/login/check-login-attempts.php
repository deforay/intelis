<?php

use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\SecurityService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');

try {

	SecurityService::checkContentLength($request);
	SecurityService::checkCSRF($request);

	$postParams = _sanitizeInput($request->getParsedBody());

	$loginId = trim((string) $postParams['loginId']);

	/** @var UsersService $usersService */
	$usersService = ContainerRegistry::get(UsersService::class);


	if (!empty($loginId) && $usersService->continuousFailedLogins($loginId) === true) {
		$response = ['captchaRequired' => true];
	} else {
		$response = ['captchaRequired' => false];
	}
} catch (Exception $e) {
	LoggerUtility::logError($e->getMessage(), [
		'line' => $e->getLine(),
		'file' => $e->getFile(),
		'trace' => $e->getTraceAsString()
	]);
	http_response_code(500); // set an error response code
	echo json_encode(['captchaRequired' => false, 'error' => 'An unexpected error occurred.']);
}

echo json_encode($response);

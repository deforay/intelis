<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$postParams = _sanitizeInput($request->getParsedBody());

$loginId = trim((string) $postParams['loginId']);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$ipAddress = $general->getClientIpAddress();
$data = 0;

if (!empty($loginId)) {
	// Query to check both failed and successful login attempts in the last 15 minutes
	$loginAttempts = $db->rawQueryOne(
		"SELECT
            SUM(CASE WHEN ulh.login_status = 'failed' THEN 1 ELSE 0 END) AS FailedAttempts,
            SUM(CASE WHEN ulh.login_status = 'success' THEN 1 ELSE 0 END) AS SuccessAttempts
			FROM user_login_history ulh
			WHERE ulh.login_id = ? AND
			ulh.login_attempted_datetime >= DATE_SUB(?, INTERVAL 15 MINUTE)",
		[$loginId, DateUtility::getCurrentDateTime()]
	);

	$failedAttempts = $loginAttempts['FailedAttempts'];
	$successAttempts = $loginAttempts['SuccessAttempts'];

	// Show CAPTCHA only if there are 3 or more failed attempts and no successful logins recently
	if ($failedAttempts >= 3 && $successAttempts == 0) {
		$data = 1;
	}
}

echo $data;

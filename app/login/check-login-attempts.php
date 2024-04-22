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
$ipdata = 0;
if (!empty($loginId)) {

	$loginAttemptCount = $db->rawQueryOne(
		"SELECT COUNT(*) AS FailedAttempts
			FROM user_login_history ulh
			WHERE ulh.login_id = ? AND
			ulh.login_status = 'failed' AND
			ulh.login_attempted_datetime >= DATE_SUB(?, INTERVAL 15 MINUTE)",
		[$loginId, DateUtility::getCurrentDateTime()]
	);

	$loginCount = $loginAttemptCount['FailedAttempts'];

	// If someone is failing to login with same login ID,
	// then we need to show the captcha
	if ($loginCount >= 3) {
		$data = 1;
	}
}

echo $data;

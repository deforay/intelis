<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

$loginId = (trim($_POST['loginId']));
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$ipAddress = $general->getIpAddress();
$data = 0;
$ipdata = 0;
if ($loginId != '') {
	$attemptCount = $db->rawQueryOne(
		"SELECT
			SUM(CASE WHEN login_id = ? THEN 1 ELSE 0 END) AS loginCount,
			SUM(CASE WHEN ip_address = ? THEN 1 ELSE 0 END) AS ipCount
			FROM user_login_history
			WHERE login_status='failed' AND login_attempted_datetime >= DATE_SUB(NOW(), INTERVAL 15 minute)",
		array($loginId, $ipAddress)
	);

	$ipCount = $attemptCount['ipCount'];
	$loginCount = $attemptCount['loginCount'];

	// If someone is failing to login with on same IP address
	// OR the same login ID, then we need to show the captcha
	if ($ipCount >= 3 || $loginCount >= 3) {
		$data = 1;
	}
}

echo $data;

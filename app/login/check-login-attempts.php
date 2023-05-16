<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;

$loginId = trim($_POST['loginId']);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$ipAddress = $general->getIpAddress();
$data = 0;
$ipdata = 0;
if (!empty($loginId)) {
	$loginQuery = "SELECT
					SUM(CASE WHEN login_id like ? THEN 1 ELSE 0 END) AS loginCount,
					SUM(CASE WHEN ip_address like ? THEN 1 ELSE 0 END) AS ipCount
					FROM user_login_history
					WHERE login_status like 'failed'
					AND login_attempted_datetime >= DATE_SUB(?, INTERVAL 15 minute)";
	$attemptCount = $db->rawQueryOne($loginQuery, [$loginId, $ipAddress, \App\Utilities\DateUtility::getCurrentDateTime()]);


	error_log($db->getLastQuery());

	$ipCount = $attemptCount['ipCount'];
	$loginCount = $attemptCount['loginCount'];

	// If someone is failing to login with on same IP address
	// OR the same login ID, then we need to show the captcha
	if ($ipCount >= 3 || $loginCount >= 3) {
		$data = 1;
	}
}

echo $data;

<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
$db = MysqliDb::getInstance();
$value = $db->escape(trim($_POST['value']));
$fnct = $_POST['fnct'];
$ipaddress = '';
if (isset($_SERVER['HTTP_CLIENT_IP'])) {
	$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
} else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
	$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
} else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
	$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
} else if (isset($_SERVER['HTTP_FORWARDED'])) {
	$ipaddress = $_SERVER['HTTP_FORWARDED'];
} else if (isset($_SERVER['REMOTE_ADDR'])) {
	$ipaddress = $_SERVER['REMOTE_ADDR'];
} else {
	$ipaddress = 'UNKNOWN';
}
$data = 0;
$ipdata = 0;
if ($value != '') {
	if ($fnct == '' || $fnct == 'null') {
		$attemptCount = $db->rawQueryOne(
			"SELECT 
											SUM(CASE WHEN login_id = ? THEN 1 ELSE 0 END) AS LoginIdCount,
											SUM(CASE WHEN ip_address = ? THEN 1 ELSE 0 END) AS IpCount
											FROM user_login_history
											WHERE login_status='failed' AND login_attempted_datetime > DATE_SUB(NOW(), INTERVAL 15 minute)",
			array($value, $ipaddress)
		);
		$ipdata = $attemptCount['IpCount'];
		$data = $attemptCount['LoginIdCount'];
	}
}

echo $data;

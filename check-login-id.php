<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$tableName = $_POST['tableName'];
$fieldName = $_POST['fieldName'];
$value = trim($_POST['value']);
$fnct = $_POST['fnct'];
$data = 0;
if ($value != '') {
    if ($fnct == '' || $fnct == 'null') {
      $attemptCount = $db->rawQueryOne("SELECT COUNT(*) as attempt FROM $tableName as ud WHERE ud.login_id = '".$value."' AND ud.login_status='failed' AND  ud.login_attempted_datetime > DATE_SUB(NOW(), INTERVAL 15 minute)");
      $data = $attemptCount['attempt'];
    } 
}
echo $data;
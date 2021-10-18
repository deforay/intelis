<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();



$general = new \Vlsm\Models\General($db);


//Add event log
$eventType = 'log-out';
$action = ucwords($_SESSION['userName']) . ' logged out';
$resource = 'user-log-out';
$data = array(
    'event_type' => $eventType,
    'action' => $action,
    'resource' => $resource,
    'date_time' => $general->getDateTime()
);
$db->insert("activity_log", $data);

// Unset all of the session variables.
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();
header("location:/login.php");

<?php
// Initialize the session.
// If you are using session_name("something"), don't forget it now!




// Unset all of the session variables.
$_SESSION = [];

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        ['expires' => time() - 42000, 'path' => $params["path"], 'domain' => $params["domain"], 'secure' => $params["secure"], 'httponly' => $params["httponly"]]
    );
}

// Finally, destroy the session.
session_destroy();
header("Location:/system-admin/login/login.php");

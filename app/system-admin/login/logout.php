<?php
// Initialize the session.
// If you are using session_name("something"), don't forget it now!




// Only clear the system-admin namespace, leave the rest of the session intact.
unset($_SESSION['_systemAdmin']);
header("Location:/system-admin/login/login.php");

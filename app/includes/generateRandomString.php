<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new \Vlsm\Models\General();
$randomString =$general->generateRandomString(16);

echo $randomString;

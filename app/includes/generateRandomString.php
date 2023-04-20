<?php

use Vlsm\Models\General;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new General();
$randomString =$general->generateRandomString(16);

echo $randomString;

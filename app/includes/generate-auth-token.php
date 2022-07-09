<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$general = new \Vlsm\Models\General();

echo $general->generateToken();
<?php

use App\Models\General;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$general = new General();

echo $general->generateToken();
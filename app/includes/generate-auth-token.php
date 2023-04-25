<?php

use App\Services\CommonService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$general = new CommonService();

echo $general->generateToken();
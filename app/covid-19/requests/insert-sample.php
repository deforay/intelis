<?php

use App\Models\Covid19;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$covid19Model = new Covid19();
echo $covid19Model->insertSampleCode($_POST);

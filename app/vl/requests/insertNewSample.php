<?php

use App\Models\Vl;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$vlModel = new Vl();
echo $vlModel->insertSampleCode($_POST);

<?php

use App\Models\Vl;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$vlModel = new Vl();
echo $vlModel->insertSampleCode($_POST);

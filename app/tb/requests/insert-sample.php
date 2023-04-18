<?php

use App\Models\Tb;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$tbModel = new Tb();
echo $tbModel->insertSampleCode($_POST);
<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$vlModel = new \App\Models\Vl();
echo $vlModel->insertSampleCode($_POST);

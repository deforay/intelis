<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$tbModel = new \App\Models\Tb();
echo $tbModel->insertSampleCode($_POST);
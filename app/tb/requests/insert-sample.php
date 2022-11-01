<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$tbModel = new \Vlsm\Models\Tb();
echo $tbModel->insertSampleCode($_POST);
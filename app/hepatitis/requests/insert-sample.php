<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$hepatitisModel = new \App\Models\Hepatitis();
echo $hepatitisModel->insertSampleCode($_POST);

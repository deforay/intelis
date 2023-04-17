<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$covid19Model = new \App\Models\Covid19();
echo $covid19Model->insertSampleCode($_POST);

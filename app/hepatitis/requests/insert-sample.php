<?php

use App\Models\Hepatitis;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$hepatitisModel = new Hepatitis();
echo $hepatitisModel->insertSampleCode($_POST);

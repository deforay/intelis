<?php

use App\Models\Tb;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$tbModel = new Tb();
echo $tbModel->insertSampleCode($_POST);
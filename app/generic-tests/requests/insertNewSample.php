<?php

use App\Services\VlService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$vlModel = new VlService();
echo $vlModel->insertSampleCodeGenericTest($_POST);

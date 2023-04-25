<?php

use App\Services\HepatitisService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$hepatitisModel = new HepatitisService();
echo $hepatitisModel->insertSampleCode($_POST);

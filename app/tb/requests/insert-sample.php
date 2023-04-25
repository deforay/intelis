<?php

use App\Services\TbService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$tbModel = new TbService();
echo $tbModel->insertSampleCode($_POST);
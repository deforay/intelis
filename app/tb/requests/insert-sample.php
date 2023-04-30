<?php

use App\Services\TbService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$tbService = new TbService();
echo $tbService->insertSampleCode($_POST);
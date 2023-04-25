<?php

use App\Services\EidService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$eidModel = new EidService();
echo $eidModel->insertSampleCode($_POST);

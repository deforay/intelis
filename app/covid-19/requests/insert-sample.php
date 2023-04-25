<?php

use App\Services\Covid19Service;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$covid19Model = new Covid19Service();
echo $covid19Model->insertSampleCode($_POST);

<?php

use App\Models\Eid;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$eidModel = new Eid();
echo $eidModel->insertSampleCode($_POST);

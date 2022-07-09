<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$eidModel = new \Vlsm\Models\Eid();
echo $eidModel->insertSampleCode($_POST);

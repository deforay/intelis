<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$general = new \Vlsm\Models\General();
$patientsModel = new \Vlsm\Models\Patients();

$prefix  = $_POST['patientCodePrefix'];

echo $patientsModel->generatePatientId($prefix);

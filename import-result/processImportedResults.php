<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#include_once '../startup.php';


$general = new \Vlsm\Models\General();

$module = $_POST['module'];

if ($module == 'vl') {
    include_once('process-vl.php');
} else if ($module == 'eid') {
    include_once('process-eid.php');
} else if ($module == 'covid19') {
    include_once('process-covid-19.php');
} else if ($module == 'hepatitis') {
    include_once('process-hepatitis.php');
}

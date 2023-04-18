<?php

use App\Models\General;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



$general = new General();

$module = $_POST['module'];

if ($module == 'vl') {
    require_once(APPLICATION_PATH . '/import-result/process-vl.php');
} else if ($module == 'eid') {
    require_once(APPLICATION_PATH . '/import-result/process-eid.php');
} else if ($module == 'covid19') {
    require_once(APPLICATION_PATH . '/import-result/process-covid-19.php');
} else if ($module == 'hepatitis') {
    require_once(APPLICATION_PATH . '/import-result/process-hepatitis.php');
}

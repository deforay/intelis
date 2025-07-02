<?php

$module = $_POST['module'];

if ($module == 'vl') {
    require_once(APPLICATION_PATH . '/import-result/process-vl.php');
} elseif ($module == 'eid') {
    require_once(APPLICATION_PATH . '/import-result/process-eid.php');
} elseif ($module == 'covid19') {
    require_once(APPLICATION_PATH . '/import-result/process-covid-19.php');
} elseif ($module == 'hepatitis') {
    require_once(APPLICATION_PATH . '/import-result/process-hepatitis.php');
}

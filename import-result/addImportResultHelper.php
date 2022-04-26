<?php

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../startup.php');  

$confFileName = base64_decode($_POST['machineName']);

$general = new \Vlsm\Models\General();
$arr = $general->getGlobalConfig();
/* echo "<pre>";
print_r($confFileName);
die; */

$type = $_POST['type'];

//var_dump($confFileName);die;

if ($type == 'vl') {
    require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "vl" . DIRECTORY_SEPARATOR . $confFileName);
} else if ($type == 'eid') {
    require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "eid" . DIRECTORY_SEPARATOR . $confFileName);
} else if ($type == 'covid19') {
    require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "covid-19" . DIRECTORY_SEPARATOR . $confFileName);
} else if ($type == 'hepatitis') {
    require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "hepatitis" . DIRECTORY_SEPARATOR . $confFileName);
} else if ($type == 'tb') {
    require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "tb" . DIRECTORY_SEPARATOR . $confFileName);
}

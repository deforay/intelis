<?php

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../startup.php');  

$confFileName = base64_decode($_POST['machineName']);
$globalConfigQuery = "SELECT * FROM global_config";
$configResult = $db->query($globalConfigQuery);
$arr = array();
for ($i = 0; $i < sizeof($configResult); $i++) {
    $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}

$general = new \Vlsm\Models\General();




$type = $_POST['type'];

if ($type == 'vl') {
    include_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "vl" . DIRECTORY_SEPARATOR . $confFileName);
} else if ($type == 'eid') {
    include_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "eid" . DIRECTORY_SEPARATOR . $confFileName);
} else if ($type == 'covid19') {
    include_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "covid-19" . DIRECTORY_SEPARATOR . $confFileName);
} else if ($type == 'hepatitis') {
    include_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "hepatitis" . DIRECTORY_SEPARATOR . $confFileName);
} else if ($type == 'tb') {
    include_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "tb" . DIRECTORY_SEPARATOR . $confFileName);
}

<?php

use App\Services\CommonService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$machineImportScript = ($_POST['fileName']);

$general = new CommonService();
$arr = $general->getGlobalConfig();
/* echo "<pre>";
print_r($machineImportScript);
die; */

$type = $_POST['type'];

//var_dump($machineImportScript);die;

if ($type == 'vl') {
    $machineImportScript = (APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "vl" . DIRECTORY_SEPARATOR . $machineImportScript);
} elseif ($type == 'eid') {
    $machineImportScript = (APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "eid" . DIRECTORY_SEPARATOR . $machineImportScript);
} elseif ($type == 'covid19') {
    $machineImportScript = (APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "covid-19" . DIRECTORY_SEPARATOR . $machineImportScript);
} elseif ($type == 'hepatitis') {
    $machineImportScript = (APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "hepatitis" . DIRECTORY_SEPARATOR . $machineImportScript);
} elseif ($type == 'tb') {
    $machineImportScript = (APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "tb" . DIRECTORY_SEPARATOR . $machineImportScript);
}

if (file_exists($machineImportScript)) {
    require_once($machineImportScript);
} else {
    echo "Import Script not found";
    exit();
}

<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

$machineImportScript = ($_POST['fileName']);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();

$type = $_POST['type'];

//var_dump($machineImportScript);die;

if ($type == 'vl') {
    $machineImportScript = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "vl" . DIRECTORY_SEPARATOR . $machineImportScript);
} elseif ($type == 'eid') {
    $machineImportScript = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "eid" . DIRECTORY_SEPARATOR . $machineImportScript);
} elseif ($type == 'covid19') {
    $machineImportScript = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "covid-19" . DIRECTORY_SEPARATOR . $machineImportScript);
} elseif ($type == 'hepatitis') {
    $machineImportScript = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "hepatitis" . DIRECTORY_SEPARATOR . $machineImportScript);
} elseif ($type == 'tb') {
    $machineImportScript = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . "import-configs" . DIRECTORY_SEPARATOR . "tb" . DIRECTORY_SEPARATOR . $machineImportScript);
}

if (!empty($machineImportScript) && file_exists($machineImportScript)) {
    require_once($machineImportScript);
} else {
    echo "Import Script not found";
    exit();
}

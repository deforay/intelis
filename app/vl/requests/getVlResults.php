<?php

use App\Registries\ContainerRegistry;
use App\Services\VlService;





/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);


// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);


if (isset($_POST['instrumentId'])) {
  $configId = $_POST['instrumentId'];
}

$vlResults = $vlService->getVlResults($configId);
$option = "";
foreach ($vlResults as $res) {
  $option .= "<option value='" . $res['result'] . "'>" . $res['result'] . "</option>";
}
echo $option;

<?php

use App\Registries\ContainerRegistry;
use App\Services\VlService;





/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);


if (isset($_POST['instrumentId'])) {
  $configId = $_POST['instrumentId'];
}

$vlResults = $vlService->getVlResults($configId);
$option = "";
foreach($vlResults as $res)
{
    $option .= "<option value='".$res['result']."'>".$res['result']."</option>";
}
echo $option;
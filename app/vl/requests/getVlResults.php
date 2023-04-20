<?php

use App\Models\Vl;




$vLModel = new Vl();


if (isset($_POST['instrumentId'])) {
  $configId = $_POST['instrumentId'];
}

$vlResults = $vLModel->getVlResults($configId);
$option = "";
foreach($vlResults as $res)
{
    $option .= "<option value='".$res['result']."'>".$res['result']."</option>";
}
echo $option;
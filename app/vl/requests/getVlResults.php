<?php
ob_start();


$vLModel = new \Vlsm\Models\Vl();


if (isset($_POST['instrumentId'])) {
  $configId = $_POST['instrumentId'];
}

$vlResults = $vLModel->getVlResults();
$option = "";
foreach($vlResults as $res)
{
    $insArr = json_decode($res['available_for_instruments']);
    if(in_array($configId,$insArr))
    {
        $option .= "<option value='".$res['result']."'>".$res['result']."</option>";
    }
}
echo $option;
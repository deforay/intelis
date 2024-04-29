<?php
use App\Registries\ContainerRegistry;
use App\Services\StorageService;

/** @var StorageService $general */
$storageService = ContainerRegistry::get(StorageService::class);

if(isset($_POST['labId']))
{
    $labId = $_POST['labId'];
   
    $freezerList = $storageService->getFreezerListByLabId($labId);
    $option="";
    foreach($freezerList as $list)
    {
        $option.="<option value='".$list['storage_id']."'>".$list['storage_code']."</option>";
    }
    echo $option;
}
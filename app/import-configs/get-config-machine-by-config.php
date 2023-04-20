<?php

use App\Models\General;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



$general = new General();
$tableName = "instruments";
$importMachineTable = "instrument_machines";

$configId = $_POST['configName'];
$testType = $_POST['testType'];
$machineId = $_POST['machine'];

$configQuery = "SELECT config_id FROM $tableName WHERE machine_name LIKE '".$configId."'";
$configInfo = $db->rawQueryOne($configQuery);

$options = '<option value="">--Select--</option>';
if(isset($configInfo) && $configInfo != ""){
    $configMachineQuery = "SELECT * FROM $importMachineTable WHERE config_id LIKE ".$configInfo['config_id'];
    $configMachineInfo = $db->rawQuery($configMachineQuery);
    foreach($configMachineInfo as $machine){
        $selected = (isset($machineId) && $machine['config_machine_id'] == $machineId)?"selected='selected'":"";
        $options .= '<option value="'.$machine['config_machine_id'].'" '.$selected.'>'.($machine['config_machine_name']).'</option>';
    }
}
echo $options;
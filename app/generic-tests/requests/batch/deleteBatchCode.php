<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName1 = "batch_details";
$tableName2 = "form_generic";
$table2PrimaryColumn = "sample_id";

$batchId = base64_decode($_POST['id']);

$vlQuery = "SELECT $table2PrimaryColumn from $tableName2 as vl where sample_batch_id=$batchId";
$vlInfo = $db->query($vlQuery);
if (count($vlInfo) > 0) {

    $value = array('sample_batch_id' => null);
    $db = $db->where('sample_batch_id', $batchId);

    $db->update($tableName2, $value);
}

$db = $db->where('batch_id', $batchId);
$delId = $db->delete($tableName1);
if ($delId > 0) {
    echo '1';
} else {
    echo '0';
}

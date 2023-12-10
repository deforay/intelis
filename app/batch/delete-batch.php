<?php


use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();
/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName1 = "batch_details";

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();


if (isset($_POST['type']) && $_POST['type'] == 'vl') {
    $tableName2 = "form_vl";
    $table2PrimaryColumn = "vl_sample_id";
} else if (isset($_POST['type']) && $_POST['type'] == 'eid') {
    $tableName2 = "form_eid";
    $table2PrimaryColumn = "eid_id";
} else if (isset($_POST['type']) && $_POST['type'] == 'covid19') {
    $tableName2 = "form_covid19";
    $table2PrimaryColumn = "covid19_id";
} else if (isset($_POST['type']) && $_POST['type'] == 'hepatitis') {
    $tableName2 = "form_hepatitis";
    $table2PrimaryColumn = "hepatitis_id";
} else if (isset($_POST['type']) && $_POST['type'] == 'tb') {
    $tableName2 = "form_tb";
    $table2PrimaryColumn = "tb_id";
} else if (isset($_POST['type']) && $_POST['type'] == 'generic-tests') {
    $tableName2 = "form_generic";
    $table2PrimaryColumn = "sample_id";
}


$batchId = base64_decode((string) $_POST['id']);

$vlQuery = "SELECT $table2PrimaryColumn from $tableName2 as vl where sample_batch_id=$batchId";
$vlInfo = $db->query($vlQuery);
if (count($vlInfo) > 0) {

    $value = array('sample_batch_id' => null);
    $db->where('sample_batch_id', $batchId);

    $db->update($tableName2, $value);
}

$db->where('batch_id', $batchId);
$delId = $db->delete($tableName1);
if ($delId > 0) {
    echo '1';
} else {
    echo '0';
}

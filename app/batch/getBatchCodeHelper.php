<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (empty($_POST['type'])) {
    echo "";
    exit;
} else {
    $testType = $_POST['type'];
}
$where = "";
if (isset($_POST['search']) && $_POST['search'] != "") {
    $where = " AND batch_code like '%" . $_POST['search'] . "%'";
}
$query = "SELECT batch_code FROM batch_details WHERE test_type='$testType' $where";

$result = $db->rawQuery($query);

$options = [];
foreach ($result as $batch) {
    $options[] = $batch['batch_code'];
}

echo json_encode($options);

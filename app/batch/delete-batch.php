<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

if (!isset($_POST['type']) || in_array(trim((string) $_POST['type']), ['', '0'], true)) {
    echo '0';
} else {

    $testTable = TestsService::getTestTableName($_POST['type']);
    $testTablePrimaryKey = TestsService::getPrimaryColumn($_POST['type']);

    $batchId = base64_decode((string) $_POST['id']);

    $vlQuery = "SELECT $testTablePrimaryKey FROM $testTable WHERE sample_batch_id=$batchId";
    $vlInfo = $db->query($vlQuery);
    if (!empty($vlInfo)) {

        $value = ['sample_batch_id' => null];
        $db->where('sample_batch_id', $batchId);

        $db->update($testTable, $value);
    }

    $db->where('batch_id', $batchId);
    $delId = $db->delete("batch_details");

    echo ($delId > 0) ? '1' : '0';
}

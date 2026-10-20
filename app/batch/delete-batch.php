<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\TestsService;
use App\Services\TbPoolService;
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

    // Pools that are not fixed go with the batch. A batch whose pools are fixed is
    // not deleted: its cartridges may be in the analyzer, and a new batch could
    // otherwise be given the same code and the same pool codes.
    if ($_POST['type'] === 'tb') {
        $batch = $db->rawQueryOne(
            "SELECT batch_code, batch_attributes FROM batch_details WHERE batch_id = ?",
            [(int) $batchId]
        ) ?: [];
        $batchCode = (string) ($batch['batch_code'] ?? '');
        if ($batchCode !== '') {
            /** @var TbPoolService $tbPoolService */
            $tbPoolService = ContainerRegistry::get(TbPoolService::class);
            if ($tbPoolService->isBatchLocked($batch)) {
                echo '2';
                return;
            }
            $tbPoolService->clearPools($batchCode);
        }
    }

    $vlQuery = "SELECT $testTablePrimaryKey FROM $testTable WHERE sample_batch_id=" . (int) $batchId;
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

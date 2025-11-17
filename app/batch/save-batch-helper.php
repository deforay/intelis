<?php

use Laminas\Diactoros\ServerRequest;
use App\Services\BatchService;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\SecurityService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var BatchService $batchService */
$batchService = ContainerRegistry::get(BatchService::class);

$testTable = "form_vl";
$testTablePrimaryKey = "vl_sample_id";

if (isset($_POST['type'])) {
    $testTable = TestsService::getTestTableName($_POST['type']);
    $testTablePrimaryKey = TestsService::getPrimaryColumn($_POST['type']);
}

$instrumentId = $_POST['platform'] ?? ($_POST['machine'] ?? null);

if (!empty($_POST['batchedSamples'])) {
    $_POST['batchedSamples'] = MiscUtility::desqid($_POST['batchedSamples'], returnArray: true);
}

$tableName1 = "batch_details";
try {

    if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== "") {
        if (!empty($_POST['batchId'])) {
            $id = intval($_POST['batchId']);
            $data = [
                'batch_code' => $_POST['batchCode'],
                'position_type' => $_POST['positions'],
                'machine' => $_POST['machine'],
                'lab_assigned_batch_code' => $_POST['labAssignedBatchCode'],
                'last_modified_by' => $_SESSION['userId'],
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            ];
            $db->where('batch_id', $id);
            $db->update($tableName1, $data);
            if ($id > 0) {
                $db->where('sample_batch_id', $id);
                $db->update($testTable, ['sample_batch_id' => null]);


                $selectedSamples = $_POST['batchedSamples'] ?? [];
                //Merging disabled samples into existing samples
                if (!empty($_POST['unbatchedSamples'])) {
                    if (!empty($selectedSamples)) {
                        $selectedSamples = array_unique(array_merge($_POST['unbatchedSamples'], $selectedSamples));
                    } else {
                        $selectedSamples = $_POST['unbatchedSamples'];
                    }
                }

                $uniqueSampleIds = array_unique($selectedSamples);

                $db->where($testTablePrimaryKey, $uniqueSampleIds, "IN");
                $db->update($testTable, ['sample_batch_id' => $id]);
                MiscUtility::redirect("edit-batch-position.php?type=" . $_POST['type'] . "&id=" . base64_encode($id) . "&position=" . $_POST['positions']);
            }
        } elseif ($batchService->doesBatchCodeExist($_POST['batchCode'])) {
            $_SESSION['alertMsg'] = _translate("Something went wrong. Please try again later.", true);
            MiscUtility::redirect("batches.php?type=" . $_POST['type']);
        } else {
            $maxSampleBatchId = $general->getMaxSampleBatchId($testTable);
            $maxBatchId = $general->getMaxBatchId($tableName1);
            $data = [
                'machine' => $_POST['platform'],
                'lab_assigned_batch_code' => $_POST['labAssignedBatchCode'],
                'batch_code' => $_POST['batchCode'],
                'batch_code_key' => $_POST['batchCodeKey'],
                'position_type' => $_POST['positions'],
                'test_type' => $_POST['type'],
                'created_by' => $_SESSION['userId'],
                'last_modified_by' => $_SESSION['userId'],
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'request_created_datetime' => DateUtility::getCurrentDateTime()
            ];

            if ($maxBatchId < $maxSampleBatchId) {
                $data['batch_id'] = $maxSampleBatchId + 1;
            }
            $db->insert($tableName1, $data);

            $lastId = $maxBatchId < $maxSampleBatchId ? $maxSampleBatchId + 1 : $db->getInsertId();

            $selectedSamples = $_POST['batchedSamples'] ?? [];
            if ($lastId > 0 && !empty($selectedSamples)) {
                $uniqueSampleIds = array_unique($selectedSamples);
                $db->where($testTablePrimaryKey, $uniqueSampleIds, "IN");
                $db->update($testTable, ['sample_batch_id' => $lastId]);
                MiscUtility::redirect("add-batch-position.php?type=" . $_POST['type'] . "&id=" . base64_encode($lastId) . "&position=" . $_POST['positions']);
            } else {
                MiscUtility::redirect("batches.php?type=" . $_POST['type']);
            }
        }
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception' => $e,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stacktrace' => $e->getTraceAsString()
    ]);
    throw new SystemException($e->getMessage(), 500);
}

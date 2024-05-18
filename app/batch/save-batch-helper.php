<?php

use App\Services\BatchService;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
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

$table = TestsService::getTestTableName($_POST['type']);
$primaryKeyColumn = TestsService::getTestPrimaryKeyColumn($_POST['type']);

if (isset($_POST['type'])) {
    $testTable = TestsService::getTestTableName($_POST['type']);
    $testTablePrimaryKey = TestsService::getTestPrimaryKeyColumn($_POST['type']);
}



$tableName1 = "batch_details";
try {


    if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != "") {
        if (!empty($_POST['batchId'])) {
            $id = intval($_POST['batchId']);
            $data = [
                'batch_code' => $_POST['batchCode'],
                'position_type' => $_POST['positions'],
                'machine' => $_POST['machine'],
                'last_modified_by' => $_SESSION['userId'],
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            ];
            $db->where('batch_id', $id);
            $db->update($tableName1, $data);
            if ($id > 0) {
                $db->where('sample_batch_id', $id);
                $db->update($testTable, ['sample_batch_id' => null]);
                $xplodResultSample = [];
                if (isset($_POST['batchedSamples']) && trim((string) $_POST['batchedSamples']) != "") {
                    $xplodResultSample = explode(",", (string) $_POST['batchedSamples']);
                }
                $selectedSamples = [];
                //Mergeing disabled samples into existing samples
                if (!empty($_POST['unbatchedSamples'])) {
                    if (!empty($xplodResultSample)) {
                        $selectedSamples = array_unique(array_merge($_POST['unbatchedSamples'], $xplodResultSample));
                    } else {
                        $selectedSamples = $_POST['unbatchedSamples'];
                    }
                } elseif (!empty($xplodResultSample)) {
                    $selectedSamples = $xplodResultSample;
                }

                $uniqueSampleIds = array_unique($selectedSamples);
                $db->where($testTablePrimaryKey, $uniqueSampleIds, "IN");
                $db->update($testTable, ['sample_batch_id' => $id]);
                header("Location:edit-batch-position.php?type=" . $_POST['type'] . "&id=" . base64_encode($id) . "&position=" . $_POST['positions']);
            }
        } else {
            if ($batchService->doesBatchCodeExist($_POST['batchCode'])) {
                $_SESSION['alertMsg'] = _translate("Something went wrong. Please try again later.");
                header("Location:batches.php?type=" . $_POST['type']);
            } else {
                $data = [
                    'machine' => $_POST['platform'],
                    'batch_code' => $_POST['batchCode'],
                    'batch_code_key' => $_POST['batchCodeKey'],
                    'position_type' => $_POST['positions'],
                    'test_type' => $_POST['type'],
                    'created_by' => $_SESSION['userId'],
                    'last_modified_by' => $_SESSION['userId'],
                    'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                    'request_created_datetime' => DateUtility::getCurrentDateTime()
                ];
                $db->insert($tableName1, $data);
                $lastId = $db->getInsertId();
                if ($lastId > 0 && trim((string) $_POST['batchedSamples']) != '') {
                    $selectedSamples = explode(",", (string) $_POST['batchedSamples']);
                    $uniqueSampleIds = array_unique($selectedSamples);
                    $db->where($testTablePrimaryKey, $uniqueSampleIds, "IN");
                    $db->update($testTable, ['sample_batch_id' => $lastId]);
                    header("Location:add-batch-position.php?type=" . $_POST['type'] . "&id=" . base64_encode($lastId) . "&position=" . $_POST['positions']);
                } else {
                    header("Location:batches.php?type=" . $_POST['type']);
                }
            }
        }
    }
    // header("Location:batches.php?type=" . $_POST['type']);
} catch (Exception $exc) {
    throw new SystemException($exc->getMessage(), 500);
}

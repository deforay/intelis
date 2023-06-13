<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$refTable = "form_vl";
$refPrimaryColumn = "vl_sample_id";
if (isset($_POST['type']) && $_POST['type'] == 'vl') {
    $refTable = "form_vl";
    $refPrimaryColumn = "vl_sample_id";
} elseif (isset($_POST['type']) && $_POST['type'] == 'eid') {
    $refTable = "form_eid";
    $refPrimaryColumn = "eid_id";
} elseif (isset($_POST['type']) && $_POST['type'] == 'covid19') {
    $refTable = "form_covid19";
    $refPrimaryColumn = "covid19_id";
} elseif (isset($_POST['type']) && $_POST['type'] == 'hepatitis') {
    $refTable = "form_hepatitis";
    $refPrimaryColumn = "hepatitis_id";
} elseif (isset($_POST['type']) && $_POST['type'] == 'tb') {
    $refTable = "form_tb";
    $refPrimaryColumn = "tb_id";
} elseif (isset($_POST['type']) && $_POST['type'] == 'generic-tests') {
    $refTable = "form_generic";
    $refPrimaryColumn = "sample_id";
}
$tableName1 = "batch_details";
try {


    if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != "") {
        if (!empty($_POST['batchId'])) {
            $id = intval($_POST['batchId']);
            $data = array(
                'batch_code' => $_POST['batchCode'],
                'position_type' => $_POST['positions'],
                'machine' => $_POST['machine'],
                'last_modified_by' => $_SESSION['userId'],
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            );
            $db = $db->where('batch_id', $id);
            $db->update($tableName1, $data);
            if ($id > 0) {
                $value = array('sample_batch_id' => null);
                $db = $db->where('sample_batch_id', $id);
                $db->update($refTable, $value);
                $xplodResultSample = [];
                if (isset($_POST['selectedSample']) && trim($_POST['selectedSample']) != "") {
                    $xplodResultSample = explode(",", $_POST['selectedSample']);
                }
                $sample = [];
                //Mergeing disabled samples into existing samples
                if (!empty($_POST['sampleCode'])) {
                    if (count($xplodResultSample) > 0) {
                        $sample = array_unique(array_merge($_POST['sampleCode'], $xplodResultSample));
                    } else {
                        $sample = $_POST['sampleCode'];
                    }
                } elseif (count($xplodResultSample) > 0) {
                    $sample = $xplodResultSample;
                }
                
                for ($j = 0; $j < count($sample); $j++) {
                    $value = array('sample_batch_id' => $id);
                    $db = $db->where($refPrimaryColumn, $sample[$j]);
                    $db->update($refTable, $value);
                }
                header("Location:add-batch-position.php?type=" . $_POST['type'] . "&id=" . base64_encode($id) . "&position=" . $_POST['positions']);
            }else{
                // header("Location:batches.php?type=" . $_POST['type']); 
            }
        } else {
            $exist = $general->existBatchCode($_POST['batchCode']);
            if ($exist) {
                $_SESSION['alertMsg'] = "Something went wrong. Please try again later.";
                header("Location:batches.php?type=" . $_POST['type']);
            } else {
                $data = array(
                    'machine' => $_POST['platform'],
                    'batch_code' => $_POST['batchCode'],
                    'batch_code_key' => $_POST['batchCodeKey'],
                    'position_type' => $_POST['positions'],
                    'test_type' => $_POST['type'],
                    'created_by' => $_SESSION['userId'],
                    'request_created_datetime' => DateUtility::getCurrentDateTime()
                );

                $db->insert($tableName1, $data);
                $lastId = $db->getInsertId();
                if ($lastId > 0 && trim($_POST['selectedSample']) != '') {
                    $selectedSample = explode(",", $_POST['selectedSample']);
                    $uniqueSampleId = array_unique($selectedSample);
                    for ($j = 0; $j <= count($selectedSample); $j++) {
                        if (isset($uniqueSampleId[$j])) {

                            $vlSampleId = $uniqueSampleId[$j];
                            $value = array('sample_batch_id' => $lastId);
                            $db = $db->where($refPrimaryColumn, $vlSampleId);
                            $db->update($refTable, $value);
                        }
                    }
                    header("Location:add-batch-position.php?type=" . $_POST['type'] . "&id=" . base64_encode($lastId) . "&position=" . $_POST['positions']);
                }else{
                    header("Location:batches.php?type=" . $_POST['type']);            
                }
            }
        }
    }
    // header("Location:batches.php?type=" . $_POST['type']);
} catch (Exception $exc) {
    echo ($exc->getMessage());
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

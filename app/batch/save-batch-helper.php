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
        if (isset($_POST['batchId']) && !empty($_POST['batchId'])) {
            $id = intval($_POST['batchId']);
            $data = array(
                'batch_code' => $_POST['batchCode'],
                'position_type' => $_POST['positions'],
                'machine' => $_POST['machine']
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
                if (isset($_POST['sampleCode']) && !empty($_POST['sampleCode'])) {
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
                //Update batch controls position, If samples has changed
                $displaySampleOrderArray = [];
                $batchQuery = "SELECT * from batch_details as b_d INNER JOIN instruments as i_c ON i_c.config_id=b_d.machine where batch_id=".$id;
                $batchInfo = $db->query($batchQuery);
                if (isset($batchInfo) && !empty($batchInfo)) {
                    
                    if (isset($batchInfo[0]['position_type']) && $batchInfo[0]['position_type'] == 'alpha-numeric') {
                        foreach ($general->excelColumnRange('A', 'H') as $value) {
                            foreach (range(1, 12) as $no) {
                                $alphaNumeric[] = $value . $no;
                            }
                        }
                    }
                    if (isset($batchInfo[0]['label_order']) && trim($batchInfo[0]['label_order']) != '') {
                        //Get display sample only
                        $samplesQuery = "SELECT " . $refPrimaryColumn . ",sample_code from " . $refTable . " where sample_batch_id=".$id." ORDER BY sample_code ASC";
                        $samplesInfo = $db->query($samplesQuery);
                        foreach ($samplesInfo as $sample) {
                            $displaySampleOrderArray[] = $sample[$refPrimaryColumn];
                        }
                        //Set label order
                        $jsonToArray = json_decode($batchInfo[0]['label_order'], true);
                        $displaySampleArray = [];
                        if (isset($batchInfo[0]['position_type']) && $batchInfo[0]['position_type'] == 'alpha-numeric') {
                            $displayOrder = [];
                            for ($j = 0; $j < count($jsonToArray); $j++) {
                                $xplodJsonToArray = explode("_", $jsonToArray[$alphaNumeric[$j]]);
                                if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
                                    if (in_array($xplodJsonToArray[1], $displaySampleOrderArray)) {
                                        $displayOrder[] = $jsonToArray[$alphaNumeric[$j]];
                                        $displaySampleArray[] = $xplodJsonToArray[1];
                                    }
                                } else {
                                    $displayOrder[] = $jsonToArray[$alphaNumeric[$j]];
                                }
                            }
                            
                        } else {
                            $displayOrder = [];
                            for ($j = 0; $j < count($jsonToArray); $j++) {
                                $xplodJsonToArray = explode("_", $jsonToArray[$j]);
                                if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
                                    if (in_array($xplodJsonToArray[1], $displaySampleOrderArray)) {
                                        $displayOrder[] = $jsonToArray[$j];
                                        $displaySampleArray[] = $xplodJsonToArray[1];
                                    }
                                } else {
                                    $displayOrder[] = $jsonToArray[$j];
                                }
                            }
                        }
                        $remainSampleNewArray = array_values(array_diff($displaySampleOrderArray, $displaySampleArray));
                        //For new samples
                        // $displayOrder = [];
                        for ($ns = 0; $ns < count($remainSampleNewArray); $ns++) {
                            $displayOrder[] = 's_' . $remainSampleNewArray[$ns];
                        }
                        $orderArray = [];
                        if (isset($batchInfo[0]['position_type']) && $batchInfo[0]['position_type'] == 'alpha-numeric') {
                            for ($o = 0; $o < count($displayOrder); $o++) {
                                if (isset($displayOrder[$o]) && $displayOrder[$o] != "") {
                                    $orderArray[$alphaNumeric[$o]] = $displayOrder[$o];
                                }
                            }
                        } else {
                            for ($o = 0; $o < count($displayOrder); $o++) {
                                $orderArray[$o] = $displayOrder[$o];
                            }
                        }
                        // echo "<pre>";print_r($orderArray);die;
                        $labelOrder = json_encode($orderArray, JSON_FORCE_OBJECT);
                        //Update label order
                        $data = array('label_order' => $labelOrder);
                        $db = $db->where('batch_id', $id);
                        $db->update($tableName1, $data);
                    }
                }
                $_SESSION['alertMsg'] = "Batch updated successfully";
                header("Location:batches.php?type=" . $_POST['type']);
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
    } else {
        header("Location:batches.php?type=" . $_POST['type']);
    }
} catch (Exception $exc) {
    echo ($exc->getMessage());
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

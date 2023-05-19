<?php

// this file is included in /import-result/processImportedResults.php

use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

$fileName = null;
$importedBy = $_SESSION['userId'];

try {
    $numberOfResults = 0;

    $arr = $general->getGlobalConfig();
    $printSampleCode = [];

    $importNonMatching = !(isset($arr['import_non_matching_sample']) && $arr['import_non_matching_sample'] == 'no');
    $instanceQuery = "SELECT * FROM s_vlsm_instance";
    $instanceResult = $db->query($instanceQuery);
    $result = '';
    $id = explode(",", $_POST['value']);
    $status = explode(",", $_POST['status']);
    $rejectedReasonId = explode(",", $_POST['rejectReasonId']);
    if ($_POST['value'] != '') {
        for ($i = 0; $i < count($id); $i++) {
            $sQuery = "SELECT * FROM temp_sample_import WHERE imported_by =? AND temp_sample_id= ?";
            $rResult = $db->rawQuery($sQuery, [$importedBy, $id[$i]]);
            $fileName = $rResult[0]['import_machine_file_name'];

            if (isset($rResult[0]['lab_tech_comments']) && $rResult[0]['lab_tech_comments'] != "") {
                $comments = $rResult[0]['lab_tech_comments']; //
                if ($_POST['comments'] != "") {
                    $comments .= " - " . $_POST['comments'];
                }
            } else {
                $comments = $_POST['comments'];
            }

            if ($rResult[0]['sample_type'] != 'S' && $rResult[0]['sample_type'] != 's') {
                $data = array(
                    'control_code'                  => $rResult[0]['sample_code'],
                    'lab_id'                        => $rResult[0]['lab_id'],
                    'control_type'                  => $rResult[0]['sample_type'],
                    'lot_number'                    => $rResult[0]['lot_number'],
                    'lot_expiration_date'           => $rResult[0]['lot_expiration_date'],
                    'sample_tested_datetime'        => $rResult[0]['sample_tested_datetime'],
                    //'is_sample_rejected'=>'yes',
                    //'reason_for_sample_rejection'=>$rResult[0]['reason_for_sample_rejection'],
                    'result_value_log'              => $rResult[0]['result_value_log'],
                    'result_value_absolute'         => $rResult[0]['result_value_absolute'],
                    'result_value_text'             => $rResult[0]['result_value_text'],
                    'result_value_absolute_decimal' => $rResult[0]['result_value_absolute_decimal'],
                    'result'                        => $rResult[0]['result'],
                    'tested_by'                     => $_POST['testBy'],
                    'lab_tech_comments'             => $comments,
                    'result_reviewed_by'            => $rResult[0]['result_reviewed_by'],
                    'result_reviewed_datetime'      => DateUtility::getCurrentDateTime(),
                    'result_approved_by'            => $_POST['appBy'],
                    'result_approved_datetime'      => DateUtility::getCurrentDateTime(),
                    'vlsm_country_id'               => $arr['vl_form'],
                    'file_name'                     => $rResult[0]['import_machine_file_name'],
                    'imported_date_time'            => $rResult[0]['result_imported_datetime'],
                );
                if ($status[$i] == 4) {
                    $data['is_sample_rejected'] = 'yes';
                    $data['reason_for_sample_rejection'] = $rejectedReasonId[$i];
                    $data['result_value_log'] = null;
                    $data['result_value_absolute'] = null;
                    $data['result_value_text'] = null;
                    $data['result_value_absolute_decimal'] = null;
                    $data['result'] = null;
                } else {
                    $data['is_sample_rejected'] = 'no';
                }
                $data['status'] = $status[$i];

                $bquery = "SELECT * FROM batch_details WHERE batch_code= ?";
                $bvlResult = $db->rawQuery($bquery, [$rResult[0]['batch_code']]);
                if ($bvlResult) {
                    $data['batch_id'] = $bvlResult[0]['batch_id'];
                } else {
                    $batchResult = $db->insert('batch_details', [
                        'test_type' => 'vl',
                        'batch_code' => $rResult[0]['batch_code'],
                        'batch_code_key' => $rResult[0]['batch_code_key'],
                        'sent_mail' => 'no',
                        'request_created_datetime' => DateUtility::getCurrentDateTime()
                    ]);
                    $data['batch_id'] = $db->getInsertId();
                }

                $db->insert('vl_imported_controls', $data);
            } else {

                $data = array(
                    'result_reviewed_datetime' => $rResult[0]['result_reviewed_datetime'],
                    'result_reviewed_by' => $_POST['reviewedBy'],
                    'import_machine_name' => $rResult[0]['import_machine_name'],
                    'lab_tech_comments' => $comments,
                    'lot_number' => $rResult[0]['lot_number'],
                    'lot_expiration_date' => $rResult[0]['lot_expiration_date'],
                    'result_value_log' => $rResult[0]['result_value_log'],
                    'result_value_absolute' => $rResult[0]['result_value_absolute'],
                    'result_value_text' => $rResult[0]['result_value_text'],
                    'result_value_absolute_decimal' => $rResult[0]['result_value_absolute_decimal'],
                    'result' => $rResult[0]['result'],
                    'sample_tested_datetime' => $rResult[0]['sample_tested_datetime'] ?? DateUtility::getCurrentDateTime(),
                    'lab_id' => $rResult[0]['lab_id'],
                    'import_machine_file_name' => $rResult[0]['import_machine_file_name'],
                    'manual_result_entry' => 'no',
                );
                if ($status[$i] == '1') {
                    $data['result_reviewed_by'] = $_POST['reviewedBy'];
                    $data['facility_id'] = $rResult[0]['facility_id'];
                    $data['sample_code'] = $rResult[0]['sample_code'];
                    $data['batch_code'] = $rResult[0]['batch_code'];
                    $data['sample_type'] = $rResult[0]['sample_type'];
                    $data['vl_test_platform'] = $rResult[0]['vl_test_platform'];
                    $data['status'] = $status[$i];
                    $data['import_batch_tracking'] = $_SESSION['controllertrack'];
                    $result = $db->insert('hold_sample_import', $data);
                } else {
                    $data['vl_test_platform'] = $rResult[0]['vl_test_platform'];
                    $data['tested_by'] = $_POST['testBy'];
                    $data['sample_tested_datetime'] = $rResult[0]['sample_tested_datetime'];
                    $data['request_created_by'] = $rResult[0]['result_reviewed_by'];
                    $data['request_created_datetime'] = DateUtility::getCurrentDateTime();
                    $data['last_modified_by'] = $rResult[0]['result_reviewed_by'];
                    $data['last_modified_datetime'] = DateUtility::getCurrentDateTime();
                    $data['result_approved_by'] = $_POST['appBy'];
                    $data['result_approved_datetime'] = DateUtility::getCurrentDateTime();
                    $sampleVal = $rResult[0]['sample_code'];

                    if ($status[$i] == '4') {
                        $data['is_sample_rejected'] = 'yes';
                        $data['reason_for_sample_rejection'] = $rejectedReasonId[$i];
                        $data['result_value_log'] = null;
                        $data['result_value_absolute'] = null;
                        $data['result_value_text'] = null;
                        $data['result_value_absolute_decimal'] = null;
                        $data['result'] = null;
                    } else {

                        $data['is_sample_rejected'] = 'no';
                        $data['reason_for_sample_rejection'] = null;

                        if (!empty(trim($rResult[0]['result_value_text'])) && $rResult[0]['result_value_text'] != '') {
                            $data['result'] = $rResult[0]['result_value_text'];
                        } elseif ($rResult[0]['result_value_absolute'] != '') {
                            $data['result'] = $rResult[0]['result_value_absolute'];
                        } elseif ($rResult[0]['result_value_log'] != '') {
                            $data['result'] = $rResult[0]['result_value_log'];
                        }
                    }
                    //get bacth code
                    $bquery = "SELECT * FROM batch_details WHERE batch_code= ?";
                    $bvlResult = $db->rawQuery($bquery, [$rResult[0]['batch_code']]);
                    if ($bvlResult) {
                        $data['sample_batch_id'] = $bvlResult[0]['batch_id'];
                    } else {
                        $batchResult = $db->insert('batch_details', [
                            'batch_code' => $rResult[0]['batch_code'],
                            'batch_code_key' => $rResult[0]['batch_code_key'],
                            'sent_mail' => 'no',
                            'request_created_datetime' => DateUtility::getCurrentDateTime()
                        ]);
                        $data['sample_batch_id'] = $db->getInsertId();
                    }

                    $query = "SELECT vl_sample_id,result FROM form_vl WHERE sample_code= ?";
                    $vlResult = $db->rawQuery($query, [$sampleVal]);
                    $data['result_status'] = $status[$i];


                    $data['vl_result_category'] = $vlService->getVLResultCategory($data['result_status'], $data['result']);

                    if ($data['vl_result_category'] == 'failed' || $data['vl_result_category'] == 'invalid') {
                        $data['result_status'] = 5;
                    } elseif ($vldata['vl_result_category'] == 'rejected') {
                        $data['result_status'] = 4;
                    }

                    $data['sample_code'] = $rResult[0]['sample_code'];
                    if (!empty($vlResult)) {
                        $data['vlsm_country_id'] = $arr['vl_form'];
                        $data['data_sync'] = 0;

                        $db = $db->where('sample_code', $rResult[0]['sample_code']);
                        $result = $db->update('form_vl', $data);

                        $vlSampleId = $vlResult[0]['vl_sample_id'];
                    } else {
                        if (!$importNonMatching) continue;
                        $data['sample_code'] = $rResult[0]['sample_code'];
                        $data['vlsm_country_id'] = $arr['vl_form'];
                        $data['vlsm_instance_id'] = $instanceResult[0]['vlsm_instance_id'];
                        $vlSampleId = $db->insert('form_vl', $data);
                    }

                    $printSampleCode[] = "'" . $rResult[0]['sample_code'] . "'";
                }
            }
            if (isset($vlSampleId) && $vlSampleId != "") {
                $db->insert('log_result_updates', array(
                    "user_id" => $_SESSION['userId'],
                    "vl_sample_id" => $vlSampleId,
                    "test_type" => "vl",
                    "result_method" => "import",
                    "file_name" => $rResult[0]['import_machine_file_name'],
                    "updated_on" => DateUtility::getCurrentDateTime()
                ));
            }
            $db = $db->where('temp_sample_id', $id[$i]);
            $result = $db->update('temp_sample_import', array('temp_sample_status' => 1));
        }
        if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $rResult[0]['import_machine_file_name'])) {
            copy(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $rResult[0]['import_machine_file_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $rResult[0]['import_machine_file_name']);
        }
    }
    //get all accepted data result
    $accQuery = "SELECT * FROM temp_sample_import as tsr LEFT JOIN form_vl as vl ON vl.sample_code=tsr.sample_code where  imported_by ='$importedBy' AND tsr.result_status='7'";
    $accResult = $db->rawQuery($accQuery);
    if ($accResult) {
        for ($i = 0; $i < count($accResult); $i++) {
            $data = array(
                // 'lab_name' => $accResult[$i]['lab_name'],
                // 'lab_contact_person' => $accResult[$i]['lab_contact_person'],
                // 'lab_phone_number' => $accResult[$i]['lab_phone_number'],
                //'sample_received_at_vl_lab_datetime' => $accResult[$i]['sample_received_at_vl_lab_datetime'],
                //'result_reviewed_datetime' => $accResult[$i]['result_reviewed_datetime'],
                'result_reviewed_by' => $_POST['reviewedBy'],
                'lab_tech_comments' => $_POST['comments'],
                'lot_number' => $accResult[$i]['lot_number'],
                'lot_expiration_date' => $accResult[$i]['lot_expiration_date'],
                'result_value_log' => $accResult[$i]['result_value_log'],
                'result_value_absolute' => $accResult[$i]['result_value_absolute'],
                'result_value_text' => $accResult[$i]['result_value_text'],
                'result_value_absolute_decimal' => $accResult[$i]['result_value_absolute_decimal'],
                'result' => $accResult[$i]['result'],
                'sample_tested_datetime' => $accResult[$i]['sample_tested_datetime'],
                'lab_id' => $accResult[$i]['lab_id'],
                'tested_by'                     => $_POST['testBy'],
                'request_created_by' => $accResult[$i]['result_reviewed_by'],
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'result_approved_by' => $_POST['appBy'],
                'result_approved_datetime' => DateUtility::getCurrentDateTime(),
                'import_machine_file_name' => $accResult[$i]['import_machine_file_name'],
                'manual_result_entry' => 'no',
                'result_printed_datetime' => null,
                'result_dispatched_datetime' => null,
                'vl_test_platform' => $accResult[$i]['vl_test_platform'],
                'import_machine_name' => $accResult[$i]['import_machine_name'],
            );

            if ($accResult[$i]['result_status'] == '4') {
                $data['is_sample_rejected'] = 'yes';
                $data['reason_for_sample_rejection'] = $rejectedReasonId[$i];
                $data['result_value_log'] = null;
                $data['result_value_absolute'] = null;
                $data['result_value_text'] = null;
                $data['result_value_absolute_decimal'] = null;
                $data['result'] = null;
            } else {
                $data['result_status'] = $status[$i];

                $data['is_sample_rejected'] = 'no';
                $data['reason_for_sample_rejection'] = null;

                if (!empty(trim($accResult[$i]['result_value_text'])) && $accResult[$i]['result_value_text'] != '') {
                    $data['result'] = trim($accResult[$i]['result_value_text']);
                } elseif ($accResult[$i]['result_value_absolute'] != '') {
                    $data['result'] = $accResult[$i]['result_value_absolute'];
                } elseif ($accResult[$i]['result_value_log'] != '') {
                    $data['result'] = $accResult[$i]['result_value_log'];
                }
            }

            $data['vl_result_category'] = $vlService->getVLResultCategory($data['result_status'], $data['result']);

            if ($data['vl_result_category'] == 'failed' || $data['vl_result_category'] == 'invalid') {
                $data['result_status'] = 5;
            } elseif ($data['vl_result_category'] == 'rejected') {
                $data['result_status'] = 4;
            }

            //get bacth code
            $bquery = "SELECT * FROM batch_details WHERE batch_code= ?";
            $bvlResult = $db->rawQuery($bquery, [$accResult[$i]['batch_code']]);
            if ($bvlResult) {
                $data['sample_batch_id'] = $bvlResult[0]['batch_id'];
            } else {
                $batchResult = $db->insert('batch_details', [
                    'batch_code' => $accResult[$i]['batch_code'],
                    'batch_code_key' => $accResult[$i]['batch_code_key'],
                    'sent_mail' => 'no',
                    'request_created_datetime' => DateUtility::getCurrentDateTime()
                ]);
                $data['sample_batch_id'] = $db->getInsertId();
            }
            $data['data_sync'] = 0;
            $db = $db->where('sample_code', $accResult[$i]['sample_code']);
            $result = $db->update('form_vl', $data);

            $numberOfResults++;

            $printSampleCode[] = "'" . $accResult[$i]['sample_code'] . "'";
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name']) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name'])) {
                if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results")) {
                    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results", 0777, true);
                }
                copy(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name']);
            }
            $db = $db->where('temp_sample_id', $accResult[$i]['temp_sample_id']);
            $result = $db->update('temp_sample_import', array('temp_sample_status' => 1));
        }
    }
    $stQuery = "SELECT *
                    FROM temp_sample_import as tsr
                    LEFT JOIN form_vl as vl ON vl.sample_code=tsr.sample_code
                    WHERE imported_by =? AND tsr.sample_type='s'";
    $stResult = $db->rawQuery($stQuery, [$importedBy]);

    if ($numberOfResults > 0) {
        $importedBy = $_SESSION['userId'] ?? 'AUTO';
        $general->resultImportStats($numberOfResults, $fileName, $importedBy);
    }

    echo "importedStatistics.php";
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

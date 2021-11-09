<?php

// this file is included in /import-result/procesImportedResults.php


$tableName = "temp_sample_import";
$tableName1 = "form_covid19";
$tableName2 = "hold_sample_import";
$fileName = null;
$importedBy = $_SESSION['userId'];

$covid19Obj = new \Vlsm\Models\Covid19();



try {
    $numberOfResults  = 0;
    $cSampleQuery = "SELECT * FROM global_config";
    $cSampleResult = $db->query($cSampleQuery);
    $arr = array();
    $printSampleCode = array();
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($cSampleResult); $i++) {
        $arr[$cSampleResult[$i]['name']] = $cSampleResult[$i]['value'];
    }

    $importNonMatching = (isset($arr['import_non_matching_sample']) && $arr['import_non_matching_sample'] == 'no') ? false : true;

    $instanceQuery = "SELECT * FROM s_vlsm_instance";
    $instanceResult = $db->query($instanceQuery);
    $result = '';
    $id = explode(",", $_POST['value']);
    $status = explode(",", $_POST['status']);
    $rejectedReasonId = explode(",", $_POST['rejectReasonId']);
    if ($_POST['value'] != '') {
        for ($i = 0; $i < count($id); $i++) {
            $sQuery = "SELECT * FROM temp_sample_import where imported_by ='$importedBy' AND temp_sample_id='" . $id[$i] . "'";
            $rResult = $db->rawQuery($sQuery);
            $fileName = $rResult[0]['import_machine_file_name'];

            if (isset($rResult[0]['approver_comments']) && $rResult[0]['approver_comments'] != "") {
                $comments = $rResult[0]['approver_comments']; //
                if ($_POST['comments'] != "") {
                    $comments .= " - " . $_POST['comments'];
                }
            } else {
                $comments = $_POST['comments'];
            }



            if ($rResult[0]['sample_type'] != 'S' && $rResult[0]['sample_type'] != 's') {
                $data = array(
                    'control_code' => $rResult[0]['sample_code'],
                    'lab_id' => $rResult[0]['lab_id'],
                    'control_type' => $rResult[0]['sample_type'],
                    'lot_number' => $rResult[0]['lot_number'],
                    'lot_expiration_date' => $rResult[0]['lot_expiration_date'],
                    'sample_tested_datetime'        => !empty($rResult[0]['sample_tested_datetime']) ? $rResult[0]['sample_tested_datetime'] : $general->getDateTime(),
                    //'is_sample_rejected'=>'yes',
                    //'reason_for_sample_rejection'=>$rResult[0]['reason_for_sample_rejection'],
                    'result' => $rResult[0]['result'],
                    'tested_by' => $_POST['testBy'],
                    'approver_comments' => $comments,
                    'result_reviewed_by' => $rResult[0]['result_reviewed_by'],
                    'result_reviewed_datetime' => $general->getDateTime(),
                    'result_approved_by' => $_POST['appBy'],
                    'result_approved_datetime' => $general->getDateTime(),
                    'vlsm_country_id' => $arr['vl_form'],
                    'file_name' => $rResult[0]['import_machine_file_name'],
                    'imported_date_time' => $rResult[0]['result_imported_datetime'],
                    'result_printed_datetime' => NULL
                );
                if ($status[$i] == 4) {
                    $data['is_sample_rejected'] = 'yes';
                    $data['reason_for_sample_rejection'] = $rejectedReasonId[$i];
                    $data['result'] = null;
                } else {
                    $data['is_sample_rejected'] = 'no';
                    $data['reason_for_sample_rejection'] = null;
                }
                $data['status'] = $status[$i];

                $bquery = "SELECT * FROM batch_details WHERE batch_code='" . $rResult[0]['batch_code'] . "'";
                $bvlResult = $db->rawQuery($bquery);
                if ($bvlResult) {
                    $data['batch_id'] = $bvlResult[0]['batch_id'];
                } else {
                    $batchResult = $db->insert('batch_details', array('batch_code' => $rResult[0]['batch_code'], 'batch_code_key' => $rResult[0]['batch_code_key'], 'sent_mail' => 'no', 'request_created_datetime' => $general->getDateTime()));
                    $data['batch_id'] = $db->getInsertId();
                }

                $db->insert('covid19_imported_controls', $data);
            } else {



                $data = array(

                    'result_reviewed_by' => $_POST['reviewedBy'],
                    'covid19_test_platform' => $rResult[0]['vl_test_platform'],
                    'import_machine_name' => $rResult[0]['import_machine_name'],
                    'approver_comments' => $comments,
                    'lot_number' => $rResult[0]['lot_number'],
                    'lot_expiration_date' => $rResult[0]['lot_expiration_date'],
                    'result' => $rResult[0]['result'],
                    'sample_tested_datetime' => $rResult[0]['sample_tested_datetime'],
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
                    //$data['last_modified_by']=$rResult[0]['result_reviewed_by'];
                    //$data['last_modified_datetime']=$general->getDateTime();
                    $data['status'] = $status[$i];
                    $data['import_batch_tracking'] = $_SESSION['controllertrack'];
                    $result = $db->insert($tableName2, $data);
                } else {
                    $data['tested_by'] = $_POST['testBy'];
                    $data['sample_tested_datetime'] = $rResult[0]['sample_tested_datetime'];
                    //$data['request_created_by'] = $rResult[0]['result_reviewed_by'];
                    //$data['request_created_datetime'] = $general->getDateTime();
                    $data['last_modified_by'] = $rResult[0]['result_reviewed_by'];
                    $data['last_modified_datetime'] = $general->getDateTime();
                    $data['result_approved_by'] = $_POST['appBy'];
                    $data['result_approved_datetime'] = $general->getDateTime();
                    $sampleVal = $rResult[0]['sample_code'];

                    if ($status[$i] == '4') {
                        $data['is_sample_rejected'] = 'yes';
                        $data['reason_for_sample_rejection'] = $rejectedReasonId[$i];
                        $data['result'] = null;
                    } else {
                        $data['is_sample_rejected'] = 'no';
                        $data['reason_for_sample_rejection'] = null;
                        $data['result'] = $rResult[0]['result'];
                    }



                    //get bacth code
                    $bquery = "select * from batch_details where batch_code='" . $rResult[0]['batch_code'] . "'";
                    $bvlResult = $db->rawQuery($bquery);
                    if ($bvlResult) {
                        $data['sample_batch_id'] = $bvlResult[0]['batch_id'];
                    } else {
                        $batchResult = $db->insert('batch_details', array('test_type' => 'covid19', 'batch_code' => $rResult[0]['batch_code'], 'batch_code_key' => $rResult[0]['batch_code_key'], 'sent_mail' => 'no', 'request_created_datetime' => $general->getDateTime()));
                        $data['sample_batch_id'] = $db->getInsertId();
                    }

                    $query = "SELECT covid19_id from form_covid19 where sample_code='" . $sampleVal . "'";
                    $vlResult = $db->rawQuery($query);



                    $data['sample_code'] = $rResult[0]['sample_code'];

                    if (count($vlResult) > 0) {
                        $data['vlsm_country_id'] = $arr['vl_form'];
                        $data['data_sync'] = 0;
                        if ($data['result'] == 'positive' || $covid19Obj->checkAllCovid19TestsForPositive($vlResult[0]['covid19_id'])) {
                            $data['result'] = null;  // CANNOT PUT FINAL RESULT FOR POSITIVE
                            $data['result_status'] = 6; // CANNOT ACCEPT IT AUTOMATICALLY
                            //var_dump($data);die;
                        }
                        $db = $db->where('sample_code', $rResult[0]['sample_code']);

                        $result = $db->update($tableName1, $data);
                        $covid19Obj->insertCovid19Tests($vlResult[0]['covid19_id'], $rResult[0]['lot_number'], $rResult[0]['lab_id'], $rResult[0]['sample_tested_datetime'], $rResult[0]['result']);
                    } else {
                        if ($importNonMatching == false) continue;
                        if ($data['result'] == 'positive') {
                            $data['result'] = null;  // CANNOT PUT FINAL RESULT FOR POSITIVE
                            $data['result_status'] = 6; // CANNOT ACCEPT IT AUTOMATICALLY
                        }
                        $data['sample_code'] = $rResult[0]['sample_code'];
                        $data['vlsm_country_id'] = $arr['vl_form'];
                        $data['vlsm_instance_id'] = $instanceResult[0]['vlsm_instance_id'];
                        $covid19Id = $db->insert($tableName1, $data);
                        $covid19Obj->insertCovid19Tests($covid19Id, $rResult[0]['lot_number'], $rResult[0]['lab_id'], $rResult[0]['sample_tested_datetime'], $rResult[0]['result']);
                    }
                    $printSampleCode[] = "'" . $rResult[0]['sample_code'] . "'";
                }
            }
            $db = $db->where('temp_sample_id', $id[$i]);
            $result = $db->update($tableName, array('temp_sample_status' => 1));
        }
        if (file_exists(TEMP_PATH . DIRECTORY_SEPARATOR . "import-result" . DIRECTORY_SEPARATOR . $rResult[0]['import_machine_file_name'])) {
            copy(TEMP_PATH . DIRECTORY_SEPARATOR . "import-result" . DIRECTORY_SEPARATOR . $rResult[0]['import_machine_file_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "import-result" . DIRECTORY_SEPARATOR . $rResult[0]['import_machine_file_name']);
        }
    }
    //get all accepted data result
    $accQuery = "SELECT tsr.*, vl.covid19_id FROM temp_sample_import as tsr LEFT JOIN form_covid19 as vl ON vl.sample_code=tsr.sample_code where imported_by ='$importedBy' AND tsr.result_status=7";
    $accResult = $db->rawQuery($accQuery);
    if ($accResult) {
        for ($i = 0; $i < count($accResult); $i++) {
            $data = array(

                //'sample_received_at_vl_lab_datetime' => $accResult[$i]['sample_received_at_vl_lab_datetime'],
                //'sample_tested_datetime'=>$accResult[$i]['sample_tested_datetime'],
                //'result_dispatched_datetime' => $accResult[$i]['result_dispatched_datetime'],
                'result_reviewed_datetime' => $accResult[$i]['result_reviewed_datetime'],
                'result_reviewed_by' => $_POST['reviewedBy'],
                'approver_comments' => $_POST['comments'],
                'lot_number' => $accResult[$i]['lot_number'],
                'lot_expiration_date' => $accResult[$i]['lot_expiration_date'],
                'result' => $accResult[$i]['result'],
                'sample_tested_datetime' => $accResult[$i]['sample_tested_datetime'],
                'lab_id' => $accResult[$i]['lab_id'],
                'tested_by' => $_POST['testBy'],
                //'request_created_by' => $accResult[$i]['result_reviewed_by'],
                //'request_created_datetime' => $general->getDateTime(),
                'last_modified_datetime' => $general->getDateTime(),
                'result_approved_by' => $_POST['appBy'],
                'result_approved_datetime' => $general->getDateTime(),
                'import_machine_file_name' => $accResult[$i]['import_machine_file_name'],
                'manual_result_entry' => 'no',
                //'result_status'=>'7',
                'covid19_test_platform' => $accResult[$i]['vl_test_platform'],
                'import_machine_name' => $accResult[$i]['import_machine_name'],
            );

            if ($accResult[$i]['result_status'] == '4') {
                $data['is_sample_rejected'] = 'yes';
                $data['reason_for_sample_rejection'] = $rejectedReasonId[$i];
                $data['result'] = null;
            } else {
                $data['is_sample_rejected'] = 'no';
                $data['reason_for_sample_rejection'] = null;
                $data['result_status'] = isset($status[$i]) ? $status[$i] : 7;
            }
            //get bacth code
            $bquery = "SELECT * FROM batch_details where batch_code='" . $accResult[$i]['batch_code'] . "'";
            $bvlResult = $db->rawQuery($bquery);
            if ($bvlResult) {
                $data['sample_batch_id'] = $bvlResult[0]['batch_id'];
            } else {
                $batchResult = $db->insert('batch_details', array('batch_code' => $accResult[$i]['batch_code'], 'batch_code_key' => $accResult[$i]['batch_code_key'], 'sent_mail' => 'no', 'request_created_datetime' => $general->getDateTime()));
                $data['sample_batch_id'] = $db->getInsertId();
            }
            $data['data_sync'] = 0;
            if ($data['result'] == 'positive' || $covid19Obj->checkAllCovid19TestsForPositive($accResult[$i]['covid19_id'])) {
                $data['result'] = null;  // CANNOT PUT FINAL RESULT FOR POSITIVE
                $data['result_status'] = 6; // CANNOT ACCEPT IT AUTOMATICALLY
                //var_dump($data);die;
            }
            $db = $db->where('sample_code', $accResult[$i]['sample_code']);
            $result = $db->update($tableName1, $data);

            $numberOfResults++;

            $printSampleCode[] = "'" . $accResult[$i]['sample_code'] . "'";
            if (file_exists(TEMP_PATH . DIRECTORY_SEPARATOR . "import-result" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name'])) {
                copy(TEMP_PATH . DIRECTORY_SEPARATOR . "import-result" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "import-result" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name']);
            }
            $db = $db->where('temp_sample_id', $accResult[$i]['temp_sample_id']);
            $result = $db->update($tableName, array('temp_sample_status' => 1));
        }
    }
    $sCode = implode(', ', $printSampleCode);
    $samplePrintQuery = "SELECT vl.*,s.sample_name,b.*,ts.*,f.facility_name,l_f.facility_name as labName,f.facility_code,f.facility_state,f.facility_district,u_d.user_name as reviewedBy,a_u_d.user_name as approvedBy ,rs.rejection_reason_name FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id LEFT JOIN r_covid19_sample_type as s ON s.sample_id=vl.sample_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by LEFT JOIN r_covid19_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection";
    $samplePrintQuery .= ' where vl.sample_code IN ( ' . $sCode . ')'; // Append to condition

    $_SESSION['covid19PrintQuery'] = $samplePrintQuery;
    $stQuery = "SELECT * FROM temp_sample_import as tsr LEFT JOIN eid_form as vl ON vl.sample_code=tsr.sample_code where imported_by ='$importedBy' AND tsr.sample_type='s'";
    $stResult = $db->rawQuery($stQuery);

    if ($numberOfResults > 0) {
        $importedBy = isset($_SESSION['userId']) ? $_SESSION['userId'] : 'AUTO';
        $general->resultImportStats($numberOfResults, $fileName, $importedBy);
    }

    //if (!$stResult) {
    //    echo "importedStatistics.php";
    //}

    echo "importedStatistics.php";
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

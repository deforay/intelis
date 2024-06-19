<?php

// this file is included in /import-result/processImportedResults.php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TestResultsService $testResultsService */
$testResultsService = ContainerRegistry::get(TestResultsService::class);

$tableName = "temp_sample_import";
$tableName1 = "form_eid";
$fileName = null;
$importedBy = $_SESSION['userId'];


try {
    $numberOfResults  = 0;
    $arr = $general->getGlobalConfig();

    $importNonMatching = !(isset($arr['import_non_matching_sample']) && $arr['import_non_matching_sample'] == 'no');

    $instanceQuery = "SELECT * FROM s_vlsm_instance";
    $instanceResult = $db->query($instanceQuery);
    $result = '';
    $id = explode(",", (string) $_POST['value']);
    $totalIds = count($id);
    $status = explode(",", (string) $_POST['status']);
    $rejectedReasonId = explode(",", (string) $_POST['rejectReasonId']);
    if ($_POST['value'] != '' && !empty($_POST['value'])) {
        for ($i = 0; $i < $totalIds; $i++) {
            $sQuery = "SELECT * FROM temp_sample_import WHERE imported_by =? AND temp_sample_id= ?";
            $rResult = $db->rawQueryOne($sQuery, [$importedBy, $id[$i]]);
            $fileName = $rResult['import_machine_file_name'];

            if (isset($rResult['lab_tech_comments']) && $rResult['lab_tech_comments'] != "") {
                $comments = $rResult['lab_tech_comments']; //
                if ($_POST['comments'] != "") {
                    $comments .= " - " . $_POST['comments'];
                }
            } else {
                $comments = $_POST['comments'];
            }

            if (strtolower($rResult['sample_type']) != 's') {
                $data = array(
                    'control_code' => $rResult['sample_code'] ?? ($rResult['sample_type'] . "-" . $rResult['batch_code']),
                    'lab_id' => $rResult['lab_id'],
                    'control_type' => $rResult['sample_type'],
                    'lot_number' => $rResult['lot_number'],
                    'lot_expiration_date' => $rResult['lot_expiration_date'],
                    'sample_tested_datetime'        => $rResult['sample_tested_datetime'] ?? DateUtility::getCurrentDateTime(),
                    'result' => $rResult['result'],
                    'tested_by' => $_POST['testBy'],
                    'lab_tech_comments' => $comments,
                    'result_reviewed_by' => $rResult['result_reviewed_by'],
                    'result_reviewed_datetime' => DateUtility::getCurrentDateTime(),
                    'result_approved_by' => $_POST['appBy'],
                    'result_approved_datetime' => DateUtility::getCurrentDateTime(),
                    'vlsm_country_id' => $arr['vl_form'],
                    'file_name' => $rResult['import_machine_file_name'],
                    'imported_date_time' => $rResult['result_imported_datetime'],
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

                $bquery = "select * from batch_details where batch_code= ?";
                $bvlResult = $db->rawQuery($bquery, [$rResult['batch_code']]);
                if ($bvlResult) {
                    $data['batch_id'] = $bvlResult[0]['batch_id'];
                } else {
                    $batchResult = $db->insert('batch_details', [
                        'test_type' => 'eid',
                        'batch_code' => $rResult['batch_code'],
                        'batch_code_key' => $rResult['batch_code_key'],
                        'sent_mail' => 'no',
                        'request_created_datetime' => DateUtility::getCurrentDateTime()
                    ]);
                    $data['batch_id'] = $db->getInsertId();
                }

                $db->insert('eid_imported_controls', $data);
            } else {

                $data = array(
                    'result_reviewed_by' => $_POST['reviewedBy'],
                    'import_machine_name' => $rResult['import_machine_name'],
                    'lab_tech_comments' => $comments,
                    'lot_number' => $rResult['lot_number'],
                    'lot_expiration_date' => $rResult['lot_expiration_date'],
                    'result' => $rResult['result'],
                    'sample_tested_datetime' => $rResult['sample_tested_datetime'] ?? DateUtility::getCurrentDateTime(),
                    'lab_id' => $rResult['lab_id'],
                    'import_machine_file_name' => $rResult['import_machine_file_name'],
                    'manual_result_entry' => 'no',
                    'result_printed_datetime' => null
                );
                if ($status[$i] == '1') {
                    $data['result_reviewed_by'] = $_POST['reviewedBy'];
                    $data['facility_id'] = $rResult['facility_id'];
                    $data['sample_code'] = $rResult['sample_code'];
                    $data['batch_code'] = $rResult['batch_code'];
                    $data['sample_type'] = $rResult['sample_type'];
                    $data['vl_test_platform'] = $rResult['vl_test_platform'];
                    $data['status'] = $status[$i];
                    $data['import_batch_tracking'] = $_SESSION['controllertrack'];
                    $result = $db->insert('hold_sample_import', $data);
                } else {
                    $data['eid_test_platform'] = $rResult['vl_test_platform'];
                    $data['tested_by'] = $_POST['testBy'];
                    $data['sample_tested_datetime'] = $rResult['sample_tested_datetime'];
                    $data['last_modified_by'] = $rResult['result_reviewed_by'];
                    $data['last_modified_datetime'] = DateUtility::getCurrentDateTime();
                    $data['result_approved_by'] = $_POST['appBy'];
                    $data['result_approved_datetime'] = DateUtility::getCurrentDateTime();
                    $sampleVal = $rResult['sample_code'];

                    if ($status[$i] == SAMPLE_STATUS\REJECTED) {
                        $data['is_sample_rejected'] = 'yes';
                        $data['reason_for_sample_rejection'] = $rejectedReasonId[$i];
                        $data['result'] = null;
                    } else {
                        $data['is_sample_rejected'] = 'no';
                        $data['reason_for_sample_rejection'] = null;
                        $data['result'] = $rResult['result'];
                    }
                    //get bacth code
                    $bquery = "select * from batch_details where batch_code='" . $rResult['batch_code'] . "'";
                    $bvlResult = $db->rawQuery($bquery);
                    if ($bvlResult) {
                        $data['sample_batch_id'] = $bvlResult[0]['batch_id'];
                    } else {
                        $batchResult = $db->insert('batch_details', array('test_type' => 'eid', 'batch_code' => $rResult['batch_code'], 'batch_code_key' => $rResult['batch_code_key'], 'sent_mail' => 'no', 'request_created_datetime' => DateUtility::getCurrentDateTime()));
                        $data['sample_batch_id'] = $db->getInsertId();
                    }

                    $query = "select eid_id, result from form_eid where sample_code='" . $sampleVal . "'";
                    $vlResult = $db->rawQuery($query);
                    $data['result_status'] = $status[$i];
                    $data['sample_code'] = $rResult['sample_code'];

                    if (!empty($vlResult)) {
                        $data['vlsm_country_id'] = $arr['vl_form'];
                        $data['data_sync'] = 0;
                        $db->where('sample_code', $rResult['sample_code']);
                        $result = $db->update($tableName1, $data);
                        $eidId = $vlResult[0]['eid_id'];
                    } else {
                        if (!$importNonMatching) {
                            continue;
                        }
                        $data['sample_code'] = $rResult['sample_code'];
                        $data['vlsm_country_id'] = $arr['vl_form'];
                        $data['vlsm_instance_id'] = $instanceResult[0]['vlsm_instance_id'];
                        $eidId = $db->insert($tableName1, $data);
                    }
                    $printSampleCode[] = "'" . $rResult['sample_code'] . "'";
                }
            }
            if (isset($eidId) && $eidId != "") {
                $db->insert('log_result_updates', array(
                    "user_id" => $_SESSION['userId'],
                    "vl_sample_id" => $eidId,
                    "test_type" => "vl",
                    "result_method" => "import",
                    "file_name" => $rResult['import_machine_file_name'],
                    "updated_datetime" => DateUtility::getCurrentDateTime()
                ));
            }
            $db->where('temp_sample_id', $id[$i]);
            $result = $db->update($tableName, array('temp_sample_status' => 1));
        }
        if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $rResult['import_machine_file_name'])) {
            copy(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $rResult['import_machine_file_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $rResult['import_machine_file_name']);
        }
    }
    //get all accepted data result
    $accQuery = "SELECT tsr.*
                    FROM temp_sample_import as tsr
                    LEFT JOIN form_eid as vl ON vl.sample_code=tsr.sample_code
                    WHERE imported_by = ? AND tsr.result_status=7";
    $accResult = $db->rawQuery($accQuery, [$importedBy]);
    if ($accResult) {
        for ($i = 0; $i < count($accResult); $i++) {
            $data = array(
                'result_reviewed_datetime' => $accResult[$i]['result_reviewed_datetime'],
                'result_reviewed_by' => $_POST['reviewedBy'],
                'lab_tech_comments' => $_POST['comments'],
                'lot_number' => $accResult[$i]['lot_number'],
                'lot_expiration_date' => $accResult[$i]['lot_expiration_date'],
                'result' => $accResult[$i]['result'],
                'sample_tested_datetime' => $accResult[$i]['sample_tested_datetime'],
                'lab_id' => $accResult[$i]['lab_id'],
                'tested_by' => $_POST['testBy'],
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'result_approved_by' => $_POST['appBy'],
                'result_approved_datetime' => DateUtility::getCurrentDateTime(),
                'import_machine_file_name' => $accResult[$i]['import_machine_file_name'],
                'manual_result_entry' => 'no',
                //'result_status'=>'7',
                'eid_test_platform' => $accResult[$i]['vl_test_platform'],
                'import_machine_name' => $accResult[$i]['import_machine_name'],
            );

            if ($accResult[$i]['result_status'] == SAMPLE_STATUS\REJECTED) {
                $data['is_sample_rejected'] = 'yes';
                $data['reason_for_sample_rejection'] = $rejectedReasonId[$i];
                $data['result'] = null;
            } else {
                $data['is_sample_rejected'] = 'no';
                $data['reason_for_sample_rejection'] = null;
                $data['result_status'] = $status[$i] ?? 7;
            }
            //get bacth code
            $bquery = "SELECT * FROM batch_details WHERE batch_code= ?";
            $bvlResult = $db->rawQuery($bquery, [$accResult[$i]['batch_code']]);
            if ($bvlResult) {
                $data['sample_batch_id'] = $bvlResult[0]['batch_id'];
            } else {
                $batchResult = $db->insert('batch_details', [
                    'test_type' => 'eid',
                    'batch_code' => $accResult[$i]['batch_code'],
                    'batch_code_key' => $accResult[$i]['batch_code_key'],
                    'sent_mail' => 'no',
                    'request_created_datetime' => DateUtility::getCurrentDateTime()
                ]);
                $data['sample_batch_id'] = $db->getInsertId();
            }
            $data['data_sync'] = 0;
            $db->where('sample_code', $accResult[$i]['sample_code']);
            $result = $db->update($tableName1, $data);

            $numberOfResults++;

            $printSampleCode[] = "'" . $accResult[$i]['sample_code'] . "'";
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name'])) {
                copy(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name']);
            }
            $db->where('temp_sample_id', $accResult[$i]['temp_sample_id']);
            $result = $db->update($tableName, ['temp_sample_status' => 1]);
        }
    }
    $sCode = implode(', ', $printSampleCode);
    $stQuery = "SELECT *
                    FROM temp_sample_import as tsr
                    LEFT JOIN form_eid as vl ON vl.sample_code=tsr.sample_code
                    WHERE imported_by =? AND tsr.sample_type='s'";
    $stResult = $db->rawQuery($stQuery, [$importedBy]);

    if ($numberOfResults > 0) {
        $importedBy = $_SESSION['userId'] ?? 'AUTO';
        $testResultsService->resultImportStats($numberOfResults, $fileName, $importedBy);
    }

    echo "importedStatistics.php";
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

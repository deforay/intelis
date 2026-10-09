<?php

// this file is included in /import-result/processImportedResults.php
use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\ON_HOLD;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\TestResultsService;
use App\Services\TestAttemptService;
use App\Services\ImportedSampleMatcher;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TestResultsService $testResultsService */
$testResultsService = ContainerRegistry::get(TestResultsService::class);

/** @var TestAttemptService $attempts */
$attempts = ContainerRegistry::get(TestAttemptService::class);

/** @var ImportedSampleMatcher $sampleMatcher */
$sampleMatcher = ContainerRegistry::get(ImportedSampleMatcher::class);

$fileName = null;
$importedBy = $_SESSION['userId'];

try {
    $numberOfResults = 0;

    $arr = $general->getGlobalConfig();
    $printSampleIds = [];

    $importNonMatching = !(isset($arr['import_non_matching_sample']) && $arr['import_non_matching_sample'] == 'no');
    $instanceQuery = "SELECT * FROM s_vlsm_instance";
    $instanceResult = $db->query($instanceQuery);
    $result = '';
    $id = explode(",", (string) $_POST['value']);
    $status = explode(",", (string) $_POST['status']);
    $rejectedReasonId = explode(",", (string) $_POST['rejectReasonId']);
    if ($_POST['value'] != '' && !empty($_POST['value'])) {
        $counter = count($id);
        for ($i = 0; $i < $counter; $i++) {
            // Written in this pass only: a stale id from the row before is no sample of this one.
            $hepatitisId = null;
            $sQuery = "SELECT * FROM temp_sample_import
                        WHERE imported_by =? AND temp_sample_id=?";
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

            if (strtolower((string) $rResult['sample_type']) !== 's') {
                $data = [
                    'control_code' => $rResult['sample_code'],
                    'lab_id' => $rResult['lab_id'],
                    'control_type' => $rResult['sample_type'],
                    'lot_number' => $rResult['lot_number'],
                    'lot_expiration_date' => $rResult['lot_expiration_date'],
                    'sample_tested_datetime' => $rResult['sample_tested_datetime'] ?? DateUtility::getCurrentDateTime(),
                    'result' => $rResult['result'],
                    'tested_by' => $_POST['testBy'],
                    'lab_tech_comments' => $comments,
                    'result_reviewed_by' => $rResult['result_reviewed_by'],
                    'result_reviewed_datetime' => DateUtility::getCurrentDateTime(),
                    'result_approved_by' => $_POST['appBy'],
                    'result_approved_datetime' => DateUtility::getCurrentDateTime(),
                    'vlsm_country_id' => $arr['vl_form'],
                    'import_machine_file_name' => $rResult['import_machine_file_name'],
                    'imported_date_time' => $rResult['result_imported_datetime'],
                ];
                if ($status[$i] == REJECTED) {
                    $data['is_sample_rejected'] = 'yes';
                    $data['reason_for_sample_rejection'] = $rejectedReasonId[$i];
                    $data['hbv_vl_count'] = null;
                    $data['hcv_vl_count'] = null;
                } else {
                    $data['is_sample_rejected'] = 'no';
                }
                $data['status'] = $status[$i];


                $db->insert('vl_imported_controls', $data);
            } else {

                $data = [
                    'result_reviewed_datetime' => $rResult['result_reviewed_datetime'],
                    'result_reviewed_by' => $_POST['reviewedBy'],
                    'instrument_id' => $rResult['import_machine_name'],
                    'import_machine_name' => $rResult['import_machine_name'],
                    'lab_tech_comments' => $comments,
                    'lot_number' => $rResult['lot_number'],
                    'lot_expiration_date' => $rResult['lot_expiration_date'],
                    'sample_tested_datetime' => $rResult['sample_tested_datetime'] ?? DateUtility::getCurrentDateTime(),
                    'lab_id' => $rResult['lab_id'],
                    'import_machine_file_name' => $rResult['import_machine_file_name'],
                    'manual_result_entry' => 'no',
                    'result_printed_datetime' => null
                ];
                if ($status[$i] == ON_HOLD) {
                    $data['result_reviewed_by'] = $_POST['reviewedBy'];
                    $data['facility_id'] = $rResult['facility_id'];
                    $data['sample_code'] = $rResult['sample_code'];
                    $data['sample_type'] = $rResult['sample_type'];
                    $data['vl_test_platform'] = $rResult['vl_test_platform'];
                    $data['status'] = $status[$i];
                    $result = $db->insert('hold_sample_import', $data);
                } else {
                    $data['hepatitis_test_platform'] = $rResult['vl_test_platform'];
                    $data['tested_by'] = $_POST['testBy'];
                    $data['sample_tested_datetime'] = $rResult['sample_tested_datetime'] ?? DateUtility::getCurrentDateTime();
                    // The reviewer chosen on the screen; a status change from the list sends none.
                    $data['result_reviewed_by'] = ($_POST['reviewedBy'] ?? '') ?: $rResult['result_reviewed_by'];
                    $data['last_modified_by'] = $rResult['result_reviewed_by'];
                    $data['last_modified_datetime'] = DateUtility::getCurrentDateTime();
                    $data['result_approved_by'] = $_POST['appBy'];
                    $data['result_approved_datetime'] = DateUtility::getCurrentDateTime();
                    $sampleVal = $rResult['sample_code'];

                    // The sample in the lab the file was imported for; a code can be shared.
                    $match = $sampleMatcher->find('hepatitis', $sampleVal, $rResult['lab_id']);
                    if (!ImportedSampleMatcher::writable($match, $importNonMatching)) {
                        continue;
                    }
                    $hepResult = $match['row'];


                    $testType = strtolower((string) ($hepResult['hepatitis_test_type'] ?? ''));
                    $resultField = $otherField = null;
                    if ($testType === 'hbv') {
                        $resultField = "hbv_vl_count";
                        $otherField = "hcv_vl_count";
                    } else {
                        // HCV, and the default when the test type is missing
                        $resultField = "hcv_vl_count";
                        $otherField = "hbv_vl_count";
                    }

                    if ($status[$i] == REJECTED) {
                        $data['is_sample_rejected'] = 'yes';
                        $data['reason_for_sample_rejection'] = $rejectedReasonId[$i];
                        $data[$resultField] = null;
                        $data[$otherField] = null;
                    } else {

                        $data['is_sample_rejected'] = 'no';
                        $data['reason_for_sample_rejection'] = null;
                        $data[$resultField] = $data[$otherField] = null;
                        $data[$resultField] = $rResult['result'];

                        if ($testType === '' || $testType === '0') {
                            $data[$otherField] = $data[$resultField];
                        }
                    }

                    $data['result_status'] = $status[$i];
                    $data['sample_code'] = $rResult['sample_code'];


                    if (!empty($hepResult)) {
                        $data['vlsm_country_id'] = $arr['vl_form'];
                        $data['data_sync'] = 0;

                        // Retain the outgoing result before the import overwrites it. This path
                        // has no "already has a result" guard, so re-importing a file replaces
                        // whatever was there, including a failure.
                        $attempts->archive('hepatitis', $match['id'], TestAttemptService::BY_IMPORT);

                        $db->where('hepatitis_id', $match['id']);
                        $result = $db->update('form_hepatitis', $data);

                        // Was read from $vlResult, which is never defined in this file, so it was
                        // always null and the log_result_updates entry below never ran for an
                        // updated sample. $hepResult is a single row, not a list.
                        $hepatitisId = $hepResult['hepatitis_id'];
                    } else {
                        $data['sample_code'] = $rResult['sample_code'];
                        $data['vlsm_country_id'] = $arr['vl_form'];
                        $data['vlsm_instance_id'] = $instanceResult[0]['vlsm_instance_id'];
                        // Created here, so this is its creation; an update above is not.
                        $data['request_created_datetime'] = DateUtility::getCurrentDateTime();
                        $hepatitisId = $db->insert('form_hepatitis', $data);
                    }
                    $printSampleIds[] = (int) $hepatitisId;
                    // Counted here too: rows written by this pass are not written again below.
                    $numberOfResults++;
                }
            }
            if (isset($hepatitisId) && $hepatitisId != "") {
                $db->insert('log_result_updates', ["user_id" => $_SESSION['userId'], "vl_sample_id" => $hepatitisId, "test_type" => "vl", "result_method" => "import", "updated_datetime" => DateUtility::getCurrentDateTime()]);
            }
            $db->where('temp_sample_id', $id[$i]);
            $result = $db->update('temp_sample_import', [
                'temp_sample_status' => 1,
            ] + ($hepatitisId !== null ? ['matched_sample_id' => (int) $hepatitisId] : []));
        }
        if (MiscUtility::fileExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $rResult['import_machine_file_name'])) {
            copy(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $rResult['import_machine_file_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $rResult['import_machine_file_name']);
        }
    }
    // Look up the per-row status/reject-reason the user submitted, keyed by
    // temp_sample_id. The POST arrays are aligned to the POST `value`
    // (temp_sample_id) array, so indexing them by the accepted-loop counter
    // below leaks a neighbouring row's status onto an unrelated valid result.
    $submittedStatusByTempId = [];
    $submittedRejectReasonByTempId = [];
    foreach ($id as $idx => $tempId) {
        $tempId = trim((string) $tempId);
        if ($tempId === '') {
            continue;
        }
        if (isset($status[$idx]) && $status[$idx] !== '') {
            $submittedStatusByTempId[$tempId] = $status[$idx];
        }
        if (isset($rejectedReasonId[$idx]) && $rejectedReasonId[$idx] !== '') {
            $submittedRejectReasonByTempId[$tempId] = $rejectedReasonId[$idx];
        }
    }

    // Accepted rows the loop above did not already write.
    $accQuery = "SELECT tsr.*
                    FROM temp_sample_import as tsr
                    WHERE imported_by =? AND tsr.temp_sample_status = 0 AND tsr.result_status='7'";
    $accResult = $db->rawQuery($accQuery, [$importedBy]);
    if ($accResult) {
        $counter = count($accResult);
        for ($i = 0; $i < $counter; $i++) {


            $match = $sampleMatcher->find('hepatitis', $accResult[$i]['sample_code'], $accResult[$i]['lab_id']);
            if ($match['id'] === null) {
                continue;
            }
            $hepResult = $match['row'];


            $testType = strtolower((string) $hepResult['hepatitis_test_type']);
            $resultField = $otherField = null;
            if ($testType === 'hbv') {
                $resultField = "hbv_vl_count";
                $otherField = "hcv_vl_count";
            } elseif ($testType === 'hcv') {
                $resultField = "hcv_vl_count";
                $otherField = "hbv_vl_count";
            } else {
                continue;
            }
            $data = [
                'result_reviewed_by' => $_POST['reviewedBy'],
                'lab_tech_comments' => $_POST['comments'],
                'lot_number' => $accResult[$i]['lot_number'],
                'lot_expiration_date' => $accResult[$i]['lot_expiration_date'],
                'sample_tested_datetime' => $accResult[$i]['sample_tested_datetime'] ?? DateUtility::getCurrentDateTime(),
                'lab_id' => $accResult[$i]['lab_id'],
                'tested_by' => $_POST['testBy'],
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'] ?? null,
                'result_approved_by' => $_POST['appBy'],
                'result_approved_datetime' => DateUtility::getCurrentDateTime(),
                'import_machine_file_name' => $accResult[$i]['import_machine_file_name'],
                'manual_result_entry' => 'no',
                //'result_status'=>'7',
                'hepatitis_test_platform' => $accResult[$i]['vl_test_platform'],
                'import_machine_name' => $accResult[$i]['import_machine_name'],
            ];

            $data['hbv_vl_count'] = null;
            $data['hcv_vl_count'] = null;

            $tempId = (string) ($accResult[$i]['temp_sample_id'] ?? '');
            if ($accResult[$i]['result_status'] == REJECTED) {
                $data['is_sample_rejected'] = 'yes';
                $data['reason_for_sample_rejection'] = $submittedRejectReasonByTempId[$tempId] ?? null;
            } else {
                $data['result_status'] = $submittedStatusByTempId[$tempId] ?? 7;
                $data['is_sample_rejected'] = 'no';
                $data['reason_for_sample_rejection'] = null;
                $data[$resultField] = trim((string) $accResult[$i]['result']);
            }


            $data['data_sync'] = 0;

            // Retain the outgoing result before the import overwrites it.
            $attempts->archive('hepatitis', $match['id'], TestAttemptService::BY_IMPORT);

            $db->where('hepatitis_id', $match['id']);
            $result = $db->update('form_hepatitis', $data);

            $numberOfResults++;

            $printSampleIds[] = $match['id'];
            if (MiscUtility::fileExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name']) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name'])) {
                MiscUtility::makeDirectory(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results");
                copy(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $accResult[$i]['import_machine_file_name']);
            }
            $db->where('temp_sample_id', $accResult[$i]['temp_sample_id']);
            $result = $db->update('temp_sample_import', ['temp_sample_status' => 1, 'matched_sample_id' => $match['id']]);
        }
    }
    // The samples just written, by id: a code alone can name other labs' samples too.
    $printIds = implode(', ', $printSampleIds ?: [0]);
    $samplePrintQuery = "SELECT vl.*,
                            s.sample_name,
                            ts.*,
                            f.facility_name,
                            l.facility_name as labName,
                            f.facility_code,
                            f.facility_state,
                            f.facility_district,
                            u_d.user_name as reviewedBy,
                            a_u_d.user_name as approvedBy,
                            rs.rejection_reason_name
                            FROM form_hepatitis as vl
                            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN facility_details as l ON vl.lab_id=l.facility_id LEFT JOIN r_hepatitis_sample_type as s ON s.sample_id=vl.specimen_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by LEFT JOIN r_hepatitis_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection";
    $samplePrintQuery .= ' WHERE vl.hepatitis_id IN ( ' . $printIds . ')';
    $_SESSION['hepatitisPrintSearchResultQuery'] = $samplePrintQuery;

    if ($numberOfResults > 0) {
        $importedBy = $_SESSION['userId'] ?? 'AUTO';
        $testResultsService->resultImportStats($numberOfResults, $fileName, $importedBy);
    }
    echo "importedStatistics.php";
} catch (Exception $exc) {
    error_log($exc->getMessage());
    throw $exc;
}

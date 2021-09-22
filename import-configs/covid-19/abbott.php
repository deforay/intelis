<?php

// File included in addImportResultHelper.php
use League\Csv\Reader;
use League\Csv\Statement;


try {

    $db = $db->where('imported_by', $_SESSION['userId']);
    $db->delete('temp_sample_import');
    //set session for controller track id in hold_sample_record table
    $cQuery = "select MAX(import_batch_tracking) FROM hold_sample_import";
    $cResult = $db->query($cQuery);
    if ($cResult[0]['MAX(import_batch_tracking)'] != '') {
        $maxId = $cResult[0]['MAX(import_batch_tracking)'] + 1;
    } else {
        $maxId = 1;
    }
    $_SESSION['controllertrack'] = $maxId;

    $allowedExtensions = array(
        'txt',
    );
    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['resultFile']['name']);
    $fileName = str_replace(" ", "-", $fileName);
    $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName = $ranNumber . "." . $extension;

    if (!file_exists(TEMP_PATH . DIRECTORY_SEPARATOR . "import-result") && !is_dir(TEMP_PATH . DIRECTORY_SEPARATOR . "import-result")) {
        mkdir(TEMP_PATH . DIRECTORY_SEPARATOR . "import-result");
    }
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], TEMP_PATH . DIRECTORY_SEPARATOR . "import-result" . DIRECTORY_SEPARATOR . $fileName)) {


        $bquery = "select MAX(batch_code_key) from batch_details";
        $bvlResult = $db->rawQuery($bquery);
        if ($bvlResult[0]['MAX(batch_code_key)'] != '' && $bvlResult[0]['MAX(batch_code_key)'] != null) {
            $maxBatchCodeKey = $bvlResult[0]['MAX(batch_code_key)'] + 1;
            $maxBatchCodeKey = "00" . $maxBatchCodeKey;
        } else {
            $maxBatchCodeKey = '001';
        }

        $newBatchCode = date('Ymd') . $maxBatchCodeKey;


        //load the CSV document from a file path
        $csv = Reader::createFromPath(TEMP_PATH . DIRECTORY_SEPARATOR . "import-result" . DIRECTORY_SEPARATOR . $fileName, 'r');
        $csv->setDelimiter("\t");

        $stmt = new Statement();
        $topRecords = $stmt->limit(18)->process($csv);

        $metaRecords = array();
        foreach ($topRecords as $topRecord) {
            if (empty($topRecord[0])) continue;
            $metaRecords[$topRecord[0]] = $topRecord[1];
        }





        $csv->setHeaderOffset(20); //set the CSV header offset - including empty lines





        // get records starting from the specified row number
        // remember this does not count empty lines
        // So in your file you may have the data starting from line 20
        // but there are 2 empty lines, so we instruct to offset 18
        $records = $stmt->offset(18)->process($csv);
        $m = 0;
        $infoFromFile = array();
        foreach ($records as $record) {
            $m++;

            $sampleCode = "";
            $batchCode = "";
            $sampleType = "";
            $txtVal = "";
            $resultFlag = "";

            $sampleCode = $record['SAMPLE ID'];
            $sampleType = $record['SAMPLE TYPE'];

            //$batchCode = $record[$batchCodeCol];
            $resultFlag = $record['FLAGS'];
            //$reviewBy = $record[$reviewByCol];

            // Changing date to European format for strtotime - https://stackoverflow.com/a/5736255

            $testingDate = date('Y-m-d H:i', strtotime($metaRecords['RUN COMPLETION TIME']));
            $result = '';

            $result = strtolower($record['INTERPRETATION']);



            $lotNumberVal = $record['REAGENT LOT NUMBER'];
            
            if (trim($record["REAGENT LOT EXPIRATION DATE"]) != '') {
                $lotExpirationDateVal = (date('Y-m-d', strtotime($record["REAGENT LOT EXPIRATION DATE"])));
            }

            if ($sampleType == 'Patient') {
                $sampleType = 'S';
            } else if ($sampleType == 'Control') {

                if ($sampleCode == 'COV-2_POS') {
                    $sampleType = 'HPC';
                    $sampleCode = $sampleCode . '-' . $lotNumberVal;
                } else if ($sampleCode == 'COV-2_NEG') {
                    $sampleType = 'NC';
                    $sampleCode = $sampleCode . '-' . $lotNumberVal;
                }
            }

            $batchCode = "";


            if ($sampleCode == "") {
                $sampleCode = $sampleType . $m;
            }


            if (!isset($infoFromFile[$sampleCode])) {
                $infoFromFile[$sampleCode] = array(
                    "sampleCode" => $sampleCode,
                    "resultFlag" => $resultFlag,
                    "testingDate" => $testingDate,
                    "sampleType" => $sampleType,
                    "batchCode" => $batchCode,
                    "lotNumber" => $lotNumberVal,
                    "result" => $result,
                    "lotExpirationDate" => $lotExpirationDateVal,
                );
            }
        }







        $inc = 0;
        foreach ($infoFromFile as $sampleCode => $d) {
            if ($d['sampleCode'] == $d['sampleType'] . $inc) {
                $d['sampleCode'] = '';
            }
            $data = array(
                'module' => 'covid19',
                'lab_id' => base64_decode($_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'sample_type' => $d['sampleType'],
                'sample_tested_datetime' => $testingDate,
                'result_status' => '6',
                'import_machine_file_name' => $fileName,
                'approver_comments' => $d['resultFlag'],
                'lot_number' => $d['lotNumber'],
                'lot_expiration_date' => $d['lotExpirationDate'],
                'result' => $d['result'],
            );

            if ($batchCode == '') {
                $data['batch_code'] = $newBatchCode;
                $data['batch_code_key'] = $maxBatchCodeKey;
            } else {
                $data['batch_code'] = $batchCode;
            }
            //get user name
            if (isset($d['reviewBy']) && $d['reviewBy'] != '') {
                $uQuery = "select user_name,user_id from user_details where user_name='" . $d['reviewBy'] . "'";
                $uResult = $db->rawQuery($uQuery);
                if ($uResult) {
                    $data['sample_review_by'] = $uResult[0]['user_id'];
                } else {
                    $userId = $general->generateUUID();
                    $userdata = array(
                        'user_id' => $userId,
                        'user_name' => $d['reviewBy'],
                        'role_id' => '3',
                        'status' => 'active',
                    );
                    $db->insert('user_details', $userdata);
                    $data['sample_review_by'] = $userId;
                }
            }

            $query = "select facility_id,covid19_id,result from form_covid19 where sample_code='" . $sampleCode . "'";
            $vlResult = $db->rawQuery($query);
            //insert sample controls
            $scQuery = "select r_sample_control_name from r_sample_controls where r_sample_control_name='" . trim($d['sampleType']) . "'";
            $scResult = $db->rawQuery($scQuery);
            if ($scResult == false) {
                $scData = array('r_sample_control_name' => trim($d['sampleType']));
                $scId = $db->insert("r_sample_controls", $scData);
            }
            if ($vlResult && $sampleCode != '') {
                if (isset($vlResult[0]['result']) && !empty($vlResult[0]['result'])) {
                    $data['sample_details'] = 'Result already exists';
                } else {
                    $data['result_status'] = '7';
                }
                $data['facility_id'] = $vlResult[0]['facility_id'];
            } else {
                $data['sample_details'] = 'New Sample';
            }
            //echo "<pre>";var_dump($data);echo "</pre>";continue;
            if ($sampleCode != '' || $batchCode != '' || $sampleType != '') {
                $data['result_imported_datetime'] = $general->getDateTime();
                $data['imported_by'] = $_SESSION['userId'];
                $id = $db->insert("temp_sample_import", $data);
            }
            $inc++;
        }
    }

    $_SESSION['alertMsg'] = "Results imported successfully";
    //Add event log
    $eventType = 'import';
    $action = ucwords($_SESSION['userName']) . ' imported a new test result with the sample code ' . $sampleCode;
    $resource = 'import-result';
    $general->activityLog($eventType, $action, $resource);

    //new log for update in result
    if (isset($id) && $id > 0) {
        $data = array(
            'user_id' => $_SESSION['userId'],
            'vl_sample_id' => $id,
            'test_type' => 'covid19',
            'updated_on' => $general->getDateTime(),
        );
        $db->insert("log_result_updates", $data);
    }
    header("location:/import-result/imported-results.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

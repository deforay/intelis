<?php

// File included in addImportResultHelper.php

use App\Helpers\Results;
use App\Models\Users;
use App\Utilities\DateUtils;
use Aranyasen\HL7\Message;

try {
    $dateFormat = (isset($_POST['dateFormat']) && !empty($_POST['dateFormat']))?$_POST['dateFormat']:'d/m/Y H:i';
    $db = $db->where('imported_by', $_SESSION['userId']);
    $db->delete('temp_sample_import');
    //set session for controller track id in hold_sample_record table
    $cQuery = "SELECT MAX(import_batch_tracking) FROM hold_sample_import";
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
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName = $_POST['fileName'] . "." . $extension;
    // $ranNumber = \App\Models\General::generateRandomString(12);
    // $fileName = $ranNumber . "." . $extension;

    if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results")) {
        mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results", 0777, true);
    }
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName)) {

        $file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        $mime_type = $file_info->buffer(file_get_contents(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName)); // e.g. gives "image/jpeg"

        $bquery = "select MAX(batch_code_key) from batch_details";
        $bvlResult = $db->rawQuery($bquery);
        if ($bvlResult[0]['MAX(batch_code_key)'] != '' && $bvlResult[0]['MAX(batch_code_key)'] != null) {
            $maxBatchCodeKey = $bvlResult[0]['MAX(batch_code_key)'] + 1;
            $maxBatchCodeKey = "00" . $maxBatchCodeKey;
        } else {
            $maxBatchCodeKey = '001';
        }

        $newBatchCode = date('Ymd') . $maxBatchCodeKey;

        $m = 1;
        $skipTillRow = 23;

        $sampleIdCol = 1;
        $sampleTypeCol = 2;
        $resultCol = 5;
        $txtValCol = 6;

        $batchCodeVal = "";
        $flagCol = 10;
        $testDateCol = 11;

        $lotNumberCol = 12;
        $reviewByCol = '';
        $lotExpirationDateCol = 13;

        if (strpos($mime_type, 'text/plain') !== false) {
            $infoFromFile = [];
            $testDateRow = "";
            $skip = 23;

            $row = 1;
            if (($handle = fopen(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName, "r")) !== false) {

                while (($sheetData = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $num = count($sheetData);


                    // Create a Message object from a HL7 string
                    $msg = new Message($sheetData[0]); // Either \n or \r can be used as segment endings
                    $pid = $msg->getSegmentByIndex(1);
                    echo $pid->getField(3); // prints 'abcd'
                    echo $msg->toString(true); // Prints entire HL7 string

                    // Get the first segment
                    $msg->getFirstSegmentInstance('PID'); // Returns the first PID segment. Same as $msg->getSegmentsByName('PID')[0];

                    // Check if a segment is present in the message object
                    $msg->hasSegment('PID'); // return true or false based on whether PID is present in the $msg object

                    // Check if a message is empty
                    $msg = new Message();
                    $msg->isempty(); // Returns true

                    // echo "<pre>";
                    // print_r($msg->toString(true));
                    // echo "</pre>";
                    // die;
                    $row++;
                    if ($row < $skip) {
                        if ($row == 8) {
                            $testingDateArray = Results::abbottTestingDateFormatter($sheetData[1], $sheetData[2]);
                            $dateFormat = $testingDateArray['dateFormat'];
                            $testingDate = $testingDateArray['testingDate'];
                        }
                        continue;
                    }
                    $sampleCode = "";
                    $batchCode = "";
                    $sampleType = "";
                    // $resultFlag = "";
                    // echo "<pre>";
                    // print_r($sheetData);
                    // echo "</pre>";
                    // die;

                    $sampleCode = $sheetData[$sampleIdCol];

                    if ($sampleCode == "SAMPLE ID" || $sampleCode == "") {
                        continue;
                    }

                    $sampleType = $sheetData[$sampleTypeCol];

                    $batchCode = $sheetData[$batchCodeCol];
                    $resultFlag = $sheetData[$flagCol];
                    //$reviewBy = $sheetData[$reviewByCol];

                    $result = '';

                    if (strpos(strtolower($sheetData[$resultCol]), 'not detected') !== false) {
                        $result = 'negative';
                    } else if ((strpos(strtolower($sheetData[$resultCol]), 'detected') !== false) || (strpos(strtolower($sheetData[$resultCol]), 'passed') !== false)) {
                        $result = 'positive';
                    } else {
                        $result = 'indeterminate';
                    }


                    $lotNumberVal = $sheetData[$lotNumberCol];
                    if (trim($sheetData[$lotExpirationDateCol]) != '') {
                        $timestamp = DateTime::createFromFormat('!m/d/Y', $sheetData[$lotExpirationDateCol]);
                        if (!empty($timestamp)) {
                            $timestamp = $timestamp->getTimestamp();
                            $lotExpirationDateVal = date('Y-m-d H:i', $timestamp);
                        } else {
                            $lotExpirationDateVal = null;
                        }
                    }

                    $sampleType = $sheetData[$sampleTypeCol];
                    if ($sampleType == 'Patient') {
                        $sampleType = 'S';
                    } else if ($sampleType == 'Control') {

                        if ($sampleCode == 'HIV_HIPOS') {
                            $sampleType = 'HPC';
                            $sampleCode = $sampleCode . '-' . $lotNumberVal;
                        } else if ($sampleCode == 'HIV_LOPOS') {
                            $sampleType = 'LPC';
                            $sampleCode = $sampleCode . '-' . $lotNumberVal;
                        } else if ($sampleCode == 'HIV_NEG') {
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

                    $m++;
                }
            }
        }
        // echo "<pre>";
        // print_r($infoFromFile);
        // echo "</pre>";
        // die;
        $inc = 0;
        foreach ($infoFromFile as $sampleCode => $d) {
            if ($d['sampleCode'] == $d['sampleType'] . $inc) {
                $d['sampleCode'] = '';
            }
            $data = array(
                'module' => 'hepatitis',
                'lab_id' => base64_decode($_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'sample_type' => $d['sampleType'],
                'sample_tested_datetime' => $d['testingDate'],
                'result_status' => '6',
                'import_machine_file_name' => $fileName,
                'lab_tech_comments' => $d['resultFlag'],
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
            if (!empty($d['reviewBy'])) {
                $usersModel = new Users();
                $data['sample_review_by'] = $usersModel->addUserIfNotExists($d['reviewBy']);
            }

            $query = "select facility_id,hepatitis_id,result from form_hepatitis where sample_code='" . $sampleCode . "'";
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
            echo "<pre>";
            print_r($data);
            echo "</pre>";
            continue;
            if ($sampleCode != '' || $batchCode != '' || $sampleType != '') {
                $data['result_imported_datetime'] = DateUtils::getCurrentDateTime();
                $data['imported_by'] = $_SESSION['userId'];
                $id = $db->insert("temp_sample_import", $data);
            }
            $inc++;
        }
    }

    $_SESSION['alertMsg'] = "Results imported successfully";
    //Add event log
    $eventType = 'import';
    $action = $_SESSION['userName'] . ' imported a new test result with the sample code ' . $sampleCode;
    $resource = 'import-results-manually';
    $general->activityLog($eventType, $action, $resource);

    //new log for update in result
    if (isset($id) && $id > 0) {
        $data = array(
            'user_id' => $_SESSION['userId'],
            'vl_sample_id' => $id,
            'test_type' => 'hepatitis',
            'updated_on' => DateUtils::getCurrentDateTime(),
        );
        $db->insert("log_result_updates", $data);
    }
    header("Location:/import-result/imported-results.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

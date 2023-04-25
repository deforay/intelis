<?php

use App\Services\UserService;
use App\Utilities\DateUtils;
use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $db = $db->where('imported_by', $_SESSION['userId']);
    $db->delete('temp_sample_import');
    //set session for controller track id in hold_sample_record table
    $cQuery  = "select MAX(import_batch_tracking) FROM hold_sample_import";
    $cResult = $db->query($cQuery);
    if ($cResult[0]['MAX(import_batch_tracking)'] != '') {
        $maxId = $cResult[0]['MAX(import_batch_tracking)'] + 1;
    } else {
        $maxId = 1;
    }
    $_SESSION['controllertrack'] = $maxId;

    $allowedExtensions = array(
        'xls',
        'xlsx',
        'csv'
    );
    $fileName          = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['resultFile']['name']);
    $fileName          = str_replace(" ", "-", $fileName);
    $extension         = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    // $ranNumber         = \App\Services\CommonService::generateRandomString(12);
    // $fileName          = $ranNumber . "." . $extension;


    if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results")) {
        mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results", 0777, true);
    }
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName)) {
        //$file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        //$mime_type = $file_info->buffer(file_get_contents(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName)); // e.g. gives "image/jpeg"

        $objPHPExcel = IOFactory::load(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData   = $objPHPExcel->getActiveSheet();





        $bquery    = "select MAX(batch_code_key) from batch_details";
        $bvlResult = $db->rawQuery($bquery);
        if ($bvlResult[0]['MAX(batch_code_key)'] != '' && $bvlResult[0]['MAX(batch_code_key)'] != null) {
            $maxBatchCodeKey = $bvlResult[0]['MAX(batch_code_key)'] + 1;
            $maxBatchCodeKey = "00" . $maxBatchCodeKey;
        } else {
            $maxBatchCodeKey = '001';
        }

        $newBatchCode = date('Ymd') . $maxBatchCodeKey;

        $sheetData = $sheetData->toArray(null, true, true, true);


        //var_dump($sheetData);die;

        $m = 0;
        $skipTillRow = 2;

        $sampleIdCol = 'E';
        $sampleIdRow = '2';
        $logValCol = '';
        $logValRow = '';
        $absValCol = 'I';
        $absValRow = '2';
        $txtValCol = '';
        $txtValRow = '';
        $testingDateCol = 'D';
        $testingDateRow = '2';
        $logAndAbsoluteValInSameCol = 'no';
        $sampleTypeCol = 'F';
        $batchCodeCol = 'G';
        $flagCol = 'K';
        //$flagRow = '2';
        $lotNumberCol = 'O';
        $reviewByCol = 'L';
        $lotExpirationDateCol = 'P';

        foreach ($sheetData as $rowIndex => $row) {
            if ($rowIndex < $skipTillRow)
                continue;

            $sampleCode    = "";
            $batchCode     = "";
            $sampleType    = "";
            $absDecimalVal = "";
            $absVal        = "";
            $logVal        = "";
            $txtVal        = "";
            $resultFlag    = "";
            $testingDate   = "";
            $lotNumberVal = "";
            $reviewBy = "";
            $lotExpirationDateVal = null;

            $sampleCode = $row[$sampleIdCol];
            $sampleType = $row[$sampleTypeCol];
            $batchCode = $row[$batchCodeCol];
            $resultFlag = $row[$flagCol];
            $reviewBy = $row[$reviewByCol];

            if ($row[$testingDateCol] != '') {
                $alterDateTime = explode(" ", $row[$testingDateCol]);
                $alterDate = str_replace("/", "-", $alterDateTime[0]);
                $strToArray = explode("-", $alterDate);
                if (strlen($strToArray[0]) == 2 && strlen($strToArray[2]) == 2) {
                    if ($strToArray[0] == date('y')) {
                        $alterDate = date('Y') . "-" . $strToArray[1] . "-" . $strToArray[2];
                    } else {
                        $alterDate = $strToArray[0] . "-" . $strToArray[1] . "-" . date('Y');
                    }
                }
                $testingDate = date('Y-m-d H:i', strtotime($alterDate . " " . $alterDateTime[1]));
            }

            if (trim($row[$absValCol]) != "") {
                $resVal = explode("(", $row[$absValCol]);
                if (count($resVal) == 2) {

                    if (strpos($resVal[0], "<") !== false) {
                        $resVal[0] = str_replace("<", "", $resVal[0]);
                        $absDecimalVal = (float) trim($resVal[0]);
                        $absVal = "< " . (float) trim($resVal[0]);
                    } else if (strpos($resVal[0], ">") !== false) {
                        $resVal[0] = str_replace(">", "", $resVal[0]);
                        $absDecimalVal = (float) trim($resVal[0]);
                        $absVal = "> " . (float) trim($resVal[0]);
                    } else {
                        $absVal = (float) trim($resVal[0]);
                        $absDecimalVal = (float) trim($resVal[0]);
                    }

                    $logVal = substr(trim($resVal[1]), 0, -1);
                    if ($logVal == "1.30" || $logVal == "1.3") {
                        $absDecimalVal = 20;
                        $absVal = "< 20";
                    }
                } else {
                    $txtVal = trim($row[$absValCol]);
                    if ($txtVal == 'Invalid') {
                        $resultFlag = trim($txtVal);
                    }
                }
            }

            $lotNumberVal = $row[$lotNumberCol];
            if (trim($row[$lotExpirationDateCol]) != '') {
                $alterDate = str_replace("/", "-", $row[$lotExpirationDateCol]);
                $strToArray = explode("-", $alterDate);
                if (strlen($strToArray[0]) == 2 && strlen($strToArray[2]) == 2) {
                    if ($strToArray[0] == date('y')) {
                        $alterDate = date('Y') . "-" . $strToArray[1] . "-" . $strToArray[2];
                    } else {
                        $alterDate = $strToArray[0] . "-" . $strToArray[1] . "-" . date('Y');
                    }
                }
                $lotExpirationDateVal = date('Y-m-d', strtotime($alterDate));
            }

            if ($sampleCode == "") {
                continue;
            }
            //   continue;

            $infoFromFile[$sampleCode] = array(
                "sampleCode" => $sampleCode,
                "logVal" => trim($logVal),
                "absVal" => $absVal,
                "absDecimalVal" => $absDecimalVal,
                "txtVal" => $txtVal,
                "resultFlag" => $resultFlag,
                "testingDate" => $testingDate,
                "sampleType" => $sampleType,
                "batchCode" => $batchCode,
                "lotNumber" => $lotNumberVal,
                "lotExpirationDate" => $lotExpirationDateVal,
                "reviewBy" => $reviewBy
            );

            $m++;
        }
        $inc = 0;
        $refno = 0;
        foreach ($infoFromFile as $sampleCode => $d) {
            if ($d['sampleCode'] == $d['sampleType'] . $inc) {
                $d['sampleCode'] = '';
            }
            if ($d['sampleType'] == 'S' || $d['sampleType'] == 's') {
                $refno += 1;
            }
            $data = array(
                'module' => 'vl',
                'lab_id' => base64_decode($_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'result_value_log' => $d['logVal'],
                'sample_type' => $d['sampleType'],
                'result_value_absolute' => $d['absVal'],
                'result_value_text' => $d['txtVal'],
                'result_value_absolute_decimal' => $d['absDecimalVal'],
                'sample_tested_datetime' => $d['testingDate'],
                'result_status' => '6',
                'import_machine_file_name' => $fileName,
                'lab_tech_comments' => $d['resultFlag'],
                'lot_number' => $d['lotNumber'],
                'lot_expiration_date' => $d['lotExpirationDate']
            );

            //echo "<pre>";var_dump($data);continue;
            if ($d['absVal'] != "") {
                $data['result'] = $d['absVal'];
            } else if ($d['logVal'] != "") {
                $data['result'] = $d['logVal'];
            } else if ($d['txtVal'] != "") {
                $data['result'] = $d['txtVal'];
            } else {
                $data['result'] = "";
            }

            if ($batchCode == '') {
                $data['batch_code']     = $newBatchCode;
                $data['batch_code_key'] = $maxBatchCodeKey;
            } else {
                $data['batch_code'] = $batchCode;
            }
            //get user name
            if (!empty($d['reviewBy'])) {
                $usersModel = new UserService();
                $data['sample_review_by'] = $usersModel->addUserIfNotExists($d['reviewBy']);
            }

            $query    = "select facility_id,vl_sample_id,result,result_value_log,result_value_absolute,result_value_text,result_value_absolute_decimal from form_vl where sample_code='" . $sampleCode . "'";
            $vlResult = $db->rawQuery($query);
            //insert sample controls
            $scQuery = "select r_sample_control_name from r_sample_controls where r_sample_control_name='" . trim($d['sampleType']) . "'";
            $scResult = $db->rawQuery($scQuery);
            if ($scResult == false) {
                $scData = array('r_sample_control_name' => trim($d['sampleType']));
                $scId = $db->insert("r_sample_controls", $scData);
            }
            if ($vlResult && $sampleCode != '') {
                if ($vlResult[0]['result_value_log'] != '' || $vlResult[0]['result_value_absolute'] != '' || $vlResult[0]['result_value_text'] != '' || $vlResult[0]['result_value_absolute_decimal'] != '') {
                    $data['sample_details'] = 'Result already exists';
                } else {
                    $data['result_status'] = '7';
                }
                $data['facility_id'] = $vlResult[0]['facility_id'];
            } else {
                $data['sample_details'] = 'New Sample';
            }
            //echo "<pre>";var_dump($data);echo "</pre>";continue; 
            if ($sampleCode != '' || $batchCode != '' || $sampleType != '' || $logVal != '' || $absVal != '' || $absDecimalVal != '') {
                $data['result_imported_datetime'] = DateUtils::getCurrentDateTime();
                $data['imported_by'] = $_SESSION['userId'];
                $id = $db->insert("temp_sample_import", $data);

                //var_dump("DOBLA -- ".$id);continue;
            }
            $inc++;
        }
        $_SESSION['refno'] = $refno;
    }

    //    die;

    $_SESSION['alertMsg'] = "Results imported successfully";
    //Add event log
    $eventType            = 'import';
    $action               = $_SESSION['userName'] . ' imported a new test result with the sample code ' . $sampleCode;
    $resource             = 'import-result';
    $general->activityLog($eventType, $action, $resource);

    //new log for update in result
    if (isset($id) && $id > 0) {
        $data = array(
            'user_id' => $_SESSION['userId'],
            'vl_sample_id' => $id,
            'test_type' => 'eid',
            'updated_on' => DateUtils::getCurrentDateTime()
        );
        $db->insert("log_result_updates", $data);
    }
    header("Location:/import-result/imported-results.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

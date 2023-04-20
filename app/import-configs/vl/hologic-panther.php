<?php

// File included in addImportResultHelper.php

use App\Models\Users;
use App\Utilities\DateUtils;

try {
    $dateFormat = (isset($_POST['dateFormat']) && !empty($_POST['dateFormat'])) ? $_POST['dateFormat'] : 'm/d/Y H:i';
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
    $fileName          = $_POST['fileName'] . "." . $extension;
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
        $skipTillRow = 2;

        $sampleIdCol = 0;
        $sampleTypeCol = 19;
        $resultCol = 3;
        $txtValCol = null;

        $batchCodeVal = "";
        $flagCol = 10;
        $testDateCol = 36;

        $reviewByCol = 14;
        $lotNumberCol = 15;
        $lotExpirationDateCol = 16;


        if (strpos($mime_type, 'text/plain') !== false) {
            $infoFromFile = [];
            $testDateRow = "";
            $skip = 2;

            $row = 0;
            if (($handle = fopen(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName, "r")) !== false) {
                while (($sheetData = fgetcsv($handle, 10000, "\t")) !== false) {
                    
                    $row++;
                    //var_dump($row . "<br>");continue;

                    if ($row < $skip) {
                        continue;
                    }

                    $num = count($sheetData);
                    $sampleCode = "";
                    $batchCode = "";
                    $sampleType = "";
                    $absDecimalVal = "";
                    $absVal = "";
                    $logVal = "";
                    $txtVal = "";
                    $resultFlag = "";

                    $sampleCode = $sheetData[$sampleIdCol];
                    $sampleType = $sheetData[$sampleTypeCol];

                    //$batchCode = $sheetData[$batchCodeCol];
                    $resultFlag = $sheetData[$flagCol];
                    //$reviewBy = $sheetData[$reviewByCol];

                    // //Changing date to European format for strtotime - https://stackoverflow.com/a/5736255
                    if (strpos($sheetData[$resultCol], 'Log') !== false) {
                        $sheetData[$resultCol] = str_replace(",", ".", $sheetData[$resultCol]); // in case they are using european decimal format
                        $logVal = ((float) filter_var($sheetData[$resultCol], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                        $absDecimalVal = round(round(pow(10, $logVal) * 100) / 100);
                        if (strpos($sheetData[$resultCol], "<") !== false) {
                            $txtVal = $absVal = "< " . trim($absDecimalVal);
                        } else {
                            $txtVal = null;
                            $absVal = $absDecimalVal;
                        }
                    } else if (strpos($sheetData[$resultCol], 'Copies') !== false) {
                        if (strpos($sheetData[$resultCol], '<') !== false || $sheetData[$resultCol] == '839 Copies / mL') {
                            $txtVal = "Below Detection Level";
                            $logVal = $absDecimalVal = $absVal = $resultFlag = "";
                        } else {
                            $absVal = $absDecimalVal = abs((int) filter_var($sheetData[$resultCol], FILTER_SANITIZE_NUMBER_INT));
                        }
                    } else if (strpos($sheetData[$resultCol], 'IU/mL') !== false) {
                        $absVal = $absDecimalVal = abs((int) filter_var($sheetData[$resultCol], FILTER_SANITIZE_NUMBER_INT));
                    } else {
                        if (strpos(strtolower($sheetData[$resultCol]), 'not detected') !== false || strtolower($sheetData[$resultCol]) == 'target not detected') {
                            $txtVal = "Below Detection Level";
                            $resultFlag = "";
                            $absVal = "";
                            $logVal = "";
                        } else if ($sheetData[$resultCol] == "" || $sheetData[$resultCol] == null) {
                            //$txtVal =  $sheetData[$flagCol];
                            $txtVal = "Failed";
                            $resultFlag = $sheetData[$flagCol];
                        } else {
                            $txtVal = $sheetData[$resultCol + 1];
                            $resultFlag = "";
                            $absVal = "";
                            $logVal = "";
                        }
                    }


                    $testingDate = $testingDateObject = null;
                    if (trim($sheetData[$testDateCol]) != '') {
                        $testingDateObject = DateTimeImmutable::createFromFormat("!$dateFormat", $sheetData[$testDateCol]);
                        $errors = DateTimeImmutable::getLastErrors();
                        if (empty($errors['warning_count']) && empty($errors['error_count']) && !empty($testingDateObject) && $testingDateObject !== false) {
                            $testingDate = $testingDateObject->format('Y-m-d H:i');
                        }
                    }



                    $lotNumberVal = $sheetData[$lotNumberCol];
                    $lotExpirationDateVal = null;
                    if (trim($sheetData[$lotExpirationDateCol]) != '') {

                        $lotExpirationDateObject = DateTimeImmutable::createFromFormat("!$dateFormat", $sheetData[$lotExpirationDateCol]);
                        $errors = DateTimeImmutable::getLastErrors();
                        if (empty($errors['warning_count']) && empty($errors['error_count']) && !empty($lotExpirationDateObject) && $lotExpirationDateObject !== false) {
                            $lotExpirationDateVal = $lotExpirationDateObject->format('Y-m-d H:i');
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
                        );
                    } else {
                        // if (isset($logVal) && trim($logVal) != "") {
                        //     $infoFromFile[$sampleCode]['logVal'] = trim($logVal);
                        // }
                    }

                    $m++;
                }
            }
        }

        $inc = 0;
        foreach ($infoFromFile as $sampleCode => $d) {
            if ($d['sampleCode'] == $d['sampleType'] . $inc) {
                $d['sampleCode'] = '';
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
                'lot_expiration_date' => $d['lotExpirationDate'],
            );

            //echo "<pre>";var_dump($data);continue;
            if ($d['txtVal'] != "") {
                $data['result'] = $d['txtVal'];
            } else if ($d['absVal'] != "") {
                $data['result'] = $d['absVal'];
            } else if ($d['logVal'] != "") {
                $data['result'] = $d['logVal'];
            } else {
                $data['result'] = "";
            }

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

            $query = "SELECT facility_id,vl_sample_id,result,result_value_log,result_value_absolute,result_value_text,result_value_absolute_decimal FROM form_vl WHERE result_printed_datetime is null AND sample_code='" . $sampleCode . "'";
            $vlResult = $db->rawQuery($query);
            //insert sample controls
            $scQuery = "SELECT r_sample_control_name FROM r_sample_controls where r_sample_control_name='" . trim($d['sampleType']) . "'";
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
            // echo "<pre>";
            // var_dump($data);
            // echo "</pre>";
            // continue;
            if ($sampleCode != '' || $batchCode != '' || $sampleType != '' || $logVal != '' || $absVal != '' || $absDecimalVal != '') {
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
            'test_type' => 'vl',
            'updated_on' => DateUtils::getCurrentDateTime(),
        );
        $db->insert("log_result_updates", $data);
    }
    header("Location:/import-result/imported-results.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

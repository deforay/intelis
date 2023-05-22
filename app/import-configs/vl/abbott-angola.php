<?php

use App\Registries\ContainerRegistry;
use App\Services\UsersService;
use App\Utilities\DateUtility;

try {

    // Sanitized values from $request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = $GLOBALS['request'];
    $_POST = $request->getParsedBody();

    /** @noinspection PhpUndefinedVariableInspection */
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
        'txt'
    );
    $fileName           = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['resultFile']['name']);
    $fileName           = str_replace(" ", "-", $fileName);
    $extension          = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName           = $_POST['fileName'] . "." . $extension;
    // $ranNumber = $general->generateRandomString(12);
    // $fileName          = $ranNumber . "." . $extension;


    if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results")) {
        mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results", 0777, true);
    }
    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {

        $file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        $mime_type = $file_info->buffer(file_get_contents($resultFile)); // e.g. gives "image/jpeg"




        $bquery    = "select MAX(batch_code_key) from batch_details";
        $bvlResult = $db->rawQuery($bquery);
        if ($bvlResult[0]['MAX(batch_code_key)'] != '' && $bvlResult[0]['MAX(batch_code_key)'] != null) {
            $maxBatchCodeKey = $bvlResult[0]['MAX(batch_code_key)'] + 1;
            $maxBatchCodeKey = "00" . $maxBatchCodeKey;
        } else {
            $maxBatchCodeKey = '001';
        }

        $newBatchCode = date('Ymd') . $maxBatchCodeKey;



        $m           = 1;
        $skipTillRow = 23;

        $sampleIdCol   = 1;
        $sampleTypeCol = 2;
        $resultCol     = 5;
        $txtValCol     = 6;

        $batchCodeVal  = "";
        $flagCol       = 10;
        $testDateCol   = 11;


        $lotNumberCol = 12;
        $reviewByCol = '';
        $lotExpirationDateCol = 13;



        if (strpos($mime_type, 'text/plain') !== false) {
            $infoFromFile = [];
            $testDateRow = "";
            $skip = 23;

            $row = 1;
            if (($handle = fopen(realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName), "r")) !== false) {
                while (($sheetData = fgetcsv($handle, 1000, "\t")) !== FALSE) {
                    $num = count($sheetData);
                    $row++;
                    if ($row < $skip) continue;

                    $sampleCode = "";
                    $batchCode = "";
                    $sampleType = "";
                    $absDecimalVal = "";
                    $absVal = "";
                    $logVal = "";
                    $txtVal = "";
                    $resultFlag = "";
                    $testingDate = "";


                    $sampleCode = $sheetData[$sampleIdCol];
                    $sampleType = $sheetData[$sampleTypeCol];

                    $batchCode = $sheetData[$batchCodeCol];
                    $resultFlag = $sheetData[$flagCol];
                    //$reviewBy = $sheetData[$reviewByCol];

                    //Changing date to European format for strtotime - https://stackoverflow.com/a/5736255
                    $sheetData[$testDateCol] = str_replace("/", "-", $sheetData[$testDateCol]);
                    $testingDate = date('Y-m-d H:i', strtotime($sheetData[$testDateCol]));

                    if (strpos($sheetData[$resultCol], 'Copies / mL') !== false) {
                        $absVal = str_replace("Copies / mL", "", $sheetData[$resultCol]);
                        $absVal = str_replace(",", "", $sheetData[$resultCol]);
                        preg_match_all('!\d+!', $absVal, $absDecimalVal);
                        $absVal = $absDecimalVal = implode("", $absDecimalVal[0]);
                    } else if (strpos($sheetData[$resultCol], 'Log (IU/mL)') !== false) {
                        $logVal = str_replace("Log (IU/mL)", "", $sheetData[$resultCol]);
                        $logVal = str_replace(",", ".", $logVal);
                    } else if (strpos($sheetData[$resultCol], 'IU/mL') !== false) {
                        $absVal = str_replace("IU/mL", "", $sheetData[$resultCol]);
                        $absVal = str_replace(" ", "", $sheetData[$resultCol]);
                        preg_match_all('!\d+!', $absVal, $absDecimalVal);
                        $absVal = $absDecimalVal = implode("", $absDecimalVal[0]);
                    } else {
                        if ($sheetData[$resultCol] == "" || $sheetData[$resultCol] == null) {
                            //$txtVal =  $sheetData[$flagCol];
                            $txtVal =  "Failed";
                            $resultFlag = $sheetData[$flagCol];
                        } else {
                            $txtVal = $sheetData[$resultCol + 1];
                            $resultFlag = "";
                            $absVal = "";
                            $logVal = "";
                        }
                    }

                    $sampleType = $sheetData[$sampleTypeCol];
                    if ($sampleType == 'Patient') {
                        $sampleType = 'S';
                    }

                    $batchCode = "";

                    $lotNumberVal = $sheetData[$lotNumberCol];
                    if (trim($sheetData[$lotExpirationDateCol]) != '') {
                        //Changing date to European format for strtotime - https://stackoverflow.com/a/5736255
                        //$sheetData[$lotExpirationDateCol] = str_replace("/", "-", $sheetData[$lotExpirationDateCol]);
                        $lotExpirationDateVal = date('Y-m-d', strtotime($sheetData[$lotExpirationDateCol]));
                    }

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
                            "lotExpirationDate" => $lotExpirationDateVal
                        );
                    } else {
                        if (trim($logVal) != "") {
                            $infoFromFile[$sampleCode]['logVal'] = trim($logVal);
                        }
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

                /** @var UsersService $usersService */
                $usersService = ContainerRegistry::get(UsersService::class);
                $data['sample_review_by'] = $usersService->addUserIfNotExists($d['reviewBy']);
            }

            $query  = "SELECT facility_id,vl_sample_id,result,result_value_log,result_value_absolute,result_value_text,result_value_absolute_decimal from form_vl where result_printed_datetime is null AND sample_code='" . $sampleCode . "'";
            $vlResult = $db->rawQuery($query);
            //insert sample controls
            $scQuery = "select r_sample_control_name from r_sample_controls where r_sample_control_name='" . trim($d['sampleType']) . "'";
            $scResult = $db->rawQuery($scQuery);
            if (!$scResult) {
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
                $data['result_imported_datetime'] = DateUtility::getCurrentDateTime();
                $data['imported_by'] = $_SESSION['userId'];
                $id = $db->insert("temp_sample_import", $data);
            }
            $inc++;
        }
    }

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
            'updated_on' => DateUtility::getCurrentDateTime()
        );
        $db->insert("log_result_updates", $data);
    }
    header("Location:/import-result/imported-results.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

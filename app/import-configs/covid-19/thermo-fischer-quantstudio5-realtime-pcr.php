<?php

// File included in addImportResultHelper.php

use App\Models\Users;
use App\Utilities\DateUtils;
use PhpOffice\PhpSpreadsheet\IOFactory;

try {

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
        'xls',
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

        $bquery = "SELECT MAX(batch_code_key) from batch_details";
        $bvlResult = $db->rawQuery($bquery);
        if ($bvlResult[0]['MAX(batch_code_key)'] != '' && $bvlResult[0]['MAX(batch_code_key)'] != null) {
            $maxBatchCodeKey = $bvlResult[0]['MAX(batch_code_key)'] + 1;
            $maxBatchCodeKey = "00" . $maxBatchCodeKey;
        } else {
            $maxBatchCodeKey = '001';
        }

        $newBatchCode = date('Ymd') . $maxBatchCodeKey;

        $m = 1;
        $skipTillRow = 47;

        $sampleIdCol = "D";
        $resultCol = "J";


        $spreadsheet = IOFactory::load(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData   = $spreadsheet->getActiveSheet();
        $sheetData   = $sheetData->toArray(null, true, true, true);

        // echo "<pre>";
        // var_dump($sheetData);
        // die;

        $infoFromFile = [];
        $testDateRow = "";


        $row = 1;

        foreach ($sheetData as $rowIndex => $rowData) {

            if ($rowIndex == 34) {
                $rowData["B"] = str_replace("/", "-", $rowData["B"]);
                $testingDateTimeArray = explode(" ", $rowData["B"]);
                $testingDate = date('Y-m-d H:i', strtotime($testingDateTimeArray[0] . " " . $testingDateTimeArray[1]));
            }

            if ($rowIndex < $skipTillRow)
                continue;


            $num = count($rowData);
            $row++;

            $sampleCode = "";
            $absDecimalVal = "";
            $absVal = "";
            $logVal = "";
            $txtVal = "";
            $resultFlag = "";

            $sampleCode = $rowData[$sampleIdCol];

            if ($sampleCode == "Sample Name" || $sampleCode == "") {
                continue;
            }

            //$reviewBy = $rowData[$reviewByCol];

            // //Changing date to European format for strtotime - https://stackoverflow.com/a/5736255
            // $rowData[$testDateCol] = str_replace("/", "-", $rowData[$testDateCol]);
            // $testingDate = date('Y-m-d H:i', strtotime($rowData[$testDateCol]));
            $result = '';
            $hold = false;


            if(empty($rowData[$resultCol]) || $rowData[$resultCol] == 'NULL'){
                continue;
            }

            if (strtolower($rowData[$resultCol]) == 'n' || strtolower($rowData[$resultCol]) == 'negative') {
                $result = 'negative';
            } else if (strtolower($rowData[$resultCol]) == 'p' || strtolower($rowData[$resultCol]) == 'positive') {
                $result = 'positive';
            } else if (strtolower($rowData[$resultCol]) == 'pr' || strtolower($rowData[$resultCol]) == 'er') {
                $result = null;
                $hold = true;
            }



            if (!isset($infoFromFile[$sampleCode])) {
                if (!empty($result)) {
                    $infoFromFile[$sampleCode] = array(
                        "sampleCode" => $sampleCode,
                        "testingDate" => $testingDate,
                        "result" => $result
                    );
                } else if ($hold) {
                    $infoFromFile[$sampleCode] = array(
                        "sampleCode" => $sampleCode,
                        "testingDate" => null,
                        "result" => null,
                        "result_status" => 1 // 1 => hold
                    );
                }
            }

            $m++;
        }



        $inc = 0;
        foreach ($infoFromFile as $sampleCode => $d) {

            $data = array(
                'module' => 'covid19',
                'lab_id' => base64_decode($_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'sample_tested_datetime' => $testingDate,
                'sample_type' => 'S',
                'result_status' => !empty($d['result_status']) ? $d['result_status'] : '6',
                'import_machine_file_name' => $fileName,
                'result' => $d['result'],
            );

            if (empty($batchCode)) {
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

            $query = "SELECT facility_id,covid19_id,result from form_covid19 where sample_code='" . $sampleCode . "'";
            $vlResult = $db->rawQuery($query);

            if ($vlResult && $sampleCode != '') {
                if ($vlResult[0]['result'] != null && !empty($vlResult[0]['result'])) {
                    $data['sample_details'] = 'Result already exists';
                } else {
                    $data['result_status'] = '7';
                }
                $data['facility_id'] = $vlResult[0]['facility_id'];
            } else {
                $data['sample_details'] = 'New Sample';
            }
            //echo "<pre>";var_dump($data);echo "</pre>";continue;
            if ($sampleCode != '') {
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
            'test_type' => 'covid19',
            'updated_on' => DateUtils::getCurrentDateTime(),
        );
        $db->insert("log_result_updates", $data);
    }
    header("Location:/import-result/imported-results.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

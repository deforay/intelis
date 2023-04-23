<?php

use App\Utilities\DateUtils;
use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $dateFormat = (isset($_POST['dateFormat']) && !empty($_POST['dateFormat']))?$_POST['dateFormat']:'d/m/Y H:i';
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
    $fileName          = $_POST['fileName'] . "." . $extension;
    // $ranNumber = \App\Services\CommonService::generateRandomString(12);
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

        $sheetData   = $sheetData->toArray(null, true, true, true);


        $m           = 0;
        $skipTillRow = 19;


        $sampleIdCol = 'B';
        $sampleIdRow = '19';
        $logValCol = '';
        $logValRow = '';
        $absValCol = 'G';
        $absValRow = '19';
        $txtValCol = '';
        $txtValRow = '';
        $testingDateCol = 'C';
        $testingDateRow = '4';
        $sampleTypeCol = '';
        $batchCodeCol = 'I';

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


            $sampleCode = $row[$sampleIdCol];

            if (strpos(strtolower($sampleCode), 'control') == false && (int)$sampleCode > 0) {
                $sampleType = "S";
            } else {
                $sampleType = $sampleCode;
            }


            if (trim($row[$absValCol]) == "<") {
                $absDecimalVal = $absVal = "";
                $logVal = "";
                $txtVal = "< 100";
            } else if ((int)$row[$absValCol] > 0) {
                $absDecimalVal = $absVal = (int)$row[$absValCol];
                $logVal = round(log10($absVal), 4);
                $txtVal = "";
            } else {
                $absDecimalVal = $absVal = "";
                $logVal = "";
                $txtVal = "";
            }


            //$absDecimalVal=$absVal=$row[$absValCol];           
            $batchCode = $row[$batchCodeCol];

            // Date time in the provided Biomerieux Sample file is in this format : 05-23-16 12:52:33
            $testingDate = $sheetData[6]['C'] . " " . $sheetData[7]['C'];
            $testingDate = DateTime::createFromFormat($dateFormat, $testingDate)->format('Y-m-d H:i:s');

            if ($sampleCode == "")
                break;

            $infoFromFile[$sampleCode] = array(
                "sampleCode" => $sampleCode,
                "logVal" => trim($logVal),
                "absVal" => $absVal,
                "absDecimalVal" => $absDecimalVal,
                "txtVal" => $txtVal,
                "resultFlag" => $resultFlag,
                "testingDate" => $testingDate,
                "sampleType" => $sampleType,
                "batchCode" => $batchCode
            );

            $m++;
        }


        foreach ($infoFromFile as $sampleCode => $d) {
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
                'lab_tech_comments' => $d['resultFlag']
            );


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

            $query    = "SELECT facility_id,vl_sample_id,result,result_value_log,result_value_absolute,result_value_text,result_value_absolute_decimal from form_vl where result_printed_datetime is null AND sample_code='" . $sampleCode . "'";
            $vlResult = $db->rawQuery($query);
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
            }
        }
    }

    $_SESSION['alertMsg'] = "Results imported successfully";
    //Add event log
    $eventType            = 'import';
    $action               = $_SESSION['userName'] . ' imported a new test result with the sample code ' . $sampleCode;
    $resource             = 'import-result';
    $general->activityLog($eventType, $action, $resource);

    //new log for update in result
    $data = array(
        'user_id' => $_SESSION['userId'],
        'vl_sample_id' => $id,
        'test_type' => 'vl',
        'updated_on' => DateUtils::getCurrentDateTime()
    );
    $db->insert("log_result_updates", $data);

    header("Location:/import-result/imported-results.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

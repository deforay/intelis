<?php


use App\Exceptions\SystemException;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

try {
    $dateFormat = (isset($_POST['dateFormat']) && !empty($_POST['dateFormat'])) ? $_POST['dateFormat'] : 'd/m/Y H:i';
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
    if (
        isset($_FILES['resultFile']) && $_FILES['resultFile']['error'] !== UPLOAD_ERR_OK
        || $_FILES['resultFile']['size'] <= 0
    ) {
        throw new SystemException('Please select a file to upload', 400);
    }

    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', htmlspecialchars(basename($_FILES['resultFile']['name'])));
    $fileName          = str_replace(" ", "-", $fileName);
    $extension         = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName          = $_POST['fileName'] . "." . $extension;
    // $ranNumber = $general->generateRandomString(12);
    // $fileName          = $ranNumber . "." . $extension;


    if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results")) {
        mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results", 0777, true);
    }
    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {
        //$file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        //$mime_type = $file_info->buffer(file_get_contents($resultFile)); // e.g. gives "image/jpeg"

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
            $sampleType = $row[$sampleTypeCol];

            $batchCode = $row[$batchCodeCol];
            $resultFlag = $row[$flagCol];


            // Date time in the provided Roche Sample file is in this format : 9/9/16 12:22
            //$testingDate = DateTime::createFromFormat($dateFormat, $row[$testingDateCol])->format('Y-m-d H:i');

            $testingDate = date('Y-m-d H:i', strtotime($row[$testingDateCol]));



            if (trim($row[$absValCol]) != "") {
                $resVal = (int)$row[$absValCol];
                if ($resVal > 0) {
                    $absDecimalVal = $absVal = trim($row[$absValCol]);

                    $logVal = round(log10($absVal), 4);
                    $txtVal = "";
                } else {
                    $absDecimalVal = $absVal = "";
                    $logVal = "";
                    $txtVal = trim($row[$absValCol]);
                }
            }

            if (trim($row[$absValCol]) != "") {
                $resVal = explode("(", $row[$absValCol]);
                if (count($resVal) == 2) {
                    $absVal = trim($resVal[0]);

                    $expAbsVal = explode("E", $absVal);
                    if (count($expAbsVal) == 2) {
                        $multipleVal = substr($expAbsVal[1], 1);
                        $absDecimalVal = $expAbsVal[0] * pow(10, $multipleVal);
                    }
                    $logVal = substr(trim($resVal[1]), 0, -1);
                } else {
                    $txtVal = trim($row[$absValCol]);
                    if ($txtVal == 'Invalid') {
                        $resultFlag = trim($txtVal);
                    }
                }
            }


            if ($sampleCode == "")
                continue;


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
                $data['result_imported_datetime'] = DateUtility::getCurrentDateTime();
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
        'updated_on' => DateUtility::getCurrentDateTime()
    );
    $db->insert("log_result_updates", $data);

    header("Location:/import-result/imported-results.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

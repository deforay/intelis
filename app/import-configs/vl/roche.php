<?php

use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\IOFactory;

try {


    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    
    $dateFormat = (isset($_POST['dateFormat']) && !empty($_POST['dateFormat']))?$_POST['dateFormat']:'d/m/Y H:i';
    $db = $db->where('imported_by', $_SESSION['userId']);
    $db->delete('temp_sample_import');
    //set session for controller track id in hold_sample_record table
    $cQuery  = "SELECT MAX(import_batch_tracking) FROM hold_sample_import";
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
    // $ranNumber         = $general->generateRandomString(12);
    // $fileName          = $ranNumber . "." . $extension;
    $fileName          = $_POST['fileName'] . "." . $extension;


    if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results")) {
        mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results", 0777, true);
    }
    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName);
if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {
        //$file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        //$mime_type = $file_info->buffer(file_get_contents($resultFile)); // e.g. gives "image/jpeg"

        $objPHPExcel = IOFactory::load(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData   = $objPHPExcel->getActiveSheet();

        $bquery    = "SELECT MAX(batch_code_key) FROM batch_details";
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

            if ($rowIndex < $skipTillRow) {
                continue;
            }


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




            if ($sampleCode == "") {
                continue;
            }


            /*
            $d=explode(" ",$row[$testingDateCol]);
            $testingDate=str_replace("/","-",$d[0],$checked);
            $testingDate = date("Y-m-d H:i:s", \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($testingDate));
            $dt=explode("/",$d[0]);
            if(count($dt) > 1){
                // Date time in the provided Roche Sample file is in this format : 2016/09/20 12:22:03
                $testingDate = DateTime::createFromFormat($dateFormat, $row[$testingDateCol])->format('Y-m-d H:i');
            }else{
                // Date time in the provided Roche Sample file is in this format : 20-09-16 12:22
                $testingDate = DateTime::createFromFormat($dateFormat, $row[$testingDateCol])->format('Y-m-d H:i');
            }  
            */


            // $testingDate = date('Y-m-d H:i', strtotime($row[$testingDateCol]));

            $testingDate = null;
            $testingDateObject = DateTimeImmutable::createFromFormat('!'.$dateFormat, $row[$testingDateCol]);
            $errors = DateTimeImmutable::getLastErrors();
            if (empty($errors['warning_count']) && empty($errors['error_count']) && !empty($testingDateObject)) {
                $testingDate = $testingDateObject->format('Y-m-d H:i');
            }

            $vlResult = trim($row[$absValCol]);

            if (!empty($vlResult)) {
                if (strpos($vlResult, 'E') !== false) {
                    if (strpos($vlResult, '< 2.00E+1') !== false) {
                        $vlResult = "< 20";
                        $txtVal = $absVal = trim($vlResult);
                        $logVal = "";
                    } else {
                        $resInNumberFormat = number_format($vlResult, 0, '', '');
                        if ($resInNumberFormat > 0) {
                            $absVal = $resInNumberFormat;
                            $absDecimalVal = (float) trim($resInNumberFormat);
                            $logVal = round(log10($absDecimalVal), 2);
                            $txtVal = "";
                        } else {
                            $absVal = $txtVal = trim($vlResult);
                            $absDecimalVal = $logVal = "";
                        }
                    }
                } else {
                    $vlResult = (float)$row[$absValCol];
                    if ($vlResult > 0) {
                        $absVal = trim($row[$absValCol]);
                        $absDecimalVal = $vlResult;
                        $logVal = round(log10($absDecimalVal), 4);
                        $txtVal = "";
                    } else {
                        $logVal = $absDecimalVal = $absVal = "";
                        $txtVal = trim($row[$absValCol]);
                    }
                }
            }



            $infoFromFile[$sampleCode] = [
                "sampleCode" => $sampleCode,
                "logVal" => trim($logVal),
                "absVal" => $absVal,
                "absDecimalVal" => $absDecimalVal,
                "txtVal" => $txtVal,
                "resultFlag" => $resultFlag,
                "testingDate" => $testingDate,
                "sampleType" => $sampleType,
                "batchCode" => $batchCode
            ];


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
                'result_status' => 6,
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

            $query    = "SELECT facility_id,vl_sample_id,result,result_value_log,result_value_absolute,result_value_text,result_value_absolute_decimal FROM form_vl WHERE result_printed_datetime is null AND sample_code='" . $sampleCode . "'";
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

    $eventType = 'result-import';
    $action = $_SESSION['userName'] . ' imported test results for Roche VL';
    $resource  = 'import-result';
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

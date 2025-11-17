<?php

use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;
use PhpOffice\PhpSpreadsheet\IOFactory;

try {

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var TestResultsService $testResultsService */
    $testResultsService = ContainerRegistry::get(TestResultsService::class);

    $testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'vl');

    // $_SESSION['controllertrack'] = $testResultsService->getMaxIDForHoldingSamples();

    $allowedExtensions = ['xls', 'xlsx', 'csv'];
    if (
        isset($_FILES['resultFile']) && $_FILES['resultFile']['error'] !== UPLOAD_ERR_OK
        || $_FILES['resultFile']['size'] <= 0
    ) {
        throw new SystemException('Please select a file to upload', 400);
    }

    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', htmlspecialchars(basename((string) $_FILES['resultFile']['name'])));

    $ranNumber = MiscUtility::generateRandomString(12);
    $extension = MiscUtility::getFileExtension($fileName);
    $fileName = $ranNumber . "." . $extension;



    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {
        //$file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        //$mime_type = $file_info->buffer(file_get_contents($resultFile)); // e.g. gives "image/jpeg"

        $objPHPExcel = IOFactory::load(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData = $objPHPExcel->getActiveSheet();

        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);


        //echo "<pre>";var_dump($sheetData);echo "</pre>";

        $m = 0;
        $skipTillRow = 2;

        $sampleIdCol = 'B';
        $sampleIdRow = '2';
        $logValCol = 'F';
        $logValRow = '2';
        $absValCol = 'E';
        $absValRow = '2';
        $txtValCol = 'G';
        $txtValRow = '2';
        $testingDateCol = 'D';
        $testingDateRow = '2';
        $logAndAbsoluteValInSameCol = 'no';
        $sampleTypeCol = '';
        $flagCol = '';
        $lotNumberCol = '';
        $reviewByCol = 'H';
        $lotExpirationDateCol = '';

        $testingPlatformCol = 'I';


        foreach ($sheetData as $rowIndex => $row) {
            if ($rowIndex < $skipTillRow) {
                continue;
            }

            if (!str_contains(strtolower((string)$row[$testingPlatformCol]), 'viral')) {
                continue;
            }


            $sampleCode = "";
            $sampleType = "";
            $absDecimalVal = "";
            $absVal = "";
            $logVal = "";
            $txtVal = null;
            $resultFlag = "";
            $testingDate = "";
            $lotNumberVal = "";
            $reviewBy = "";
            $lotExpirationDateVal = null;

            $sampleCode = $row[$sampleIdCol];
            $sampleType = "S";
            $resultFlag = "";
            $reviewBy = $row[$reviewByCol];

            $testingDate = date('Y-m-d H:i', strtotime((string) $row[$testingDateCol]));

            if (trim((string) $row[$absValCol]) !== "") {
                $resVal = (float) $row[$absValCol];
                if ($resVal > 0) {
                    $absVal = $resVal;
                    $absDecimalVal = $resVal;
                    if ($row[$logValCol] != null && trim((string) $row[$logValCol]) !== "") {
                        $logVal = (float) $row[$logValCol];
                    } else {
                        $logVal = round(log10($absDecimalVal), 4);
                    }
                    $txtVal = null;
                } else {
                    $absDecimalVal = $absVal = "";
                    $logVal = "";
                    $txtVal = trim((string) $row[$absValCol]);
                }
            }



            if ($sampleCode == "") {
                $sampleCode = $sampleType . $m;
            }
            //   continue;

            $infoFromFile[$sampleCode] = ["sampleCode" => $sampleCode, "logVal" => $logVal, "absVal" => $absVal, "absDecimalVal" => $absDecimalVal, "txtVal" => $txtVal, "resultFlag" => $resultFlag, "testingDate" => $testingDate, "sampleType" => $sampleType, "lotNumber" => $lotNumberVal, "lotExpirationDate" => $lotExpirationDateVal, "reviewBy" => $reviewBy];

            $m++;
        }


        //echo "<pre>";var_dump($infoFromFile);echo "</pre>";die;
        $inc = 0;
        $refno = 0;
        foreach ($infoFromFile as $sampleCode => $d) {
            if ($d['sampleCode'] == $d['sampleType'] . $inc) {
                $d['sampleCode'] = '';
            }
            if ($d['sampleType'] == 'S' || $d['sampleType'] == 's') {
                $refno += 1;
            }
            $data = ['module' => 'vl', 'lab_id' => base64_decode((string) $_POST['labId']), 'vl_test_platform' => $_POST['vltestPlatform'], 'import_machine_name' => $_POST['configMachineName'], 'result_reviewed_by' => $_SESSION['userId'], 'sample_code' => $d['sampleCode'], 'result_value_log' => $d['logVal'], 'sample_type' => $d['sampleType'], 'result_value_absolute' => $d['absVal'], 'result_value_text' => $d['txtVal'], 'result_value_absolute_decimal' => $d['absDecimalVal'], 'sample_tested_datetime' => $d['testingDate'], 'result_status' => RECEIVED_AT_TESTING_LAB, 'import_machine_file_name' => $fileName, 'lab_tech_comments' => $d['resultFlag'], 'lot_number' => $d['lotNumber'], 'lot_expiration_date' => $d['lotExpirationDate']];

            //echo "<pre>";var_dump($data);continue;
            if ($d['absVal'] != "") {
                $data['result'] = $d['absVal'];
            } elseif ($d['logVal'] != "") {
                $data['result'] = $d['logVal'];
            } elseif ($d['txtVal'] != "") {
                $data['result'] = $d['txtVal'];
            } else {
                $data['result'] = "";
            }
            //get username
            if (!empty($d['reviewBy'])) {

                /** @var UsersService $usersService */
                $usersService = ContainerRegistry::get(UsersService::class);
                $data['sample_review_by'] = $usersService->getOrCreateUser($d['reviewBy']);
            }

            $query = "SELECT facility_id,vl_sample_id,result,result_value_log,result_value_absolute,result_value_text,result_value_absolute_decimal from form_vl where result_printed_datetime is null AND sample_code='" . $sampleCode . "'";
            $vlResult = $db->rawQueryOne($query);
            //insert sample controls
            $scQuery = "select r_sample_control_name from r_sample_controls where r_sample_control_name='" . trim((string) $d['sampleType']) . "'";
            $scResult = $db->rawQuery($scQuery);
            if (!$scResult) {
                $scData = ['r_sample_control_name' => trim((string) $d['sampleType'])];
                $scId = $db->insert("r_sample_controls", $scData);
            }
            if (!empty($vlResult) && !empty($sampleCode)) {
                if (!empty($vlResult['result'])) {
                    $data['sample_details'] = 'Result already exists';
                }
                $data['facility_id'] = $vlResult['facility_id'];
            } else {
                $data['sample_details'] = 'New Sample';
            }

            if ($sampleCode != ''  || $sampleType != '' || $logVal != '' || $absVal != '' || $absDecimalVal != '') {
                $data['result_imported_datetime'] = DateUtility::getCurrentDateTime();
                $data['imported_by'] = $_SESSION['userId'];
                $id = $db->insert("temp_sample_import", $data);
            }
            $inc++;
        }
        $_SESSION['refno'] = $refno;
    }

    $_SESSION['alertMsg'] = "Results imported successfully";
    //Add event log


    //Add event log
    $eventType = 'result-import';
    $action = $_SESSION['userName'] . ' imported test results for Roche VL';
    $resource = 'import-result';
    $general->activityLog($eventType, $action, $resource);

    header("Location:/import-result/imported-results.php?t=$type");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

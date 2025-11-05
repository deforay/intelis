<?php

// For Roche Cobas test results import for EID
// File gets called in import-file-helper.php based on the selected instrument type

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {
    $dateFormat = (!empty($_POST['dateFormat'])) ? $_POST['dateFormat'] : 'd/m/Y H:i';

    /** @var TestResultsService $testResultsService */
    $testResultsService = ContainerRegistry::get(TestResultsService::class);

    $testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'eid');

    // $_SESSION['controllertrack'] = $testResultsService->getMaxIDForHoldingSamples();

    $allowedExtensions = ['txt'];
    if (
        isset($_FILES['resultFile']) && $_FILES['resultFile']['error'] !== UPLOAD_ERR_OK
        || $_FILES['resultFile']['size'] <= 0
    ) {
        throw new SystemException('Please select a file to upload', 400);
    }

    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', htmlspecialchars(basename((string) $_FILES['resultFile']['name'])));

    $extension = MiscUtility::getFileExtension($fileName);
    $fileName = $_POST['fileName'] . "-" . MiscUtility::generateRandomString(12) . "." . $extension;



    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {

        $file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        $mime_type = $file_info->buffer(file_get_contents($resultFile)); // e.g. gives "image/jpeg"

        $m = 1;
        $skipTillRow = 5;

        $testDateCol = "B";
        $sampleIdCol = "C";
        $sampleTypeCol = "D";
        $resultCol = "G";


        $flagCol = 10;


        $lotNumberCol = 12;
        $reviewByCol = '';
        $lotExpirationDateCol = 13;

        $spreadsheet = IOFactory::load(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData = $spreadsheet->getActiveSheet();
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $infoFromFile = [];
        $testDateRow = "";


        $row = 1;

        foreach ($sheetData as $rowIndex => $rowData) {

            if ($rowIndex < $skipTillRow) {
                continue;
            }


            $num = count($rowData ?? []);
            $row++;

            $sampleCode = "";
            $sampleType = "";
            $absDecimalVal = "";
            $absVal = "";
            $logVal = "";
            $txtVal = null;
            $resultFlag = "";

            $sampleCode = $rowData[$sampleIdCol];

            if ($sampleCode == "SAMPLE ID" || $sampleCode == "") {
                continue;
            }

            $sampleType = $rowData[$sampleTypeCol];

            $resultFlag = $rowData[$flagCol];
            //$reviewBy = $rowData[$reviewByCol];

            $result = $absVal = $logVal = $absDecimalVal = $txtVal = '';
            $resultInLowerCase = strtolower((string)$rowData[$resultCol]);
            if (str_contains($resultInLowerCase, 'not detected')) {
                $result = 'negative';
            } elseif ((str_contains($resultInLowerCase, 'detected')) || (str_contains(strtolower((string)$rowData[$resultCol]), 'passed'))) {
                $result = 'positive';
            } else {
                $result = $resultInLowerCase;
            }


            $lotNumberVal = $rowData[$lotNumberCol];
            if (trim((string) $rowData[$lotExpirationDateCol]) != '') {
                $timestamp = DateTime::createFromFormat('!' . $dateFormat, $rowData[$lotExpirationDateCol]);
                if (!empty($timestamp)) {
                    $timestamp = $timestamp->getTimestamp();
                    $lotExpirationDateVal = date('Y-m-d H:i', $timestamp);
                } else {
                    $lotExpirationDateVal = null;
                }
            }

            $sampleType = $rowData[$sampleTypeCol];
            if ($sampleType == 'Patient') {
                $sampleType = 'S';
            } else if ($sampleType == 'Control') {

                if ($sampleCode == 'HIV_HIPOS') {
                    $sampleType = 'HPC';
                    $sampleCode = "$sampleCode-$lotNumberVal";
                } else if ($sampleCode == 'HIV_LOPOS') {
                    $sampleType = 'LPC';
                    $sampleCode = "$sampleCode-$lotNumberVal";
                } else if ($sampleCode == 'HIV_NEG') {
                    $sampleType = 'NC';
                    $sampleCode = "$sampleCode-$lotNumberVal";
                }
            }



            if ($sampleCode == "") {
                $sampleCode = $sampleType . $m;
            }

            if (!isset($infoFromFile[$sampleCode])) {
                $infoFromFile[$sampleCode] = [
                    "sampleCode" => $sampleCode,
                    "resultFlag" => $resultFlag,
                    "testingDate" => $testingDate,
                    "sampleType" => $sampleType,
                    "lotNumber" => $lotNumberVal,
                    "result" => $result,
                    "lotExpirationDate" => $lotExpirationDateVal,
                ];
            } else {
                if (isset($logVal) && $logVal != "") {
                    $infoFromFile[$sampleCode]['logVal'] = $logVal;
                }
            }

            $m++;
        }



        $inc = 0;
        foreach ($infoFromFile as $sampleCode => $d) {
            if ($d['sampleCode'] == $d['sampleType'] . $inc) {
                $d['sampleCode'] = '';
            }
            $data = [
                'module' => 'eid',
                'lab_id' => base64_decode((string) $_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'result_value_log' => null,
                'sample_type' => $d['sampleType'],
                'result_value_absolute' => null,
                'result_value_text' => null,
                'result_value_absolute_decimal' => null,
                'sample_tested_datetime' => $d['testingDate'],
                'result_status' => SAMPLE_STATUS\PENDING_APPROVAL,
                'import_machine_file_name' => $fileName,
                'lab_tech_comments' => $d['resultFlag'],
                'lot_number' => $d['lotNumber'],
                'lot_expiration_date' => $d['lotExpirationDate'],
                'result' => $d['result'],
            ];
            //get username
            if (!empty($d['reviewBy'])) {

                /** @var UsersService $usersService */
                $usersService = ContainerRegistry::get(UsersService::class);
                $data['sample_review_by'] = $usersService->getOrCreateUser($d['reviewBy']);
            }

            $query = "SELECT facility_id,eid_id,result FROM form_eid WHERE sample_code='$sampleCode'";
            $eidResult = $db->rawQueryOne($query);
            //insert sample controls
            $scQuery = "SELECT r_sample_control_name FROM r_sample_controls WHERE r_sample_control_name='" . trim((string) $d['sampleType']) . "'";
            $scResult = $db->rawQuery($scQuery);
            if (!$scResult) {
                $scData = ['r_sample_control_name' => trim((string) $d['sampleType'])];
                $scId = $db->insert("r_sample_controls", $scData);
            }
            if (!empty($eidResult) && !empty($sampleCode)) {
                if (!empty($eidResult['result'])) {
                    $data['sample_details'] = 'Result already exists';
                }
                $data['facility_id'] = $eidResult['facility_id'];
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
    }

    $_SESSION['alertMsg'] = "Results imported successfully";
    //Add event log
    $eventType = 'import';
    $action = $_SESSION['userName'] . ' imported a new test result with the sample id ' . $sampleCode;
    $resource = 'import-results-manually';
    $general->activityLog($eventType, $action, $resource);

    header("Location:/import-result/imported-results.php?t=$type");
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

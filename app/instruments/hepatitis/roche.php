<?php

use App\Registries\AppRegistry;
use App\Services\BatchService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {
    /** @var TestResultsService $testResultsService */
    $testResultsService = ContainerRegistry::get(TestResultsService::class);

    $testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'hepatitis');

    $_SESSION['controllertrack'] = $testResultsService->getMaxIDForHoldingSamples();

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

    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', htmlspecialchars(basename((string) $_FILES['resultFile']['name'])));
    $fileName = str_replace(" ", "-", $fileName) . "-" . $general->generateRandomString(12);
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName = $_POST['fileName'] . "." . $extension;

    // $fileName          = $ranNumber . "." . $extension;



    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {
        //$file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        //$mime_type = $file_info->buffer(file_get_contents($resultFile)); // e.g. gives "image/jpeg"

        $objPHPExcel = IOFactory::load(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData = $objPHPExcel->getActiveSheet();

        /** @var BatchService $batchService */
        $batchService = ContainerRegistry::get(BatchService::class);
        [$maxBatchCodeKey, $newBatchCode] = $batchService->createBatchCode();

        $sheetData = $sheetData->toArray(null, true, true, true);
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

            $sampleCode = "";
            $batchCode = "";
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
            $sampleType = $row[$sampleTypeCol];
            $batchCode = $row[$batchCodeCol];
            $resultFlag = $row[$flagCol];
            $reviewBy = $row[$reviewByCol];

            if ($row[$testingDateCol] != '') {
                $alterDateTime = explode(" ", (string) $row[$testingDateCol]);
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

            if (trim((string) $row[$absValCol]) != "") {
                $resVal = explode("(", (string) $row[$absValCol]);
                if (count($resVal) == 2) {

                    if (str_contains($resVal[0], "<")) {
                        $resVal[0] = str_replace("<", "", $resVal[0]);
                        $absDecimalVal = (float) trim($resVal[0]);
                        $absVal = "< " . (float) trim($resVal[0]);
                    } else if (str_contains($resVal[0], ">")) {
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
                    $txtVal = trim((string) $row[$absValCol]);
                    if ($txtVal == 'Invalid') {
                        $resultFlag = trim($txtVal);
                    }
                }
            }

            $lotNumberVal = $row[$lotNumberCol];
            if (trim((string) $row[$lotExpirationDateCol]) != '') {
                $alterDate = str_replace("/", "-", (string) $row[$lotExpirationDateCol]);
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
                'module' => 'hepatitis',
                'lab_id' => base64_decode((string) $_POST['labId']),
                'hepatitis_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'sample_type' => $d['sampleType'],
                'sample_tested_datetime' => $d['testingDate'],
                'result_status' => '6',
                'import_machine_file_name' => $fileName,
                'lab_tech_comments' => $d['resultFlag'],
                'lot_number' => $d['lotNumber'],
                'lot_expiration_date' => $d['lotExpirationDate']
            );


            if ($batchCode == '' || empty($batchCode)) {
                $data['batch_code'] = $newBatchCode;
                $data['batch_code_key'] = $maxBatchCodeKey;
            } else {
                $data['batch_code'] = $batchCode;
            }
            //get username
            if (!empty($d['reviewBy'])) {

                /** @var UsersService $usersService */
                $usersService = ContainerRegistry::get(UsersService::class);
                $data['sample_review_by'] = $usersService->getOrCreateUser($d['reviewBy']);
            }

            $query = "SELECT facility_id,
                            hepatitis_id,
                            hcv_vl_count,
                            hbv_vl_count,
                            hepatitis_test_type,
                            result_status
                        FROM form_hepatitis
                        WHERE sample_code= ?";
            $hepResult = $db->rawQueryOne($query, [$sampleCode]);


            if ($d['absVal'] != "") {
                $data['result'] = $d['absVal'];
            } elseif ($d['logVal'] != "") {
                $data['result'] = $d['logVal'];
            } elseif ($d['txtVal'] != "") {
                $data['result'] = $d['txtVal'];
            } else {
                $data['result'] = null;
            }


            //insert sample controls
            $scQuery = "SELECT r_sample_control_name
                        FROM r_sample_controls
                        WHERE r_sample_control_name= ?";
            $scResult = $db->rawQueryOne($scQuery, [trim((string) $d['sampleType'])]);
            if (!$scResult) {
                $scData = array('r_sample_control_name' => trim((string) $d['sampleType']));
                $scId = $db->insert("r_sample_controls", $scData);
            }
            if (!empty($hepResult) && !empty($sampleCode)) {
                if ($hepResult['hcv_vl_count'] != '' || $hepResult['hbv_vl_count'] != '') {
                    $data['sample_details'] = 'Result already exists';
                } else {
                    if ($hepResult['result_status'] != '') {
                        $data['result_status'] = $hepResult['result_status'];
                    } else {
                        $data['result_status'] = '7';
                    }
                }
                $data['facility_id'] = $hepResult['facility_id'];
            } else {
                $data['result_status'] = '7';
                $data['sample_details'] = 'New Sample';
            }

            if ($sampleCode != '' || $batchCode != '' || $sampleType != '' || $logVal != '' || $absVal != '' || $absDecimalVal != '') {
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
    $eventType = 'import';
    $action = $_SESSION['userName'] . ' imported a new test result with the sample id ' . $sampleCode;
    $resource = 'import-result';
    $general->activityLog($eventType, $action, $resource);

    header("Location:/import-result/imported-results.php?t=$type");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

<?php

// File gets called in import-file-helper.php based on the selected instrument type
use Laminas\Diactoros\ServerRequest;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;

try {
    // Sanitized values from $request object
    /** @var ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    /** @var TestResultsService $testResultsService */
    $testResultsService = ContainerRegistry::get(TestResultsService::class);

    $dateFormat = (empty($_POST['dateFormat'])) ? 'd/m/Y H:i' : $_POST['dateFormat'];

    $testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'vl');

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
        $skipTillRow = 23;

        $sampleIdCol = 1;
        $sampleTypeCol = 2;
        $resultCol = 5;
        $txtValCol = 6;

        $flagCol = 10;
        $testDateCol = 11;

        $lotNumberCol = 12;
        $reviewByCol = '';
        $lotExpirationDateCol = 13;

        $dateFormat = 'd/m/Y';

        if (str_contains($mime_type, 'text/plain')) {
            $infoFromFile = [];
            $testDateRow = "";
            $skip = 23;

            $row = 1;
            if (($handle = fopen(realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName), "r")) !== false) {
                while (($sheetData = fgetcsv($handle, 10000, "\t")) !== false) {
                    $row++;
                    if ($row < $skip) {
                        if ($row == 8) {
                            $testingDateArray = $testResultsService->abbottDateFormatter($sheetData[1], $sheetData[2]);
                            $dateFormat = $testingDateArray['dateFormat'];
                            $testingDate = $testingDateArray['testingDate'];
                        }
                        continue;
                    }

                    $num = count($sheetData);
                    $sampleCode = "";

                    $sampleType = "";
                    $absDecimalVal = "";
                    $absVal = "";
                    $logVal = "";
                    $txtVal = null;
                    $resultFlag = "";

                    $sampleCode = $sheetData[$sampleIdCol];
                    $sampleType = $sheetData[$sampleTypeCol];

                    $resultFlag = $sheetData[$flagCol];
                    //$reviewBy = $sheetData[$reviewByCol];


                    if ($sheetData[$resultCol] == "" || $sheetData[$resultCol] == null) {
                        $absVal = $absDecimalVal = $logVal = null;
                        $result = $txtVal = "Failed";
                        $resultFlag = $sheetData[$flagCol] ?? '';
                    } elseif (str_contains((string)$sheetData[$resultCol], 'Log')) {
                        $sheetData[$resultCol] = str_replace(",", ".", (string) $sheetData[$resultCol]); // in case they are using european decimal format
                        $logVal = ((float) filter_var($sheetData[$resultCol], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                        $absDecimalVal = round(10 ** $logVal, 2);
                        if (str_contains($sheetData[$resultCol], "<")) {
                            $txtVal = $absVal = "< " . trim($absDecimalVal);
                        } else {
                            $txtVal = null;
                            $absVal = $absDecimalVal;
                        }
                    } elseif (str_contains((string)$sheetData[$resultCol], 'Copies')) {
                        if (str_contains((string)$sheetData[$resultCol], '<') || $sheetData[$resultCol] == '839 Copies / mL') {
                            $txtVal = "Below Detection Level";
                            $logVal = $absDecimalVal = $absVal = $resultFlag = "";
                        } else {
                            $absVal = $absDecimalVal = abs((int) filter_var($sheetData[$resultCol], FILTER_SANITIZE_NUMBER_INT));
                        }
                    } elseif (str_contains((string)$sheetData[$resultCol], 'IU/mL')) {
                        $absVal = $absDecimalVal = abs((int) filter_var($sheetData[$resultCol], FILTER_SANITIZE_NUMBER_INT));
                    } elseif (str_contains(strtolower((string)$sheetData[$resultCol]), 'not detected') || strtolower((string) $sheetData[$resultCol]) === 'target not detected') {
                        $txtVal = "Below Detection Level";
                        $resultFlag = "";
                        $absVal = "";
                        $logVal = "";
                    } else {
                        $txtVal = $sheetData[$resultCol + 1];
                        $resultFlag = "";
                        $absVal = "";
                        $logVal = "";
                    }


                    $lotNumberVal = $sheetData[$lotNumberCol];
                    if (trim((string) $sheetData[$lotExpirationDateCol]) !== '') {
                        $timestamp = DateTime::createFromFormat("!$dateFormat", $sheetData[$lotExpirationDateCol]);
                        if (!empty($timestamp)) {
                            $timestamp = $timestamp->getTimestamp();
                            $lotExpirationDateVal = date('Y-m-d H:i', $timestamp);
                        } else {
                            $lotExpirationDateVal = null;
                        }
                    }

                    $sampleType = $sheetData[$sampleTypeCol];
                    if ($sampleType == 'Patient') {
                        $sampleType = 'S';
                    } elseif ($sampleType == 'Control') {

                        if ($sampleCode == 'HIV_HIPOS') {
                            $sampleType = 'HPC';
                            $sampleCode = "$sampleCode-$lotNumberVal";
                        } elseif ($sampleCode == 'HIV_LOPOS') {
                            $sampleType = 'LPC';
                            $sampleCode = $sampleCode . '-' . $lotNumberVal;
                        } elseif ($sampleCode == 'HIV_NEG') {
                            $sampleType = 'NC';
                            $sampleCode = $sampleCode . '-' . $lotNumberVal;
                        }
                    }




                    if ($sampleCode == "") {
                        $sampleCode = $sampleType . $m;
                    }

                    if (!isset($infoFromFile[$sampleCode])) {
                        $infoFromFile[$sampleCode] = [
                            "sampleCode" => $sampleCode,
                            "logVal" => $logVal,
                            "absVal" => $absVal,
                            "absDecimalVal" => $absDecimalVal,
                            "txtVal" => $txtVal,
                            "resultFlag" => $resultFlag,
                            "testingDate" => $testingDate,
                            "sampleType" => $sampleType,
                            "lotNumber" => $lotNumberVal,
                            "lotExpirationDate" => $lotExpirationDateVal,
                        ];
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
            $data = [
                'module' => 'vl',
                'lab_id' => base64_decode((string) $_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'sample_type' => $d['sampleType'],
                'result_value_log' => $d['logVal'],
                'result_value_absolute' => $d['absVal'],
                'result_value_text' => $d['txtVal'],
                'result_value_absolute_decimal' => $d['absDecimalVal'],
                'sample_tested_datetime' => $d['testingDate'],
                'result_status' => RECEIVED_AT_TESTING_LAB,
                'import_machine_file_name' => $fileName,
                'lab_tech_comments' => $d['resultFlag'],
                'lot_number' => $d['lotNumber'],
                'lot_expiration_date' => $d['lotExpirationDate'],
            ];

            //echo "<pre>";var_dump($data);continue;
            if ($d['txtVal'] != "") {
                $data['result'] = $d['txtVal'];
            } elseif ($d['absVal'] != "") {
                $data['result'] = $d['absVal'];
            } elseif ($d['logVal'] != "") {
                $data['result'] = $d['logVal'];
            } else {
                $data['result'] = "";
            }

            //get username
            if (!empty($d['reviewBy'])) {

                /** @var UsersService $usersService */
                $usersService = ContainerRegistry::get(UsersService::class);
                $data['sample_review_by'] = $usersService->getOrCreateUser($d['reviewBy']);
            }

            $query = "SELECT facility_id,vl_sample_id,result,result_value_log,result_value_absolute,result_value_text,result_value_absolute_decimal FROM form_vl WHERE result_printed_datetime is null AND sample_code='" . $sampleCode . "'";
            $vlResult = $db->rawQueryOne($query);
            //insert sample controls
            $scQuery = "SELECT r_sample_control_name FROM r_sample_controls where r_sample_control_name='" . trim((string) $d['sampleType']) . "'";
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
    }

    $_SESSION['alertMsg'] = "Results imported successfully";
    //Add event log
    $eventType = 'import';
    $action = $_SESSION['userName'] . ' imported a new test result with the sample id ' . $sampleCode;
    $resource = 'import-results-manually';
    $general->activityLog($eventType, $action, $resource);

    header("Location:/import-result/imported-results.php?t=$type");
} catch (Exception $exc) {
    LoggerUtility::logError($exc->getMessage());
}

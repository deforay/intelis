<?php

// File gets called in import-file-helper.php based on the selected instrument type
use Laminas\Diactoros\ServerRequest;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    // Sanitized values from $request object
    /** @var ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    /** @var TestResultsService $testResultsService */
    $testResultsService = ContainerRegistry::get(TestResultsService::class);

    $testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'hepatitis');

    // $_SESSION['controllertrack'] = $testResultsService->getMaxIDForHoldingSamples();

    $dateFormat = (empty($_POST['dateFormat'])) ? 'd/m/Y H:i' : $_POST['dateFormat'];

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

        if (str_contains($mime_type, 'text/plain')) {
            $infoFromFile = [];
            $testDateRow = "";
            $skip = 23;

            $row = 1;
            if (($handle = fopen(realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName), "r")) !== false) {
                while (($sheetData = fgetcsv($handle, 10000, "\t")) !== false) {
                    $num = count($sheetData);
                    $row++;
                    if ($row < $skip) {
                        if ($row == 8) {
                            $testingDateArray = $testResultsService->abbottDateFormatter($sheetData[1], $sheetData[2]);
                            $dateFormat = $testingDateArray['dateFormat'];
                            $testingDate = $testingDateArray['testingDate'];
                        }
                        continue;
                    }
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

                    if (str_contains((string)$sheetData[$resultCol], 'Log')) {

                        $sheetData[$resultCol] = str_replace(",", ".", (string) $sheetData[$resultCol]); // in case they are using european decimal format
                        $logVal = ((float) filter_var($sheetData[$resultCol], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                        $absDecimalVal = round(10 ** $logVal, 2);


                        if (str_contains($sheetData[$resultCol], "<")) {
                            if ($sheetData[$resultCol] === "< INF") {
                                $txtVal = $absVal = $absDecimalVal = 839;
                                $logVal = round(log10($absDecimalVal), 2);
                            } else {
                                $txtVal = $absVal = "< " . trim($absDecimalVal);
                                $logVal = $absDecimalVal = $resultFlag = "";
                            }
                        } elseif (str_contains($sheetData[$resultCol], ">")) {
                            $txtVal = $absVal = "> " . trim($absDecimalVal);
                        } else {
                            $txtVal = null;
                            $absVal = $absDecimalVal;
                        }
                    } elseif (str_contains((string)$sheetData[$resultCol], 'Copies')) {
                        $absVal = $absDecimalVal = abs((int) filter_var($sheetData[$resultCol], FILTER_SANITIZE_NUMBER_INT));
                        if (str_contains((string)$sheetData[$resultCol], '<')) {
                            if ($sheetData[$resultCol] == "< INF") {
                                $txtVal = $absVal = $absDecimalVal = 839;
                                $logVal = round(log10($absDecimalVal), 2);
                            } else {
                                $txtVal = $absVal = "< " . trim($absDecimalVal);
                                $logVal = $absDecimalVal = $resultFlag = "";
                            }
                        } elseif (str_contains((string)$sheetData[$resultCol], '>')) {
                            $txtVal = $absVal = "> " . trim($absDecimalVal);
                            $logVal = $absDecimalVal = $resultFlag = "";
                        } else {
                            $logVal = round(log10($absDecimalVal), 2);
                            $absVal = $absDecimalVal;
                        }
                    } elseif (str_contains((string)$sheetData[$resultCol], 'IU/mL')) {
                        $absVal = $absDecimalVal = abs((int) filter_var($sheetData[$resultCol], FILTER_SANITIZE_NUMBER_INT));
                    } elseif (str_contains(strtolower((string)$sheetData[$resultCol]), 'not detected') || strtolower((string) $sheetData[$resultCol]) === 'target not detected') {
                        $txtVal = "Target Not Detected";
                        $resultFlag = "";
                        $absVal = "";
                        $logVal = "";
                    } elseif ($sheetData[$resultCol] == "" || $sheetData[$resultCol] == null) {
                        //$txtVal =  $sheetData[$flagCol];
                        $txtVal = "Failed";
                        $resultFlag = $sheetData[$flagCol];
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
                            $sampleCode = $sampleCode . '-' . $lotNumberVal;
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
                        $infoFromFile[$sampleCode] = ["sampleCode" => $sampleCode, "logVal" => $logVal, "absVal" => $absVal, "absDecimalVal" => $absDecimalVal, "txtVal" => $txtVal, "resultFlag" => $resultFlag, "testingDate" => $testingDate, "sampleType" => $sampleType, "lotNumber" => $lotNumberVal, "lotExpirationDate" => $lotExpirationDateVal];
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
            $data = ['module' => 'hepatitis', 'lab_id' => base64_decode((string) $_POST['labId']), 'vl_test_platform' => $_POST['vltestPlatform'], 'import_machine_name' => $_POST['configMachineName'], 'result_reviewed_by' => $_SESSION['userId'], 'sample_code' => $d['sampleCode'], 'result_value_log' => $d['logVal'], 'sample_type' => $d['sampleType'], 'result_value_absolute' => $d['absVal'], 'result_value_text' => $d['txtVal'], 'result_value_absolute_decimal' => $d['absDecimalVal'], 'sample_tested_datetime' => $d['testingDate'], 'result_status' => RECEIVED_AT_TESTING_LAB, 'import_machine_file_name' => $fileName, 'lab_tech_comments' => $d['resultFlag'], 'lot_number' => $d['lotNumber'], 'lot_expiration_date' => $d['lotExpirationDate']];

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

            $query = "SELECT facility_id,
                            hepatitis_id,
                            hcv_vl_count,
                            hbv_vl_count,
                            hepatitis_test_type,
                            result_status
                        FROM form_hepatitis
                        WHERE sample_code= ?";
            $hepResult = $db->rawQueryOne($query, [$sampleCode]);
            //insert sample controls
            $scQuery = "SELECT r_sample_control_name
                        FROM r_sample_controls
                        WHERE r_sample_control_name= ?";
            $scResult = $db->rawQueryOne($scQuery, [trim((string) $d['sampleType'])]);
            if (!$scResult) {
                $scData = ['r_sample_control_name' => trim((string) $d['sampleType'])];
                $scId = $db->insert("r_sample_controls", $scData);
            }
            if (!empty($hepResult) && !empty($sampleCode)) {
                if ($hepResult['hcv_vl_count'] != '' || $hepResult['hbv_vl_count'] != '') {
                    $data['sample_details'] = 'Result already exists';
                }
                $data['facility_id'] = $hepResult['facility_id'];
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
    error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    error_log($exc->getMessage());
}

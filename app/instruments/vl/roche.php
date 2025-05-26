<?php

// For Roche Cobas Test results import
// File gets called in import-file-helper.php based on the selected instrument type

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;

try {

    // Sanitized values from $request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $tableName = $_POST['tableName'];

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    $dateFormat = (!empty($_POST['dateFormat'])) ? $_POST['dateFormat'] : 'd/m/Y H:i';

    /** @var TestResultsService $testResultsService */
    $testResultsService = ContainerRegistry::get(TestResultsService::class);

    $testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'vl');

    // $_SESSION['controllertrack'] = $testResultsService->getMaxIDForHoldingSamples();

    $allowedExtensions = [
        'xls',
        'xlsx',
        'csv'
    ];
    if (
        isset($_FILES['resultFile']) && $_FILES['resultFile']['error'] !== UPLOAD_ERR_OK
        || $_FILES['resultFile']['size'] <= 0
    ) {
        throw new SystemException('Please select a file to upload', 400);
    }

    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', htmlspecialchars(basename((string) $_FILES['resultFile']['name'])));
    $fileName          = str_replace(" ", "-", $fileName) . "-" . MiscUtility::generateRandomString(12);
    $extension         = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName          = $_POST['fileName'] . "." . $extension;



    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {
        //$file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        //$mime_type = $file_info->buffer(file_get_contents($resultFile)); // e.g. gives "image/jpeg"

        $objPHPExcel = IOFactory::load(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData   = $objPHPExcel->getActiveSheet();



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
        $flagCol = 'K';
        //$flagRow = '2';

        foreach ($sheetData as $rowIndex => $row) {

            if ($rowIndex < $skipTillRow) {
                continue;
            }


            $sampleCode    = "";
            $sampleType    = "";
            $absDecimalVal = "";
            $absVal        = "";
            $logVal        = "";
            $txtVal        = "";
            $resultFlag    = "";
            $testingDate   = "";


            $sampleCode = $row[$sampleIdCol];
            $sampleType = $row[$sampleTypeCol];

            $resultFlag = $row[$flagCol];




            if ($sampleCode == "") {
                continue;
            }


            // $testingDate = date('Y-m-d H:i', strtotime($row[$testingDateCol]));

            $testingDate = null;
            $testingDateObject = DateTimeImmutable::createFromFormat('!' . $dateFormat, $row[$testingDateCol]);
            $errors = DateTimeImmutable::getLastErrors();
            if (empty($errors['warning_count']) && empty($errors['error_count']) && !empty($testingDateObject)) {
                $testingDate = $testingDateObject->format('Y-m-d H:i');
            }

            $vlResult = trim((string) $row[$absValCol]);

            if (!empty($vlResult)) {
                if (str_contains($vlResult, 'E')) {
                    if (str_contains($vlResult, '< 2.00E+1')) {
                        $vlResult = "< 20";
                        $txtVal = $absVal = trim($vlResult);
                        $logVal = "";
                    } else {
                        $vlResult = (float) $vlResult;
                        $resInNumberFormat = number_format($vlResult, 0, '', '');
                        if ($vlResult > 0) {
                            $absVal = $resInNumberFormat;
                            $absDecimalVal = $vlResult;
                            $logVal = round(log10($vlResult), 2);
                            $txtVal = null;
                        } else {
                            $absVal = $txtVal = trim($vlResult);
                            $absDecimalVal = $logVal = "";
                        }
                    }
                } else {
                    $vlResult = (float)$row[$absValCol];
                    if ($vlResult > 0) {
                        $absVal = trim((string) $row[$absValCol]);
                        $absDecimalVal = $vlResult;
                        $logVal = round(log10($absDecimalVal), 4);
                        $txtVal = null;
                    } else {
                        $logVal = $absDecimalVal = $absVal = "";
                        $txtVal = trim((string) $row[$absValCol]);
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
                "sampleType" => $sampleType
            ];


            $m++;
        }

        foreach ($infoFromFile as $sampleCode => $d) {

            $data = array(
                'module' => 'vl',
                'lab_id' => base64_decode((string) $_POST['labId']),
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

            $query    = "SELECT facility_id,
                                vl_sample_id,
                                result,
                                result_value_log,
                                result_value_absolute,
                                result_value_text,
                                result_value_absolute_decimal
                        FROM form_vl
                        WHERE result_printed_datetime is null
                        AND sample_code= ?";
            $vlResult = $db->rawQueryOne($query, [$sampleCode]);
            if (!empty($vlResult) && !empty($sampleCode)) {
                if (!empty($vlResult['result'])) {
                    $data['sample_details'] = 'Result already exists';
                }
                $data['facility_id'] = $vlResult['facility_id'];
            } else {
                $data['sample_details'] = 'New Sample';
            }
            if ($sampleCode != '' || $sampleType != '' || $logVal != '' || $absVal != '' || $absDecimalVal != '') {
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


    header("Location:/import-result/imported-results.php?t=$type");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

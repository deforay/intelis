<?php


use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use App\Registries\AppRegistry;
use App\Utilities\DateUtility;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {
    $dateFormat = (empty($_POST['dateFormat'])) ? 'd/m/Y H:i' : $_POST['dateFormat'];

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
    $fileName = str_replace(" ", "-", $fileName) . "-" . MiscUtility::generateRandomString(12);
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName = $_POST['fileName'] . "." . $extension;

    // $fileName          = $ranNumber . "." . $extension;



    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {
        //$file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        //$mime_type = $file_info->buffer(file_get_contents($resultFile)); // e.g. gives "image/jpeg"

        $objPHPExcel = IOFactory::load(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData = $objPHPExcel->getActiveSheet();

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
        $flagCol = 'K';
        //$flagRow = '2';

        foreach ($sheetData as $rowIndex => $row) {

            if ($rowIndex < $skipTillRow) {
                continue;
            }

            $sampleCode = "";
            $sampleType = "";
            $absDecimalVal = "";
            $absVal = "";
            $logVal = "";
            $txtVal = "";
            $resultFlag = "";
            $testingDate = "";


            $sampleCode = $row[$sampleIdCol];
            $sampleType = $row[$sampleTypeCol];

            $resultFlag = $row[$flagCol];


            // Date time in the provided Roche Sample file is in this format : 9/9/16 12:22
            //$testingDate = DateTime::createFromFormat($dateFormat, $row[$testingDateCol])->format('Y-m-d H:i');

            $testingDate = date('Y-m-d H:i', strtotime((string) $row[$testingDateCol]));



            if (trim((string) $row[$absValCol]) !== "") {
                $resVal = (int) $row[$absValCol];
                if ($resVal > 0) {
                    $absDecimalVal = $absVal = trim((string) $row[$absValCol]);

                    $logVal = round(log10($absVal), 4);
                    $txtVal = null;
                } else {
                    $absDecimalVal = $absVal = "";
                    $logVal = "";
                    $txtVal = trim((string) $row[$absValCol]);
                }
            }

            if (trim((string) $row[$absValCol]) !== "") {
                $resVal = explode("(", (string) $row[$absValCol]);
                if (count($resVal) == 2) {
                    $absVal = trim($resVal[0]);

                    $expAbsVal = explode("E", $absVal);
                    if (count($expAbsVal) == 2) {
                        $multipleVal = substr($expAbsVal[1], 1);
                        $absDecimalVal = $expAbsVal[0] * 10 ** $multipleVal;
                    }
                    $logVal = substr(trim($resVal[1]), 0, -1);
                } else {
                    $txtVal = trim((string) $row[$absValCol]);
                    if ($txtVal === 'Invalid') {
                        $resultFlag = trim($txtVal);
                    }
                }
            }


            if ($sampleCode == "") {
                continue;
            }


            $infoFromFile[$sampleCode] = ["sampleCode" => $sampleCode, "logVal" => $logVal, "absVal" => $absVal, "absDecimalVal" => $absDecimalVal, "txtVal" => $txtVal, "resultFlag" => $resultFlag, "testingDate" => $testingDate, "sampleType" => $sampleType];


            $m++;
        }


        foreach ($infoFromFile as $sampleCode => $d) {

            $data = ['module' => 'vl', 'lab_id' => base64_decode((string) $_POST['labId']), 'vl_test_platform' => $_POST['vltestPlatform'], 'result_reviewed_by' => $_SESSION['userId'], 'sample_code' => $d['sampleCode'], 'result_value_log' => $d['logVal'], 'sample_type' => $d['sampleType'], 'result_value_absolute' => $d['absVal'], 'result_value_text' => $d['txtVal'], 'result_value_absolute_decimal' => $d['absDecimalVal'], 'sample_tested_datetime' => $d['testingDate'], 'result_status' => RECEIVED_AT_TESTING_LAB, 'import_machine_file_name' => $fileName, 'lab_tech_comments' => $d['resultFlag']];


            if ($d['absVal'] != "") {
                $data['result'] = $d['absVal'];
            } elseif ($d['logVal'] != "") {
                $data['result'] = $d['logVal'];
            } elseif ($d['txtVal'] != "") {
                $data['result'] = $d['txtVal'];
            } else {
                $data['result'] = "";
            }

            $query = "SELECT facility_id,vl_sample_id,result,result_value_log,result_value_absolute,result_value_text,result_value_absolute_decimal from form_vl where result_printed_datetime is null AND sample_code='" . $sampleCode . "'";
            $vlResult = $db->rawQueryOne($query);
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
    $eventType = 'import';
    $action = $_SESSION['userName'] . ' imported a new test result with the sample id ' . $sampleCode;
    $resource = 'import-result';
    $general->activityLog($eventType, $action, $resource);

    header("Location:/import-result/imported-results.php?t=$type");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

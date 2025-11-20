<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;
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

            $sampleType = !stripos((string) $sampleCode, 'control') && (int) $sampleCode > 0 ? "S" : $sampleCode;


            if (trim((string) $row[$absValCol]) === "<") {
                $absDecimalVal = $absVal = "";
                $logVal = "";
                $txtVal = "< 100";
            } elseif ((int) $row[$absValCol] > 0) {
                $absDecimalVal = $absVal = (int) $row[$absValCol];
                $logVal = round(log10($absVal), 4);
                $txtVal = null;
            } else {
                $absDecimalVal = $absVal = "";
                $logVal = "";
                $txtVal = null;
            }

            // Date time in the provided Biomerieux Sample file is in this format : 05-23-16 12:52:33
            $testingDate = $sheetData[6]['C'] . " " . $sheetData[7]['C'];
            $testingDate = DateTime::createFromFormat($dateFormat, $testingDate)->format('Y-m-d H:i:s');

            if ($sampleCode == "") {
                break;
            }

            $infoFromFile[$sampleCode] = ["sampleCode" => $sampleCode, "logVal" => $logVal, "absVal" => $absVal, "absDecimalVal" => $absDecimalVal, "txtVal" => $txtVal, "resultFlag" => $resultFlag, "testingDate" => $testingDate, "sampleType" => $sampleType];

            $m++;
        }


        foreach ($infoFromFile as $sampleCode => $d) {
            $data = ['module' => 'vl', 'lab_id' => base64_decode((string) $_POST['labId']), 'vl_test_platform' => $_POST['vltestPlatform'], 'import_machine_name' => $_POST['configMachineName'], 'result_reviewed_by' => $_SESSION['userId'], 'sample_code' => $d['sampleCode'], 'result_value_log' => $d['logVal'], 'sample_type' => $d['sampleType'], 'result_value_absolute' => $d['absVal'], 'result_value_text' => $d['txtVal'], 'result_value_absolute_decimal' => $d['absDecimalVal'], 'sample_tested_datetime' => $d['testingDate'], 'result_status' => RECEIVED_AT_TESTING_LAB, 'import_machine_file_name' => $fileName, 'lab_tech_comments' => $d['resultFlag']];


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

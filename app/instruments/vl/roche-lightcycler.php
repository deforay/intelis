<?php

use Laminas\Diactoros\ServerRequest;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);


try {

    // Sanitized values from $request object
    /** @var ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    /** @var VlService $vlService */
    $vlService = ContainerRegistry::get(VlService::class);

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    $dateFormat = (empty($_POST['dateFormat'])) ? 'd/m/Y H:i' : $_POST['dateFormat'];

    /** @var TestResultsService $testResultsService */
    $testResultsService = ContainerRegistry::get(TestResultsService::class);

    $testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'vl');

    // $_SESSION['controllertrack'] = $testResultsService->getMaxIDForHoldingSamples();

    $allowedExtensions = ['xls', 'xlsx', 'csv'];

    if (isset($_FILES['resultFile']) && $_FILES['resultFile']['error'] !== UPLOAD_ERR_OK || $_FILES['resultFile']['size'] <= 0) {
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
        $inputFileName = UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName;
        $spreadsheet = IOFactory::load($inputFileName);

        $sheet = $spreadsheet->getSheetByName('Résultats finaux');

        $testingSheet = $spreadsheet->getSheetByName('Liste des échantillons');
        $testedDate = $testingSheet->getCell('F6')->getValue();
        $testedBy =  $testingSheet->getCell('F7')->getValue();
        $dateObj = Date::excelToDateTimeObject($testedDate);
        $testDate = $dateObj->format('Y-m-d');
        $sheetData   = $sheet->toArray(null, true, true, true);

        $resultArray = array_slice($sheetData, 7);

        //dump($resultArray);

        // $sheet1 = $spreadsheet->getActiveSheet()->getCell('C7')->getValue();
        //  echo $sheet1; die;

        $data = [];

        foreach ($resultArray as $row) {
            if (empty($row['C'])) {
                continue;
            }
            $interpretedResults = $vlService->interpretViralLoadResult($row['D']);
            // dump($row['C']);
            // dump($row['D']);
            // dump($interpretedResults);
            $data[] = [
                'module' => 'vl',
                'lab_id' => base64_decode((string) $_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $row['C'],
                'result_value_log' => $interpretedResults['logVal'],
                'sample_type' => 'S',
                'result' => $interpretedResults['result'],
                'result_value_text' => $interpretedResults['txtVal'],
                'result_value_absolute' => $interpretedResults['absVal'],
                'result_value_absolute_decimal' => $interpretedResults['absDecimalVal'],
                'sample_tested_datetime' => $testDate,
                'result_status' => RECEIVED_AT_TESTING_LAB,
                'import_machine_file_name' => $fileName,
                'result_imported_datetime' => DateUtility::getCurrentDateTime(),
                'imported_by' => $_SESSION['userId'],
            ];
        }

        foreach ($data as $d) {
            $db->insert("temp_sample_import", $d);
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

<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

try {

    // Sanitized values from $request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());


    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    $dateFormat = (!empty($_POST['dateFormat'])) ? $_POST['dateFormat'] : 'd/m/Y H:i';

    /** @var TestResultsService $testResultsService */
    $testResultsService = ContainerRegistry::get(TestResultsService::class);

    $testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'vl');

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
    $fileName          = str_replace(" ", "-", $fileName);
    $extension         = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName          = $_POST['fileName'] . "." . $extension;



    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;

    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {
        //$file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        //$mime_type = $file_info->buffer(file_get_contents($resultFile)); // e.g. gives "image/jpeg"
        $inputFileName = UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName;
        $spreadsheet = IOFactory::load($inputFileName);

        $sheet = $spreadsheet->getActiveSheet();

        $testingSheet = $spreadsheet->getSheetByName('Liste des échantillons');
        $testedDate = $testingSheet->getCell('F6')->getValue();
        $testedBy =  $testingSheet->getCell('F7')->getValue();
        $dateObj = Date::excelToDateTimeObject($testedDate);
        $testDate = $dateObj->format('Y-m-d');
        $sheetData   = $sheet->toArray(null, true, true, true);

        $resultArray = array_slice($sheetData, 7);

        // $sheet1 = $spreadsheet->getActiveSheet()->getCell('C7')->getValue();
        //  echo $sheet1; die;
        $data = array();

        foreach ($resultArray as $row) {

            $data[] = array(
                'module' => 'vl',
                'lab_id' => base64_decode((string) $_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $row['C'],
                'result_value_log' => $row['E'],
                'sample_type' => $d['sampleType'],
                'result' => $row['D'],
                'sample_tested_datetime' => $testDate,
                'result_status' => 6,
                'import_machine_file_name' => $fileName,
                'result_imported_datetime' => DateUtility::getCurrentDateTime(),
                'imported_by' => $_SESSION['userId'],
            );
        }

        $db->insertMulti("temp_sample_import", $data);
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

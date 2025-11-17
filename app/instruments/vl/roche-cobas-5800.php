<?php

// For Roche COBAS 5800 test results import
// File gets called in import-file-helper.php based on the selected instrument type

use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;
use App\Services\TestResultImportService;
use App\Utilities\MiscUtility;

try {

    $testType = 'vl';
    // Initialize the import service
    /** @var TestResultImportService $importService */
    $importService = new TestResultImportService($testType);
    $importService->initializeImport();

    // Handle file upload and get CSV content
    $fileContents = $importService->handleFileUpload(['csv']);

    /** @var VlService $vlService */
    $vlService = ContainerRegistry::get(VlService::class);

    $infoFromFile = [];

    // Parse CSV data
    $lines = explode("\n", $fileContents);
    $firstLine = array_shift($lines);
    $delimiter = MiscUtility::detectCSVDelimiter($firstLine);
    $headers = str_getcsv($firstLine, $delimiter);


    foreach ($lines as $line) {
        if (in_array(trim($line), ['', '0'], true)) {
            continue;
        }

        $rowData = str_getcsv($line, $delimiter);

        // Skip rows that don't have enough columns
        if (count($rowData) < count($headers)) {
            continue;
        }

        $row = array_combine($headers, $rowData);

        if (empty($row['Sample ID'])) {
            continue;
        }

        $interpretedResults = $vlService->interpretViralLoadResult($row['Result'] ?? null);
        $testingDate = DateUtility::isoDateFormat($row['Released date/time'] ?? null, true);

        $infoFromFile[$row['Sample ID']] = [
            "sampleCode" => $row['Sample ID'],
            "logVal" => $interpretedResults['logVal'],
            "absVal" => $interpretedResults['absVal'],
            "absDecimalVal" => $interpretedResults['absDecimalVal'],
            "txtVal" => $interpretedResults['txtVal'],
            "result" => $interpretedResults['result'],
            "resultFlag" => null,
            "testingDate" => $testingDate,
            "sampleType" => null
        ];
    }

    // Send parsed data to service for insertion
    $importService->insertParsedData($infoFromFile);

    // Handle success using the service
    $importService->handleSuccess();
} catch (Exception $e) {
    $importService->handleError($e);
}

$importService->redirect();

<?php

// For Abbott m2000 test results import
// File gets called in import-file-helper.php based on the selected instrument type

use App\Services\VlService;
use App\Registries\ContainerRegistry;
use App\Services\TestResultImportService;


/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

try {
    $testType = 'vl';
    // Initialize the import service
    /** @var TestResultImportService $importService */
    $importService = new TestResultImportService($testType);
    $importService->initializeImport();

    // Handle file upload and get text content
    $fileContents = $importService->handleFileUpload(['txt']);

    // Create file handle from string content to use with fgetcsv
    $handle = fopen("data://text/plain,$fileContents", 'r');

    // Initialize variables
    $infoFromFile = [];
    $testingDate = null;
    $cvNumber = null;
    $skip = 23;
    $row = 1;
    $m = 1;

    // Column mappings
    $sampleIdCol = 1;
    $sampleTypeCol = 2;
    $resultCol = 5;
    $txtValCol = 6;
    $flagCol = 10;
    $testDateCol = 11;
    $lotNumberCol = 12;
    $lotExpirationDateCol = 13;

    // Parse the file using fgetcsv
    while (($sheetData = fgetcsv($handle, 10000, "\t")) !== false) {
        $row++;

        if ($row < $skip) {
            if (empty($sheetData[0])) {
                continue;
            }

            // Extract testing date from row 8
            if ($row == 8) {
                $testingDate = $importService->parseDate($sheetData[1] ?? null);
            } elseif (in_array($sheetData[0], ['PLATE NUMBER', 'PLATE NAME'])) {
                $cvNumber = $sheetData[1] ?? null;
            }
            continue;
        }

        // Initialize/Reset result variables
        $absDecimalVal = null;
        $absVal = null;
        $logVal = null;
        $txtVal = null;

        // Extract basic data
        $sampleCode = $sheetData[$sampleIdCol];
        $sampleType = $sheetData[$sampleTypeCol];
        $resultFlag = $sheetData[$flagCol];

        $unprocessedResult = strtolower((string)$sheetData[$resultCol]);


        // Process result values
        if ($sheetData[$resultCol] == "" || $sheetData[$resultCol] == null) {
            $absVal = $absDecimalVal =$logVal = null;
            $result = $txtVal = "Failed";
            $resultFlag = $sheetData[$flagCol] ?? '';
        } elseif (str_contains($unprocessedResult, 'log')) {
            continue; // Skip log entries, process copies/mL entries
        } elseif ($sheetData[$resultCol] == "< INF") {
            $absVal = $absDecimalVal = 839;
            $result = $txtVal = "< 839";
            $logVal = null;
        } else {

            $interpretedResults = $vlService->interpretViralLoadResult($sheetData[$resultCol] ?? null);

            $absVal = $interpretedResults['absVal'];
            $absDecimalVal = $interpretedResults['absDecimalVal'];
            $txtVal = $interpretedResults['txtVal'];
            $logVal = $interpretedResults['logVal'];
            $result = $interpretedResults['result'];
            $resultFlag = "";
        }

        // Process lot information
        $lotNumberVal = $sheetData[$lotNumberCol];
        $lotExpirationDateVal = null;
        if (trim((string) $sheetData[$lotExpirationDateCol]) !== '') {
            $lotExpirationDateVal = $importService->parseDate($sheetData[$lotExpirationDateCol]);
        }

        // Sample type mapping and code modification
        $sampleType = $sheetData[$sampleTypeCol];
        if ($sampleType == 'Patient' || $sampleType == 'Sample' || $sampleType == 'Specimen') {
            $sampleType = 'S';
        } elseif ($sampleType == 'Control') {
            if ($sampleCode == 'HIV_HIPOS') {
                $sampleType = 'HPC';
                $sampleCode = "$sampleCode-$lotNumberVal";
            } elseif ($sampleCode == 'HIV_LOPOS') {
                $sampleType = 'LPC';
                $sampleCode = "$sampleCode-$lotNumberVal";
            } elseif ($sampleCode == 'HIV_NEG') {
                $sampleType = 'NC';
                $sampleCode = "$sampleCode-$lotNumberVal";
            }
        }

        // Generate sample code if empty
        if ($sampleCode == "") {
            $sampleCode = $sampleType . $m;
        }

        // Store parsed data with result determination
        if (!isset($infoFromFile[$sampleCode])) {
            $infoFromFile[$sampleCode] = [
                "sampleCode" => $sampleCode,
                "logVal" => $logVal,
                "absVal" => $absVal,
                "absDecimalVal" => $absDecimalVal,
                "txtVal" => $txtVal,
                "result" => $result,
                "resultFlag" => $resultFlag,
                "testingDate" => $testingDate ?? null,
                "sampleType" => $sampleType,
                "lotNumber" => $lotNumberVal,
                "lotExpirationDate" => $lotExpirationDateVal,
                "cvNumber" => $cvNumber,
            ];
        }

        $m++;
    }

    fclose($handle);

    // Send parsed data to service for insertion
    $importService->insertParsedData($infoFromFile);

    // Handle success using the service
    $importService->handleSuccess();
} catch (Exception $e) {
    $importService->handleError($e);
}

$importService->redirect();

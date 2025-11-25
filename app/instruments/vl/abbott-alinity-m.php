<?php

// For Abbott Alinity M Test results import
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

    // Handle file upload and get Excel data as array
    $sheetData = $importService->handleFileUpload(['xls', 'xlsx']);

    // Initialize parsing variables
    $infoFromFile = [];
    $skipTillRow = 2;

    // Column mappings
    $sampleIdCol = 'A';
    $resultCol = 'M';
    $testingDateCol = 'W';
    $sampleTypeCol = 'F';
    $flagCol = 'S';

    foreach ($sheetData as $rowIndex => $row) {
        if ($rowIndex < $skipTillRow) {
            continue;
        }

        // Extract basic data
        $sampleCode = $row[$sampleIdCol] ?? '';
        $sampleType = $row[$sampleTypeCol] ?? 'S';
        $resultFlag = $row[$flagCol] ?? '';
        $vlResult = $row[$resultCol] ?? null;

        if (empty($sampleCode)) {
            continue;
        }

        // Parse testing date
        $testingDate = $importService->parseDate($row[$testingDateCol] ?? '');

        // Interpret the viral load result using VlService
        $interpretedResults = $vlService->interpretViralLoadResult($vlResult);

        // Sample type mapping
        if ($sampleType == 'Patient' || $sampleType == 'Sample' || $sampleType == 'Specimen') {
            $sampleType = 'S';
        } elseif ($sampleType == 'Control' || $sampleType == 'C' || $sampleType == 'QC') {
            if ($sampleCode == 'HIV_HIPOS') {
                $sampleType = 'HPC';
            } elseif ($sampleCode == 'HIV_LOPOS') {
                $sampleType = 'LPC';
            } elseif ($sampleCode == 'HIV_NEG') {
                $sampleType = 'NC';
            }
        }

        $bdl = [
            '< 400',
        ];

        if (
            !empty($interpretedResults['result']) &&
            (in_array(strtolower((string) $interpretedResults['result']), $bdl) ||
                $interpretedResults['absDecimalVal'] < 400)
        ) {
            $interpretedResults['txtVal'] =
                $interpretedResults['result'] = 'Below Detection Level';
            $interpretedResults['absVal'] = null;
            $interpretedResults['absDecimalVal'] = null;
            $interpretedResults['logVal'] = null;
        }

        $tnd = [
            'not detected',
            'tnd',
            'undetected',
        ];

        if (
            !empty($interpretedResults['result']) &&
            in_array(strtolower((string) $interpretedResults['result']), $bdl)
        ) {
            $interpretedResults['txtVal'] =
                $interpretedResults['result'] = 'Target Not Detected';
            $interpretedResults['absVal'] = null;
            $interpretedResults['absDecimalVal'] = null;
            $interpretedResults['logVal'] = null;
        }


        // Store parsed data
        $infoFromFile[$sampleCode] = [
            "sampleCode" => $sampleCode,
            "logVal" => $interpretedResults['logVal'] ?? null,
            "absVal" => $interpretedResults['absVal'] ?? null,
            "absDecimalVal" => $interpretedResults['absDecimalVal'] ?? null,
            "txtVal" => $interpretedResults['txtVal'] ?? null,
            "result" => $interpretedResults['result'] ?? null,
            "resultFlag" => $resultFlag,
            "testingDate" => $testingDate,
            "sampleType" => $sampleType
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

<?php

// For GENEXPERT test results import
// File gets called in import-file-helper.php based on the selected instrument type

use League\Csv\Reader;
use App\Services\VlService;
use App\Utilities\MiscUtility;
use App\Registries\ContainerRegistry;
use App\Services\TestResultImportService;

try {
    $testType = 'vl';
    // Initialize the import service
    /** @var TestResultImportService $importService */
    $importService = new TestResultImportService($testType);
    $importService->initializeImport();

    // Handle file upload and get CSV content
    $filePath = $importService->handleFileUpload(['csv'], operation: 'import');

    /** @var VlService $vlService */
    $vlService = ContainerRegistry::get(VlService::class);

    // Read and convert the file to handle UTF-16 encoding if needed
    $rawContent = file_get_contents($filePath);
    $encoding = mb_detect_encoding($rawContent, ['UTF-16LE', 'UTF-16BE', 'UTF-8', 'ASCII'], true);

    // Only convert if it's actually UTF-16, otherwise use original file
    if ($encoding === 'UTF-16LE' || $encoding === 'UTF-16BE') {
        $rawContent = mb_convert_encoding($rawContent, 'UTF-8', $encoding);
        // Write the converted content back to a temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'genexpert_');
        file_put_contents($tempFile, $rawContent);
        $filePath = $tempFile;
    }

    // Create CSV reader from file contents
    $reader = Reader::createFromPath($filePath);

    $infoFromFile = [];
    $sampleCode = null;
    $testedOn = null;
    $testedBy = null;



    foreach ($reader as $offset => $record) {
        foreach ($record as $o => $v) {
            // Clean the value using the existing method
            $v = $importService->removeControlCharsAndEncode($v);

            if ($v == "Status") {
                $status = $importService->removeControlCharsAndEncode($record[1] ?? '');
                if (!empty($status) && $status == "Incomplete") {
                    continue 2;
                }
            } elseif ($v == "End Time" || $v == "Heure de fin") {
                $rawEndTime = $record[1] ?? '';
                $cleanEndTime = $importService->removeControlCharsAndEncode($rawEndTime);

                if (!empty($cleanEndTime)) {
                    $testedOn = $importService->parseDate($cleanEndTime);
                } else {
                    error_log("End Time is empty after cleaning");
                }
            } elseif ($v == "Start Time" || $v == "Heure de début") {
                // Use as fallback if End Time is not available
                if (empty($testedOn)) {
                    $rawStartTime = $record[1] ?? '';
                    $cleanStartTime = $importService->removeControlCharsAndEncode($rawStartTime);

                    if (!empty($cleanStartTime)) {
                        $testedOn = $importService->parseDate($cleanStartTime);
                        error_log("Using Start Time as fallback: '$testedOn'");
                    }
                }
            } elseif ($v == "User" || $v == 'Utilisateur') {
                $testedBy = $importService->removeControlCharsAndEncode($record[1] ?? '');
            } elseif ($v == "RESULT TABLE" || $v == "TABLEAU DE RÉSULTATS") {
                $sampleCode = null;
            } elseif ($v == "Sample ID" || $v == "N° Id de l'échantillon") {
                $sampleCode = $importService->removeControlCharsAndEncode($record[1] ?? '');

                if (empty($sampleCode)) {
                    continue 2;
                }

                $infoFromFile[$sampleCode]['sampleCode'] = $sampleCode;
                $infoFromFile[$sampleCode]['testingDate'] = $testedOn;
                $infoFromFile[$sampleCode]['reviewBy'] = $testedBy;
                $infoFromFile[$sampleCode]['sampleType'] = 'S'; // Default sample type

            } elseif ($v == "Assay" || $v == "Test") {
                if (empty($sampleCode)) {
                    continue;
                }
                $assayValue = $importService->removeControlCharsAndEncode($record[1] ?? '');
                $infoFromFile[$sampleCode]['assay'] = $assayValue;
            } elseif ($v == "Test Result" || $v == "Résultat du test") {
                if (empty($sampleCode)) {
                    continue;
                }

                $resultValue = $importService->removeControlCharsAndEncode($record[1] ?? '');
                $parsedResult = str_replace("|", "", strtoupper($resultValue));
                $parts = explode(" (LOG ", $parsedResult);
                $vlResult = $parts[0];
                $logVal = isset($parts[1]) ? rtrim($parts[1], ")") : null;

                $interpretedResults = $vlService->interpretViralLoadResult($vlResult);

                $infoFromFile[$sampleCode]['logVal'] = $logVal ?? $interpretedResults['logVal'] ?? null;
                $infoFromFile[$sampleCode]['absDecimalVal'] = $interpretedResults['absDecimalVal'] ?? null;
                $infoFromFile[$sampleCode]['absVal'] = $interpretedResults['absVal'] ?? null;
                $infoFromFile[$sampleCode]['txtVal'] = $interpretedResults['txtVal'] ?? null;
                $infoFromFile[$sampleCode]['result'] = $interpretedResults['result'] ?? null;
                $infoFromFile[$sampleCode]['resultFlag'] = null;
            }
        }
    }

    // Clean up temp file if we created one
    MiscUtility::deleteFile($tempFile);


    // Send parsed data to service for insertion
    if ($infoFromFile !== []) {
        $importService->insertParsedData($infoFromFile);
        $importService->handleSuccess();
    } else {
        throw new Exception("No sample data found");
    }
} catch (Exception $e) {
    if (isset($importService)) {
        $importService->handleError($e);
    }
    error_log("Import error: " . $e->getMessage());
}

if (isset($importService)) {
    $importService->redirect();
}

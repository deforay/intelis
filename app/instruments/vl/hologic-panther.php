<?php

// For Hologic Panther test results import
// File gets called in import-file-helper.php based on the selected instrument type
use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$dateFormat = (empty($_POST['dateFormat'])) ? 'm/d/Y H:i:s' : $_POST['dateFormat'];

/** @var TestResultsService $testResultsService */
$testResultsService = ContainerRegistry::get(TestResultsService::class);
$testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'vl');
// $_SESSION['controllertrack'] = $testResultsService->getMaxIDForHoldingSamples();

// Process the uploaded file
if (isset($_FILES['resultFile']) && $_FILES['resultFile']['error'] !== UPLOAD_ERR_OK || $_FILES['resultFile']['size'] <= 0) {
    throw new SystemException('Please select a file to upload', 400);
}

$fileName = preg_replace('/[^A-Za-z0-9.]/', '-', htmlspecialchars(basename((string) $_FILES['resultFile']['name'])));
$extension = MiscUtility::getFileExtension($fileName);
$fileName = $_POST['fileName'] . "-" . MiscUtility::generateRandomString(12) . "." . $extension;

$resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;
if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {

    $file_info = new finfo(FILEINFO_MIME);
    $mime_type = $file_info->buffer(file_get_contents($resultFile));

    // Only proceed if file is a text file
    if (str_contains($mime_type, 'text/plain')) {
        $infoFromFile = [];

        // Column mappings based on the file structure
        $sampleIdCol = 0;       // Specimen Barcode
        $resultCol = 4;         // Interpretation 1 - main result value
        $logValCol = 5;         // Interpretation 2 - log value
        $validityCol = 7;       // Interpretation 4 - validity
        $sampleTypeCol = 19;    // Sample Type
        $lotNumberCol = 15;     // Assay Reagent Kit ML #
        $lotExpirationDateCol = 16; // Assay Reagent Kit ML Exp Date UTC
        $testDateCol = 36;      // Pipette Time UTC

        $row = 0;
        $skip = 1; // Skip header row

        // Parse the file
        if (($handle = fopen($resultFile, "r")) !== false) {
            while (($sheetData = fgetcsv($handle, 10000, "\t")) !== false) {
                $row++;

                // Skip header row
                if ($row <= $skip) {
                    continue;
                }

                // Skip if end marker or not enough columns
                if (isset($sheetData[0]) && str_contains((string) $sheetData[0], '[end]') || count($sheetData) < 20) {
                    continue;
                }

                $sampleCode = isset($sheetData[$sampleIdCol]) ? trim((string) $sheetData[$sampleIdCol]) : "";
                $sampleType = isset($sheetData[$sampleTypeCol]) ? trim((string) $sheetData[$sampleTypeCol]) : "";
                $resultValue = isset($sheetData[$resultCol]) ? trim((string) $sheetData[$resultCol]) : "";
                $logVal = isset($sheetData[$logValCol]) ? trim((string) $sheetData[$logValCol]) : "";
                $validity = isset($sheetData[$validityCol]) ? trim((string) $sheetData[$validityCol]) : "";

                // Process the result value
                $absVal = "";
                $absDecimalVal = "";
                $txtVal = null;

                if (str_contains(strtolower($resultValue), 'not detected')) {
                    $txtVal = "Below Detection Level";
                    $absVal = "";
                    $absDecimalVal = "";
                } elseif (str_contains($resultValue, '<883 detected')) {
                    $txtVal = "< 883";
                    $absVal = "< 883";
                    $absDecimalVal = "< 883";
                } else {
                    // It's a numeric value - remove any commas
                    $absVal = str_replace(',', '', $resultValue);
                    $absDecimalVal = $absVal;
                }

                // Process testing date
                $testingDate = null;
                if (isset($sheetData[$testDateCol]) && trim((string) $sheetData[$testDateCol]) !== '') {
                    try {
                        $testingDateObject = DateTimeImmutable::createFromFormat($dateFormat, $sheetData[$testDateCol]);
                        if ($testingDateObject) {
                            $testingDate = $testingDateObject->format('Y-m-d H:i:s');
                        }
                    } catch (Exception) {
                        // If date parsing fails, leave as null
                    }
                }

                // Process lot expiration date
                $lotExpirationDateVal = null;
                if (isset($sheetData[$lotExpirationDateCol]) && trim((string) $sheetData[$lotExpirationDateCol]) !== '') {
                    try {
                        $lotExpirationDateObject = DateTimeImmutable::createFromFormat($dateFormat, $sheetData[$lotExpirationDateCol]);
                        if ($lotExpirationDateObject) {
                            $lotExpirationDateVal = $lotExpirationDateObject->format('Y-m-d H:i:s');
                        }
                    } catch (Exception) {
                        // If date parsing fails, leave as null
                    }
                }

                // Get lot number
                $lotNumberVal = isset($sheetData[$lotNumberCol]) ? trim((string) $sheetData[$lotNumberCol]) : "";

                // Process sample type
                if ($sampleType === 'Specimen') {
                    $sampleType = 'S';
                }

                // Store the parsed data
                if (!isset($infoFromFile[$sampleCode]) && ($sampleCode !== '' && $sampleCode !== '0')) {
                    $infoFromFile[$sampleCode] = ["sampleCode" => $sampleCode, "logVal" => $logVal, "absVal" => $absVal, "absDecimalVal" => $absDecimalVal, "txtVal" => $txtVal, "resultFlag" => $validity, "testingDate" => $testingDate, "sampleType" => $sampleType, "lotNumber" => $lotNumberVal, "lotExpirationDate" => $lotExpirationDateVal];
                }
            }

            fclose($handle);
        }

        // Process and insert the data
        foreach ($infoFromFile as $sampleCode => $d) {
            // Prepare result value
            if ($d['txtVal'] != "") {
                $resultValue = $d['txtVal'];
            } elseif ($d['absVal'] != "") {
                $resultValue = $d['absVal'];
            } elseif ($d['logVal'] !== "") {
                $resultValue = $d['logVal'];
            } else {
                $resultValue = "";
            }

            // Prepare data for database insertion
            $data = [
                'module' => 'vl',
                'lab_id' => base64_decode((string) $_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'result_value_log' => $d['logVal'],
                'sample_type' => $d['sampleType'],
                'result_value_absolute' => $d['absVal'],
                'result_value_text' => $d['txtVal'],
                'result_value_absolute_decimal' => $d['absDecimalVal'],
                'sample_tested_datetime' => $d['testingDate'],
                'result_status' => RECEIVED_AT_TESTING_LAB,
                'import_machine_file_name' => $fileName,
                'lab_tech_comments' => $d['resultFlag'],
                'lot_number' => $d['lotNumber'],
                'lot_expiration_date' => $d['lotExpirationDate'],
                'result' => $resultValue,
            ];

            // Check if sample already exists in the database
            $query = "SELECT facility_id, vl_sample_id, result FROM form_vl WHERE sample_code='" . $db->escape($sampleCode) . "'";
            $vlResult = $db->rawQueryOne($query);

            // Insert sample controls if needed
            $scQuery = "SELECT r_sample_control_name FROM r_sample_controls where r_sample_control_name='" . $db->escape(trim($d['sampleType'])) . "'";
            $scResult = $db->rawQuery($scQuery);
            if (!$scResult) {
                $scData = ['r_sample_control_name' => trim($d['sampleType'])];
                $scId = $db->insert("r_sample_controls", $scData);
            }

            // Check if result already exists
            if (!empty($vlResult) && ($sampleCode !== '' && $sampleCode !== '0')) {
                if (!empty($vlResult['result'])) {
                    $data['sample_details'] = _translate('Result already exists');
                }
                $data['facility_id'] = $vlResult['facility_id'];
            } else {
                $data['sample_details'] = _translate('New Sample');
            }

            // Insert data into database
            $data['result_imported_datetime'] = DateUtility::getCurrentDateTime();
            $data['imported_by'] = $_SESSION['userId'];
            $id = $db->insert("temp_sample_import", $data);
        }
    }

    $_SESSION['alertMsg'] = _translate("Results imported successfully", true);
    //Add event log
    $eventType = 'import';
    $action = $_SESSION['userName'] . ' imported new test results';
    $resource = 'import-results-manually';
    $general->activityLog($eventType, $action, $resource);

    header("Location:/import-result/imported-results.php?t=$type");
} else {
    throw new SystemException('Failed to move uploaded file', 500);
}

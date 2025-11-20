<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;

try {

    // Sanitized values from $request object
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $tableName = $_POST['tableName'];

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    $dateFormat = (empty($_POST['dateFormat'])) ? 'd/m/Y H:i' : $_POST['dateFormat'];

    /** @var TestResultsService $testResultsService */
    $testResultsService = ContainerRegistry::get(TestResultsService::class);

    $testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'eid');

    // $_SESSION['controllertrack'] = $testResultsService->getMaxIDForHoldingSamples();

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



    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {


        foreach (MiscUtility::readCSVFile($resultFile) as $row) {
            if ($row['Sample ID'] == "") {
                continue;
            }

            $result = (string) $row['Result'];
            $resultInLowerCase = strtolower($result);
            if (str_contains($resultInLowerCase, 'not detected')) {
                $result = 'negative';
            } elseif ((str_contains($resultInLowerCase, 'detected')) || (str_contains(strtolower((string) $rowData[$resultCol]), 'passed'))) {
                $result = 'positive';
            } else {
                $result = $resultInLowerCase;
            }

            $testingDate = DateUtility::isoDateFormat($row['Released date/time'] ?? null, true);

            $infoFromFile[$row['Sample ID']] = [
                "sampleCode" => $row['Sample ID'],
                "logVal" => null,
                "absVal" => null,
                "absDecimalVal" => null,
                "txtVal" => null,
                "result" => $result,
                "resultFlag" => null,
                "testingDate" => $testingDate,
                "sampleType" => null
            ];
        }

        foreach ($infoFromFile as $sampleCode => $d) {

            $data = [
                'module' => 'eid',
                'lab_id' => base64_decode((string) $_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'result_value_log' => $d['logVal'],
                'sample_type' => $d['sampleType'],
                'result' => $d['result'],
                'result_value_absolute' => $d['absVal'],
                'result_value_text' => $d['txtVal'],
                'result_value_absolute_decimal' => $d['absDecimalVal'],
                'sample_tested_datetime' => $d['testingDate'],
                'result_status' => RECEIVED_AT_TESTING_LAB,
                'import_machine_file_name' => $fileName,
                'lab_tech_comments' => $d['resultFlag']
            ];


            $query = "SELECT facility_id,
                                eid_id,
                                result
                        FROM form_eid
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
    $action = $_SESSION['userName'] . ' imported test results for Roche EID';
    $resource = 'import-result';
    $general->activityLog($eventType, $action, $resource);


    header("Location:/import-result/imported-results.php?t=$type");
} catch (Exception $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}

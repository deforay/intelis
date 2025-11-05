<?php

// File gets called in import-file-helper.php based on the selected instrument type

use League\Csv\Reader;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TestResultsService $testResultsService */
$testResultsService = ContainerRegistry::get(TestResultsService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {

    $dateFormat = (!empty($_POST['dateFormat'])) ? $_POST['dateFormat'] : 'm/d/Y H:i';

    $testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'eid');

    // $_SESSION['controllertrack'] = $testResultsService->getMaxIDForHoldingSamples();

    $allowedExtensions = [
        'csv',
    ];

    if (
        isset($_FILES['resultFile']) && $_FILES['resultFile']['error'] !== UPLOAD_ERR_OK
        || $_FILES['resultFile']['size'] <= 0
    ) {
        throw new SystemException('Please select a file to upload', 400);
    }

    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', htmlspecialchars(basename((string) $_FILES['resultFile']['name'])));

    $extension = MiscUtility::getFileExtension($fileName);
    if (!in_array($extension, $allowedExtensions)) {
        throw new SystemException("Invalid file format.");
    }
    $fileName = $_POST['fileName'] . "-" . MiscUtility::generateRandomString(12) . "." . $extension;




    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {

        $file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        $mime_type = $file_info->buffer(file_get_contents($resultFile)); // e.g. gives "image/jpeg"

        $m = 1;
        $skipTillRow = 47;

        $sampleIdCol = "D";
        $resultCol = "I";


        $reader = Reader::createFromPath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName);
        $infoFromFile = [];
        foreach ($reader as $offset => $record) {
            //$newRow = [];
            //$sampleCode = null;
            foreach ($record as $o => $v) {

                $v = $testResultsService->removeControlCharsAndEncode($v);
                if ($v == "End Time" || $v == "Heure de fin") {
                    $testedOn = $testResultsService->removeControlCharsAndEncode($record[1]);
                    $timestamp = DateTimeImmutable::createFromFormat("!$dateFormat", $testedOn);
                    if (!empty($timestamp)) {
                        $timestamp = $timestamp->getTimestamp();
                        $testedOn = date('Y-m-d H:i', $timestamp);
                    } else {
                        $testedOn = null;
                    }
                } elseif ($v == "User" || $v == 'Utilisateur') {
                    $testedBy = $testResultsService->removeControlCharsAndEncode($record[1]);
                } elseif ($v == "RESULT TABLE" || $v == "TABLEAU DE RÉSULTATS") {
                    $sampleCode = null;
                } elseif ($v == "Sample ID" || $v == "N° Id de l'échantillon") {
                    $sampleCode = $testResultsService->removeControlCharsAndEncode($record[1]);
                    if (empty($sampleCode)) {
                        continue;
                    }
                    $infoFromFile[$sampleCode]['sampleCode'] = $sampleCode;
                    $infoFromFile[$sampleCode]['testedOn'] = $testedOn;
                    $infoFromFile[$sampleCode]['testedBy'] = $testedBy;
                } elseif ($v == "Assay" || $v == "Test") {
                    if (empty($sampleCode)) {
                        continue;
                    }
                    $infoFromFile[$sampleCode]['assay'] = $testResultsService->removeControlCharsAndEncode($record[1]);
                } elseif ($v == "Test Result" || $v == "Résultat du test") {
                    if (empty($sampleCode)) {
                        continue;
                    }

                    $parsedResult = (str_replace("|", "", strtoupper($testResultsService->removeControlCharsAndEncode($record[1]))));

                    if ($general->checkIfStringExists($parsedResult, array('not detected', 'notdetected')) !== false) {
                        $parsedResult = 'negative';
                    } elseif ($general->checkIfStringExists($parsedResult, array('detected'))) {
                        $parsedResult = 'positive';
                    }
                    $infoFromFile[$sampleCode]['result'] = strtolower($parsedResult);
                }
            }
        }

        $inc = 0;
        foreach ($infoFromFile as $sampleCode => $d) {

            $data = [
                'module' => 'eid',
                'lab_id' => base64_decode((string) $_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'sample_tested_datetime' => $d['testedOn'],
                'sample_type' => 'S',
                'result_status' => SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB,
                'import_machine_file_name' => $fileName,
                'result' => trim($d['result']),
            ];

            if (empty($data['result'])) {
                $data['result_status'] = SAMPLE_STATUS\ON_HOLD; // 1= Hold
            }
            //get username
            if (!empty($d['reviewBy'])) {

                /** @var UsersService $usersService */
                $usersService = ContainerRegistry::get(UsersService::class);
                $data['sample_review_by'] = $usersService->getOrCreateUser($d['reviewBy']);
            }

            $query = "SELECT facility_id, eid_id, result
                        FROM form_eid
                        WHERE sample_code= ?";
            $vlResult = $db->rawQueryOne($query, array($sampleCode));

            if (!empty($vlResult) && !empty($sampleCode)) {
                if (!empty($vlResult['result'])) {
                    $data['sample_details'] = 'Result already exists';
                }
                $data['facility_id'] = $vlResult['facility_id'];
            } else {
                $data['sample_details'] = 'New Sample';
            }

            if (!empty($sampleCode)) {
                $data['result_imported_datetime'] = DateUtility::getCurrentDateTime();
                $data['imported_by'] = $_SESSION['userId'];
                $id = $db->insert("temp_sample_import", $data);
            }
            $inc++;
        }
    }

    $_SESSION['alertMsg'] = "Result file imported successfully";
    //Add event log
    $eventType = 'import';
    $action = $_SESSION['userName'] . ' imported a new test result with the sample id ' . $sampleCode;
    $resource = 'import-results-manually';
    $general->activityLog($eventType, $action, $resource);

    header("Location:/import-result/imported-results.php?t=eid");
} catch (Exception $exc) {

    LoggerUtility::logError($exc->getMessage(), ['file' => __FILE__, 'line' => __LINE__, 'trace' => $exc->getTraceAsString()]);

    $_SESSION['alertMsg'] = _translate("Result file could not be imported. Please check if the file is of correct format.");
    header("Location:/import-result/import-file.php?t=vl");
}

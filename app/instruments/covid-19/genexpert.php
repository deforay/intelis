<?php

// File gets called in import-file-helper.php based on the selected instrument type
use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\ON_HOLD;
use League\Csv\Reader;
use App\Services\EidService;
use App\Services\UsersService;
use App\Services\ImportedSampleMatcher;
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

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {

    $dateFormat = (empty($_POST['dateFormat'])) ? 'm/d/Y H:i' : $_POST['dateFormat'];

    $testResultsService->clearPreviousImportsByUser($_SESSION['userId'], 'covid19');

    $allowedExtensions = ['csv'];

    if (
        !isset($_FILES['resultFile'])
        || $_FILES['resultFile']['error'] !== UPLOAD_ERR_OK
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

        // A GeneXpert export: label in the first column, value in the second, one
        // block per cartridge, each block starting with its Sample ID.
        $reader = Reader::createFromPath($resultFile);
        $infoFromFile = [];
        $sampleCode = null;
        $testedOn = null;
        $testedBy = null;
        foreach ($reader as $record) {
            $label = $testResultsService->removeControlCharsAndEncode($record[0] ?? '');
            $value = $testResultsService->removeControlCharsAndEncode($record[1] ?? '');

            // End Time and User come inside each sample's block, and also once at the
            // top of some exports; the block's own value wins for its sample.
            if ($label == "End Time" || $label == "Heure de fin") {
                $timestamp = DateTimeImmutable::createFromFormat("!$dateFormat", $value);
                $testedOn = $timestamp ? $timestamp->format('Y-m-d H:i') : null;
                if (!empty($sampleCode)) {
                    $infoFromFile[$sampleCode]['testedOn'] = $testedOn;
                }
            } elseif ($label == "User" || $label == 'Utilisateur') {
                $testedBy = $value;
                if (!empty($sampleCode) && $value !== '') {
                    $infoFromFile[$sampleCode]['reviewBy'] = $value;
                }
            } elseif ($label == "RESULT TABLE" || $label == "TABLEAU DE RÉSULTATS") {
                $sampleCode = null;
            } elseif ($label == "Sample ID" || $label == "N° Id de l'échantillon") {
                $sampleCode = $value;
                if (empty($sampleCode)) {
                    continue;
                }
                $infoFromFile[$sampleCode] = [
                    'sampleCode' => $sampleCode,
                    'testedOn' => $testedOn,
                    // The operator who ran the cartridge, as for viral load and EID.
                    'reviewBy' => $testedBy,
                    'lotNumber' => null,
                ];
            } elseif (empty($sampleCode)) {
                continue;
            } elseif ($label == "Assay" || $label == "Test") {
                $infoFromFile[$sampleCode]['assay'] = $value;
            } elseif ($label == "Reagent Lot ID" || $label == "N° de lot du réactif") {
                // Often left empty; an empty lot number is still a valid result.
                $infoFromFile[$sampleCode]['lotNumber'] = $value !== '' ? $value : null;
            } elseif ($label == "Test Result" || $label == "Résultat du test") {
                $parsedResult = str_replace("|", "", strtoupper($value));
                // "SARS-CoV-2 NEGATIVE" / "SARS-CoV-2 POSITIVE", in English or French.
                $infoFromFile[$sampleCode]['result'] = EidService::interpretEidResult($parsedResult)
                    ?? strtolower($parsedResult);
            }
        }

        /** @var UsersService $usersService */
        $usersService = ContainerRegistry::get(UsersService::class);

        foreach ($infoFromFile as $sampleCode => $d) {
            $data = [
                'module' => 'covid19',
                'lab_id' => base64_decode((string) $_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'sample_tested_datetime' => $d['testedOn'],
                'sample_type' => 'S',
                'result_status' => RECEIVED_AT_TESTING_LAB,
                'import_machine_file_name' => $fileName,
                'lot_number' => $d['lotNumber'],
                'result' => trim((string) ($d['result'] ?? '')),
            ];

            if ($data['result'] === '') {
                $data['result_status'] = ON_HOLD;
            }
            if (!empty($d['reviewBy'])) {
                $data['sample_review_by'] = $usersService->getOrCreateUser($d['reviewBy'], labId: (int) $data['lab_id']);
            }

            $data = ContainerRegistry::get(ImportedSampleMatcher::class)->stage('covid19', $data);

            $data['result_imported_datetime'] = DateUtility::getCurrentDateTime();
            $data['imported_by'] = $_SESSION['userId'];
            $db->insert("temp_sample_import", $data);
        }
    }

    $_SESSION['alertMsg'] = "Result file imported successfully";
    $general->activityLog(
        'import',
        $_SESSION['userName'] . ' imported COVID-19 results from ' . $fileName,
        'import-results-manually'
    );

    header("Location:/import-result/imported-results.php?t=covid19");
} catch (Exception $exc) {

    LoggerUtility::logError($exc->getMessage(), ['file' => $exc->getFile(), 'line' => $exc->getLine(), 'trace' => $exc->getTraceAsString()]);

    $_SESSION['alertMsg'] = _translate("Result file could not be imported. Please check if the file is of correct format.");
    header("Location:/import-result/import-file.php?t=covid19");
}

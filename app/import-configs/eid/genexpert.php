<?php

// File included in addImportResultHelper.php

use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use League\Csv\Reader;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

try {

    $dateFormat = (isset($_POST['dateFormat']) && !empty($_POST['dateFormat'])) ? $_POST['dateFormat'] : 'm/d/Y H:i';
    $db = $db->where('imported_by', $_SESSION['userId']);
    $db->delete('temp_sample_import');
    //set session for controller track id in hold_sample_record table
    $cQuery = "SELECT MAX(import_batch_tracking) FROM hold_sample_import";
    $cResult = $db->query($cQuery);
    if ($cResult[0]['MAX(import_batch_tracking)'] != '') {
        $maxId = $cResult[0]['MAX(import_batch_tracking)'] + 1;
    } else {
        $maxId = 1;
    }
    $_SESSION['controllertrack'] = $maxId;

    $allowedExtensions = array(
        'csv',
    );

    if (
        isset($_FILES['resultFile']) && $_FILES['resultFile']['error'] !== UPLOAD_ERR_OK
        || $_FILES['resultFile']['size'] <= 0
    ) {
        throw new SystemException('Please select a file to upload', 400);
    }

    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', htmlspecialchars(basename($_FILES['resultFile']['name'])));
    $fileName = str_replace(" ", "-", $fileName);
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        throw new SystemException("Invalid file format.");
    }
    $fileName = $_POST['fileName'] . "." . $extension;
    // $ranNumber = $general->generateRandomString(12);
    // $fileName = $ranNumber . "." . $extension;


    if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results")) {
        mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results", 0777, true);
    }
    $resultFile = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") . DIRECTORY_SEPARATOR . $fileName;
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {

        $file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        $mime_type = $file_info->buffer(file_get_contents($resultFile)); // e.g. gives "image/jpeg"

        $bquery = "SELECT MAX(batch_code_key) FROM `batch_details`";
        $bvlResult = $db->rawQuery($bquery);
        if ($bvlResult[0]['MAX(batch_code_key)'] != '' && $bvlResult[0]['MAX(batch_code_key)'] != null) {
            $maxBatchCodeKey = $bvlResult[0]['MAX(batch_code_key)'] + 1;
            $maxBatchCodeKey = "00" . $maxBatchCodeKey;
        } else {
            $maxBatchCodeKey = '001';
        }

        $newBatchCode = date('Ymd') . $maxBatchCodeKey;

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

                $v = $general->removeCntrlCharsAndEncode($v);
                // echo "<pre>";
                // var_dump(($v));
                // echo "</pre><br><br><br>";
                if ($v == "End Time" || $v == "Heure de fin") {
                    $testedOn = $general->removeCntrlCharsAndEncode($record[1]);
                    $timestamp = DateTimeImmutable::createFromFormat("!$dateFormat", $testedOn);
                    if (!empty($timestamp)) {
                        $timestamp = $timestamp->getTimestamp();
                        $testedOn = date('Y-m-d H:i', $timestamp);
                    } else {
                        $testedOn = null;
                    }
                } elseif ($v == "User" || $v == 'Utilisateur') {
                    $testedBy = $general->removeCntrlCharsAndEncode($record[1]);
                } else if ($v == "RESULT TABLE" || $v == "TABLEAU DE RÉSULTATS") {
                    $sampleCode = null;
                } else if ($v == "Sample ID" || $v == "N° Id de l'échantillon") {
                    $sampleCode = $general->removeCntrlCharsAndEncode($record[1]);
                    if (empty($sampleCode)) {
                        continue;
                    }
                    $infoFromFile[$sampleCode]['sampleCode'] = $sampleCode;
                    $infoFromFile[$sampleCode]['testedOn'] = $testedOn;
                    $infoFromFile[$sampleCode]['testedBy'] = $testedBy;
                } else if ($v == "Assay" || $v == "Test") {
                    if (empty($sampleCode)) {
                        continue;
                    }
                    $infoFromFile[$sampleCode]['assay'] = $general->removeCntrlCharsAndEncode($record[1]);
                } else if ($v == "Test Result" || $v == "Résultat du test") {
                    if (empty($sampleCode)) {
                        continue;
                    }

                    $parsedResult = (str_replace("|", "", strtoupper($general->removeCntrlCharsAndEncode($record[1]))));

                    if ($general->checkIfStringExists($parsedResult, array('not detected', 'notdetected')) !== false) {
                        $parsedResult = 'negative';
                    } else if ($general->checkIfStringExists($parsedResult, array('detected'))) {
                        $parsedResult = 'positive';
                    }
                    $infoFromFile[$sampleCode]['result'] = strtolower($parsedResult);
                }
            }
        }


        // echo "<pre>";
        // var_dump($infoFromFile);
        // echo "</pre>";
        // die;
        $inc = 0;
        foreach ($infoFromFile as $sampleCode => $d) {

            $data = array(
                'module' => 'eid',
                'lab_id' => base64_decode($_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'sample_tested_datetime' => $d['testedOn'],
                'sample_type' => 'S',
                'result_status' => '6',
                'import_machine_file_name' => $fileName,
                'result' => trim($d['result']),
            );

            if (empty($data['result'])) {
                $data['result_status'] = '1'; // 1= Hold
            }

            if (empty($batchCode)) {
                $data['batch_code'] = $newBatchCode;
                $data['batch_code_key'] = $maxBatchCodeKey;
            } else {
                $data['batch_code'] = $batchCode;
            }
            //get user name
            if (!empty($d['reviewBy'])) {

                /** @var UsersService $usersService */
                $usersService = ContainerRegistry::get(UsersService::class);
                $data['sample_review_by'] = $usersService->addUserIfNotExists($d['reviewBy']);
            }

            $query = "SELECT facility_id, eid_id, result
                        FROM form_eid
                        WHERE sample_code= ?";
            $vlResult = $db->rawQuery($query, array($sampleCode));

            if (!empty($vlResult) && !empty($sampleCode)) {
                if ($vlResult[0]['result'] != null && !empty($vlResult[0]['result'])) {
                    $data['sample_details'] = 'Result already exists';
                } else {
                    $data['result_status'] = '7';
                }
                $data['facility_id'] = $vlResult[0]['facility_id'];
            } else {
                $data['sample_details'] = 'New Sample';
            }
            //echo "<pre>";var_dump($data);echo "</pre>";continue;
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
    $action = $_SESSION['userName'] . ' imported a new test result with the sample code ' . $sampleCode;
    $resource = 'import-results-manually';
    $general->activityLog($eventType, $action, $resource);

    //new log for update in result
    if (isset($id) && $id > 0) {
        $data = array(
            'user_id' => $_SESSION['userId'],
            'vl_sample_id' => $id,
            'test_type' => 'covid19',
            'updated_on' => DateUtility::getCurrentDateTime(),
        );
        $db->insert("log_result_updates", $data);
    }
    header("Location:/import-result/imported-results.php");
} catch (Exception $exc) {

    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    $_SESSION['alertMsg'] = "Result file could not be imported. Please check if the file is of correct format.";
    header("Location:/import-result/addImportResult.php?t=" . base64_encode('eid'));
}

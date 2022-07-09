<?php

// File included in addImportResultHelper.php

use League\Csv\Reader;

try {

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

    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['resultFile']['name']);
    $fileName = str_replace(" ", "-", $fileName);
    $ranNumber = \Vlsm\Models\General::generateRandomString();
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception("Invalid file format.");
    }

    $fileName = $ranNumber . "." . $extension;

    if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results")) {
        mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results");
    }
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName)) {

        $file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        $mime_type = $file_info->buffer(file_get_contents(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName)); // e.g. gives "image/jpeg"

        $bquery = "SELECT MAX(batch_code_key) from batch_details";
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


        $reader = Reader::createFromPath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $fileName, 'r');
        $infoFromFile = array();
        foreach ($reader as $offset => $record) {
            //$newRow = array();
            foreach ($record as $o => $v) {
                //echo "<pre>";var_dump($record);echo "</pre><br><br><br>";
                $v = preg_replace('/[[:cntrl:]]/', '',  $v);
                if ($v == "End Time" || $v == "Heure de fin") {
                    $testedOn = preg_replace('/[[:cntrl:]]/', '',  $record[1]);
                    $testedOn = date('Y-m-d H:i', strtotime($testedOn));
                } elseif ($v == "User" || $v == 'Utilisateur') {
                    $testedBy = preg_replace('/[[:cntrl:]]/', '',  $record[1]);
                } else if ($v == "RESULT TABLE" || $v == "TABLEAU DE RÉSULTATS") {
                    $sampleCode = null;
                } else if ($v == "Sample ID" || $v == "N° Id de l'échantillon") {
                    $sampleCode = preg_replace('/[[:cntrl:]]/', '',  $record[1]);
                    if (empty($sampleCode)) continue;
                    $infoFromFile[$sampleCode]['sampleCode'] = $sampleCode;
                    $infoFromFile[$sampleCode]['testedOn'] = $testedOn;
                    $infoFromFile[$sampleCode]['testedBy'] = $testedBy;
                } else if ($v == "Assay" || $v == "Test") {
                    if (empty($sampleCode)) continue;
                    $infoFromFile[$sampleCode]['assay'] = preg_replace('/[[:cntrl:]]/', '',  $record[1]);
                } else if ($v == "Test Result" || $v == "Résultat du test") {
                    if (empty($sampleCode)) continue;
                    $parsedResult = (str_replace("SARS-CoV-2 ", "", preg_replace('/[[:cntrl:]]/', '',  $record[1])));
                    if($parsedResult == 'NÉGATIF' || $parsedResult == 'NÉGATIVE'){
                        $parsedResult = 'negative';
                    }else if($parsedResult == 'POSITIF' || $parsedResult == 'POSITIVE'){
                        $parsedResult = 'positive';
                    }
                    $infoFromFile[$sampleCode]['result'] = strtolower($parsedResult);
                    //echo "<pre>";var_dump($infoFromFile[$sampleCode]['result']);echo "</pre><br><br><br>";
                }
            }
        }


        // echo "<pre>";var_dump($infoFromFile);echo "</pre>";
        // die;
        $inc = 0;
        foreach ($infoFromFile as $sampleCode => $d) {

            $data = array(
                'module' => 'covid19',
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
                $usersModel = new \Vlsm\Models\Users();
                $data['sample_review_by'] = $usersModel->addUserIfNotExists($d['reviewBy']);
            }

            $query = "SELECT facility_id,covid19_id,result from form_covid19 where sample_code='" . $sampleCode . "'";
            $vlResult = $db->rawQuery($query);

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
                $data['result_imported_datetime'] = $general->getDateTime();
                $data['imported_by'] = $_SESSION['userId'];
                $id = $db->insert("temp_sample_import", $data);
            }
            $inc++;
        }
    }

    $_SESSION['alertMsg'] = "Result file imported successfully";
    //Add event log
    $eventType = 'import';
    $action = ucwords($_SESSION['userName']) . ' imported a new test result with the sample code ' . $sampleCode;
    $resource = 'import-results-manually';
    $general->activityLog($eventType, $action, $resource);

    //new log for update in result
    if (isset($id) && $id > 0) {
        $data = array(
            'user_id' => $_SESSION['userId'],
            'vl_sample_id' => $id,
            'test_type' => 'covid19',
            'updated_on' => $general->getDateTime(),
        );
        $db->insert("log_result_updates", $data);
    }
    header("location:/import-result/imported-results.php");
} catch (Exception $exc) {

    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    $_SESSION['alertMsg'] = "Result file could not be imported. Please check if the file is of correct format.";
    header("location:/import-result/addImportResult.php?t=" . base64_encode('covid19'));
}

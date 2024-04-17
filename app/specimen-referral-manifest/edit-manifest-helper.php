<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$packageTable = "package_details";
try {
    $selectedSample = explode(",", (string) $_POST['selectedSample']);
    $uniqueSampleId = array_unique($selectedSample);
    if (isset($_POST['packageCode']) && trim((string) $_POST['packageCode']) != "" && !empty($selectedSample)) {
        $lastId = $_POST['packageId'];
        $db->where('package_id', $lastId);
        $db->update($packageTable, array(
            'lab_id' => $_POST['testingLab'],
            'number_of_samples' => count($selectedSample),
            'package_status' => $_POST['packageStatus'],
            'last_modified_datetime' => DateUtility::getCurrentDateTime()
        ));

        if ($lastId > 0) {
            $value = array(
                'sample_package_id'   => null,
                'sample_package_code' => null
            );

            if ($_POST['module'] == 'vl') {
                $db->where('sample_package_id', $lastId);
                $db->update('form_vl', $value);
            } else if ($_POST['module'] == 'eid') {
                $db->where('sample_package_id', $lastId);
                $db->update('form_eid', $value);
            } else if ($_POST['module'] == 'covid19') {
                $db->where('sample_package_id', $lastId);
                $db->update('form_covid19', $value);
            } else if ($_POST['module'] == 'hepatitis') {
                $db->where('sample_package_id', $lastId);
                $db->update('form_hepatitis', $value);
            } else if ($_POST['module'] == 'tb') {
                $db->where('sample_package_id', $lastId);
                $db->update('form_tb', $value);
            } else if ($_POST['module'] == 'generic-tests') {
                $db->where('sample_package_id', $lastId);
                $db->update('form_generic', $value);
            }

            for ($j = 0; $j < count($selectedSample); $j++) {
                $value = array(
                    'sample_package_id'   => $lastId,
                    'sample_package_code' => $_POST['packageCode'],
                    'lab_id'    => $_POST['testingLab'],
                    'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                    'data_sync' => 0
                );
                if ($_POST['module'] == 'vl') {
                    $db->where('vl_sample_id', $uniqueSampleId[$j]);
                    $db->update('form_vl', $value);
                } else if ($_POST['module'] == 'eid') {
                    $db->where('eid_id', $uniqueSampleId[$j]);
                    $db->update('form_eid', $value);
                } else if ($_POST['module'] == 'covid19') {
                    $db->where('covid19_id', $uniqueSampleId[$j]);
                    $db->update('form_covid19', $value);
                } else if ($_POST['module'] == 'hepatitis') {
                    $db->where('hepatitis_id', $uniqueSampleId[$j]);
                    $db->update('form_hepatitis', $value);
                } else if ($_POST['module'] == 'tb') {
                    $db->where('tb_id', $uniqueSampleId[$j]);
                    $db->update('form_tb', $value);
                } else if ($_POST['module'] == 'generic-tests') {
                    $db->where('sample_id', $uniqueSampleId[$j]);
                    $db->update('form_generic', $value);
                }
            }
            $_SESSION['alertMsg'] = "Manifest details updated successfully";
        }
    }

    //Add event log
    $eventType = 'edit-manifest';
    $action = $_SESSION['userName'] . ' updated Sample Manifest details for ' . $_POST['packageCode'];
    $resource = 'specimen-manifest';

    $general->activityLog($eventType, $action, $resource);

    header("Location:view-manifests.php?t=" . ($_POST['module']));
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

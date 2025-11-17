<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_generic_sample_types";
$sampleTypeId = (int) base64_decode((string) $_POST['sampleTypeId']);
$_POST['sampleTypeName'] = trim((string) $_POST['sampleTypeName']);
try {
    if (isset($_POST['sampleTypeName']) && ($_POST['sampleTypeName'] !== '' && $_POST['sampleTypeName'] !== '0')) {

        $data = ['sample_type_name' => $_POST['sampleTypeName'], 'sample_type_code' => $_POST['sampleTypeCode'], 'sample_type_status' => $_POST['sampleTypeStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        if ($sampleTypeId !== 0) {
            $db->where('sample_type_id', $sampleTypeId);
            $lastId = $db->update($tableName, $data);
            $_SESSION['alertMsg'] = _translate("Sample type updated successfully");
            if ($lastId > 0) {
                $general->activityLog('Sample Type', $_SESSION['userName'] . ' updated sample type for ' . $_POST['sampleTypeName'], 'generic-sample-types');
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Sample type added successfully");
                $general->activityLog('Sample Type', $_SESSION['userName'] . ' added new sample type for ' . $_POST['sampleTypeName'], 'generic-sample-types');
            }
        }
    }
    error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    header("location:generic-sample-type.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}

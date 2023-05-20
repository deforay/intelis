<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\VlService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var VlService $vlObj */
$vlObj = ContainerRegistry::get(VlService::class);

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

$sampleQuery = "SELECT vl_sample_id,
                sample_collection_date,
                sample_package_code,
                province_id,
                sample_code
                FROM form_vl where vl_sample_id IN (?)";
$sampleResult = $db->rawQuery($sampleQuery, [$_POST['sampleId']]);
$status = 0;
foreach ($sampleResult as $sampleRow) {

    $provinceCode = null;

    if (isset($sampleRow['province_id']) && !empty($sampleRow['province_id'])) {
        $provinceQuery = "SELECT * FROM geographical_divisions WHERE geo_id = ?";
        $provinceResult = $db->rawQueryOne($provinceQuery, [$sampleRow['province_id']]);
        $provinceCode = $provinceResult['geo_code'];
    }
    if (isset($_POST['testDate']) && !empty($_POST['testDate'])) {
        $testDate = explode(" ", $_POST['testDate']);
        $_POST['testDate'] = DateUtility::isoDateFormat($testDate[0]);
        $_POST['testDate'] .= " " . $testDate[1];
    } else {
        $_POST['testDate'] = null;
    }
    // ONLY IF SAMPLE CODE IS NOT ALREADY GENERATED
    if ($sampleRow['sample_code'] == null || $sampleRow['sample_code'] == '' || $sampleRow['sample_code'] == 'null') {

        $sampleJson = $vlObj->generateVLSampleID($provinceCode, DateUtility::humanReadableDateFormat($sampleRow['sample_collection_date']));
        $sampleData = json_decode($sampleJson, true);
        //$vldata['sample_code'] = $sampleData['sampleCode'];
        $vldata['sample_code'] = $sampleData['sampleCode'];
        $vldata['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $vldata['sample_code_key'] = $sampleData['sampleCodeKey'];
        $vldata['result_status'] = 6;
        $vldata['data_sync'] = 0;

        $vldata['last_modified_by'] = $_SESSION['userId'];
        $vldata['last_modified_datetime'] = DateUtility::getCurrentDateTime();

        if (!empty($_POST['testDate'])) {
            $vldata['sample_tested_datetime'] = null;
            $vldata['sample_received_at_vl_lab_datetime'] = $_POST['testDate'];
        }
        $db = $db->where('vl_sample_id', $sampleRow['vl_sample_id']);
        $id = $db->update('form_vl', $vldata);
        if ($id > 0) {
            $status = $id;
        }
    }
}
echo $status;

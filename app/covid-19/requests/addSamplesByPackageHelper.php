<?php

use App\Services\Covid19Service;
use App\Services\CommonService;
use App\Utilities\DateUtils;

$general = new CommonService();
$covid19Obj = new Covid19Service();


$sampleQuery = "SELECT covid19_id, sample_collection_date, sample_package_code, province_id, sample_code FROM form_covid19 where covid19_id IN (" . $_POST['sampleId'] . ") ORDER BY covid19_id";
$sampleResult = $db->query($sampleQuery);
$status = 0;
foreach ($sampleResult as $sampleRow) {

    $provinceCode = null;
    if (isset($sampleRow['province_id']) && !empty($sampleRow['province_id'])) {
        $provinceQuery = "SELECT * FROM geographical_divisions WHERE geo_id= " . $sampleRow['province_id'];
        $provinceResult = $db->rawQueryOne($provinceQuery);
        $provinceCode = $provinceResult['geo_code'];
    }
    if (isset($_POST['testDate']) && !empty($_POST['testDate'])) {
        $testDate = explode(" ", $_POST['testDate']);
        $_POST['testDate'] = DateUtils::isoDateFormat($testDate[0]);
        $_POST['testDate'] .= " " . $testDate[1];
    } else {
        $_POST['testDate'] = null;
    }
    // ONLY IF SAMPLE CODE IS NOT ALREADY GENERATED
    if ($sampleRow['sample_code'] == null || $sampleRow['sample_code'] == '' || $sampleRow['sample_code'] == 'null') {

        $sampleJson = $covid19Obj->generateCovid19SampleCode($provinceCode, DateUtils::humanReadableDateFormat($sampleRow['sample_collection_date']));
        $sampleData = json_decode($sampleJson, true);
        $covid19Data = [];
        $covid19Data['sample_code'] = $sampleData['sampleCode'];
        $covid19Data['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $covid19Data['sample_code_key'] = $sampleData['sampleCodeKey'];
        $covid19Data['result_status'] = 6;
        $covid19Data['data_sync'] = 0;
        if (!empty($_POST['testDate'])) {
            $covid19Data['sample_tested_datetime'] = null;
            $covid19Data['sample_received_at_vl_lab_datetime'] = $_POST['testDate'];
        }
        $covid19Data['last_modified_by'] = $_SESSION['userId'];
        $covid19Data['last_modified_datetime'] = DateUtils::getCurrentDateTime();

        $db = $db->where('covid19_id', $sampleRow['covid19_id']);
        $id = $db->update('form_covid19', $covid19Data);
        if ($id > 0) {
            $status = $id;
        }
    }
}
echo $status;

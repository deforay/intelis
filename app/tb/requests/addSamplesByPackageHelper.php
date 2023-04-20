<?php


use App\Models\General;
use App\Models\Tb;
use App\Utilities\DateUtils;

$general = new General();
$tbObj = new Tb();


$sampleQuery = "SELECT tb_id, sample_collection_date, sample_package_code, province_id, sample_code FROM form_tb where tb_id IN (" . $_POST['sampleId'] . ") ORDER BY tb_id";
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

        $sampleJson = $tbObj->generatetbSampleCode($provinceCode, DateUtils::humanReadableDateFormat($sampleRow['sample_collection_date']));
        $sampleData = json_decode($sampleJson, true);
        $tbData = array();
        $tbData['sample_code'] = $sampleData['sampleCode'];
        $tbData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $tbData['sample_code_key'] = $sampleData['sampleCodeKey'];
        if(!empty($_POST['testDate'])){
            $tbData['sample_tested_datetime'] = null;
            $tbData['sample_received_at_vl_lab_datetime'] = $_POST['testDate'];
        }
        $tbData['result_status'] = 6;
        $tbData['data_sync'] = 0;
        $tbData['last_modified_by'] = $_SESSION['userId'];
        $tbData['last_modified_datetime'] = DateUtils::getCurrentDateTime();

        $db = $db->where('tb_id', $sampleRow['tb_id']);
        $id = $db->update('form_tb', $tbData);
        if ($id > 0) {
            $status = $id;
        }
    }
}
echo $status;

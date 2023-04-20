<?php


use App\Models\General;
use App\Models\Hepatitis;
use App\Utilities\DateUtils;

$general = new General();
$hepatitisObj = new Hepatitis();


$sampleQuery = "SELECT hepatitis_id, hepatitis_test_type, sample_collection_date, sample_package_code, province_id, sample_code FROM form_hepatitis where hepatitis_id IN (" . $_POST['sampleId'] . ") ORDER BY hepatitis_id";
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

        $sampleJson = $hepatitisObj->generatehepatitisSampleCode($sampleRow['hepatitis_test_type'], $provinceCode, DateUtils::humanReadableDateFormat($sampleRow['sample_collection_date']));
        $sampleData = json_decode($sampleJson, true);
        $hepatitisData = [];
        $hepatitisData['sample_code'] = $sampleData['sampleCode'];
        $hepatitisData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $hepatitisData['sample_code_key'] = $sampleData['sampleCodeKey'];
        $hepatitisData['result_status'] = 6;
        $hepatitisData['data_sync'] = 0;
        $hepatitisData['last_modified_datetime'] = DateUtils::getCurrentDateTime();
        if(!empty($_POST['testDate'])){
            $hepatitisData['sample_tested_datetime'] = null;
            $hepatitisData['sample_received_at_vl_lab_datetime'] = $_POST['testDate'];
        }        
        $hepatitisData['last_modified_by'] = $_SESSION['userId'];
        $hepatitisData['last_modified_datetime'] = DateUtils::getCurrentDateTime();

        $db = $db->where('hepatitis_id', $sampleRow['hepatitis_id']);
        $id = $db->update('form_hepatitis', $hepatitisData);
        if ($id > 0) {
            $status = $id;
        }
    }
}
echo $status;

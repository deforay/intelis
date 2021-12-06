<?php

#require_once('../../startup.php');



$general = new \Vlsm\Models\General();
$covid19Obj = new \Vlsm\Models\Covid19();


$sampleQuery = "SELECT tb_id, sample_collection_date, sample_package_code, province_id, sample_code FROM form_tb where tb_id IN (" . $_POST['sampleId'] . ") ORDER BY tb_id";
$sampleResult = $db->query($sampleQuery);
$status = 0;
foreach ($sampleResult as $sampleRow) {

    $provinceCode = null;
    if (isset($sampleRow['province_id']) && !empty($sampleRow['province_id'])) {
        $provinceQuery = "SELECT * FROM province_details WHERE province_id= " . $sampleRow['province_id'];
        $provinceResult = $db->rawQueryOne($provinceQuery);
        $provinceCode = $provinceResult['province_code'];
    }


    // ONLY IF SAMPLE CODE IS NOT ALREADY GENERATED
    if ($sampleRow['sample_code'] == null || $sampleRow['sample_code'] == '' || $sampleRow['sample_code'] == 'null') {

        $sampleJson = $covid19Obj->generateCovid19SampleCode($provinceCode, $general->humanDateFormat($sampleRow['sample_collection_date']));
        $sampleData = json_decode($sampleJson, true);
        $covid19Data = array();
        $covid19Data['sample_code'] = $sampleData['sampleCode'];
        $covid19Data['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $covid19Data['sample_code_key'] = $sampleData['sampleCodeKey'];
        $covid19Data['result_status'] = 6;
        $covid19Data['last_modified_by'] = $_SESSION['userId'];
        $covid19Data['last_modified_datetime'] = $general->getDateTime();

        $db = $db->where('tb_id', $sampleRow['tb_id']);
        $id = $db->update('form_tb', $covid19Data);
        if ($id > 0) {
            $status = $id;
        }
    }
}
echo $status;

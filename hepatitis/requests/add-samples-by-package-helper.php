<?php

#require_once('../../startup.php');



$general = new \Vlsm\Models\General($db);
$hepatitisObj = new \Vlsm\Models\Hepatitis($db);


$sampleQuery = "SELECT hepatitis_id, sample_collection_date, sample_package_code, province_id, sample_code FROM form_hepatitis where hepatitis_id IN (" . $_POST['sampleId'] . ") ORDER BY hepatitis_id";
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

        $sampleJson = $hepatitisObj->generatehepatitisSampleCode($provinceCode, $general->humanDateFormat($sampleRow['sample_collection_date']));
        $sampleData = json_decode($sampleJson, true);
        $hepatitisData = array();
        $hepatitisData['sample_code'] = $sampleData['sampleCode'];
        $hepatitisData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $hepatitisData['sample_code_key'] = $sampleData['sampleCodeKey'];
        $hepatitisData['result_status'] = 6;
        $hepatitisData['last_modified_by'] = $_SESSION['userId'];
        $hepatitisData['last_modified_datetime'] = $general->getDateTime();

        $db = $db->where('hepatitis_id', $sampleRow['hepatitis_id']);
        $id = $db->update('form_hepatitis', $hepatitisData);
        if ($id > 0) {
            $status = $id;
        }
    }
}
echo $status;

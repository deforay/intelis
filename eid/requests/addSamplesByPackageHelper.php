<?php





$general = new \Vlsm\Models\General();
$eidObj = new \Vlsm\Models\Eid();


$sampleQuery = "SELECT eid_id, sample_collection_date, sample_package_code, province_id, sample_code FROM form_eid where eid_id IN (" . $_POST['sampleId'] . ") ORDER BY eid_id";
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

        $sampleJson = $eidObj->generateEIDSampleCode($provinceCode, $general->humanDateFormat($sampleRow['sample_collection_date']));
        $sampleData = json_decode($sampleJson, true);

        $eidData['sample_code'] = $sampleData['sampleCode'];
        $eidData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $eidData['sample_code_key'] = $sampleData['sampleCodeKey'];
        $eidData['result_status'] = 6;
        $eidData['last_modified_by'] = $_SESSION['userId'];
        $eidData['last_modified_datetime'] = $general->getDateTime();

        $db = $db->where('eid_id', $sampleRow['eid_id']);
        $id = $db->update('form_eid', $eidData);
        if ($id > 0) {
            $status = $id;
        }
    }
}
echo $status;

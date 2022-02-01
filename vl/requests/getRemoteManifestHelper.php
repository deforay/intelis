<?php



$general = new \Vlsm\Models\General();
$sampleData = array();
$sampleQuery = 'SELECT vl_sample_id FROM vl_request_form where sample_code IS NULL AND (sample_package_code LIKE "' . $_POST['samplePackageCode'] . '" OR remote_sample_code LIKE "' . $_POST['samplePackageCode'] . '")';
$sampleResult = $db->query($sampleQuery);
foreach ($sampleResult as $sampleRow) {
    array_push($sampleData, $sampleRow['vl_sample_id']);
}
echo implode(',', $sampleData);

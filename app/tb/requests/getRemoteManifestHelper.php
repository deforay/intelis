<?php



$general = new \Vlsm\Models\General();
$sampleData = array();
$sampleQuery = 'SELECT tb_id FROM form_tb where sample_code IS NULL AND (sample_package_code LIKE "' . $_POST['samplePackageCode'] . '" OR remote_sample_code LIKE "' . $_POST['samplePackageCode'] . '")';
$sampleResult = $db->query($sampleQuery);
foreach ($sampleResult as $sampleRow) {
    array_push($sampleData, $sampleRow['tb_id']);
}
echo implode(',', $sampleData);

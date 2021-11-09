<?php
#require_once('../../startup.php');


$general = new \Vlsm\Models\General();
$sampleData = array();
$sampleQuery = 'SELECT hepatitis_id FROM form_hepatitis where sample_code IS NULL AND (sample_package_code LIKE "' . $_POST['samplePackageCode'] . '" OR remote_sample_code LIKE "' . $_POST['samplePackageCode'] . '")';
$sampleResult = $db->query($sampleQuery);
foreach ($sampleResult as $sampleRow) {
    array_push($sampleData, $sampleRow['hepatitis_id']);
}
echo implode(',', $sampleData);

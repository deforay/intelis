<?php


use App\Models\General;

$general = new General();
$sampleData = array();
$sampleQuery = 'SELECT hepatitis_id FROM form_hepatitis WHERE sample_code IS NULL AND (sample_package_code LIKE "' . $_POST['samplePackageCode'] . '" OR remote_sample_code LIKE "' . $_POST['samplePackageCode'] . '")';
$sampleResult = $db->query($sampleQuery);
$sampleData = array_column($sampleResult, 'hepatitis_id');
echo implode(',', $sampleData);

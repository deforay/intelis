<?php


use App\Services\CommonService;

$general = new CommonService();
$sampleData = [];
$sampleQuery = 'SELECT covid19_id FROM form_covid19 WHERE sample_code IS NULL AND (sample_package_code LIKE "' . $_POST['samplePackageCode'] . '" OR remote_sample_code LIKE "' . $_POST['samplePackageCode'] . '")';
$sampleResult = $db->query($sampleQuery);
$sampleData = array_column($sampleResult, 'covid19_id');
echo implode(',', $sampleData);

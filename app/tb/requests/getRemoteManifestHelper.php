<?php


use App\Services\CommonService;

$general = new CommonService();
$sampleData = [];
$sampleQuery = 'SELECT tb_id FROM form_tb WHERE sample_code IS NULL AND (sample_package_code LIKE "' . $_POST['samplePackageCode'] . '" OR remote_sample_code LIKE "' . $_POST['samplePackageCode'] . '")';
$sampleResult = $db->query($sampleQuery);
$sampleData = array_column($sampleResult, 'tb_id');
echo implode(',', $sampleData);

<?php

use App\Models\General;

$general = new General();
$sampleData = array();
$sampleQuery = 'SELECT vl_sample_id FROM form_vl WHERE sample_code IS NULL AND (sample_package_code LIKE "' . $_POST['samplePackageCode'] . '" OR remote_sample_code LIKE "' . $_POST['samplePackageCode'] . '")';
$sampleResult = $db->query($sampleQuery);
$sampleData = array_column($sampleResult, 'vl_sample_id');
echo implode(',', $sampleData);

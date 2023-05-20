<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

$sampleData = [];
$sampleQuery = "SELECT tb_id FROM form_tb
                WHERE sample_code IS NULL
                AND (sample_package_code LIKE ? OR remote_sample_code LIKE ?)";
$sampleResult = $db->rawQuery($sampleQuery, [$_POST['samplePackageCode'], $_POST['samplePackageCode']]);
$sampleData = array_column($sampleResult, 'tb_id');
echo implode(',', $sampleData);

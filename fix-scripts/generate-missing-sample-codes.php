<?php

use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;


require_once(__DIR__ . '/../bootstrap.php');

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var VlService $vlObj */
$vlObj = ContainerRegistry::get(VlService::class);


$sampleQuery = "SELECT vl_sample_id,
                sample_collection_date,
                sample_package_code,
                province_id,
                sample_code, remote_sample_code FROM `form_vl`
                WHERE `sample_code` IS NULL AND `remote_sample_code` IS NULL";

$sampleResult = $db->rawQuery($sampleQuery);

foreach ($sampleResult as $sampleRow) {
    if ($sampleRow['sample_code'] == NULL || $sampleRow['sample_code'] == '' || $sampleRow['sample_code'] == 'null' || $sampleRow['remote_sample_code'] == NULL || $sampleRow['remote_sample_code'] == '' || $sampleRow['remote_sample_code'] == 'null') {
        $provinceCode = null;

        if (!empty($sampleRow['province_id'])) {
            $provinceQuery = "SELECT * FROM geographical_divisions WHERE geo_id = ?";
            $provinceResult = $db->rawQueryOne($provinceQuery, [$sampleRow['province_id']]);
            $provinceCode = $provinceResult['geo_code'];
        }
        $sampleCodeParams = [];
        $sampleCodeParams['sampleCollectionDate'] = DateUtility::humanReadableDateFormat($sampleRow['sample_collection_date'] ?? '');
        $sampleCodeParams['provinceCode'] = $provinceCode ?? null;

        $sampleJson = $vlObj->getSampleCode($sampleCodeParams);
        $sampleData = json_decode($sampleJson, true);
        $vldata['sample_code'] = $sampleData['sampleCode'];
        $vldata['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $vldata['sample_code_key'] = $sampleData['sampleCodeKey'];
        $vldata['unique_id'] = $general->generateUUID();

        $db->where('vl_sample_id', $sampleRow['vl_sample_id']);
        $id = $db->update('form_vl', $vldata);
    }
}

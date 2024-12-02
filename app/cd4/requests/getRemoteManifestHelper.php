<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$sampleData = [];
$sampleCode = $_POST['samplePackageCode'];
$sampleQuery = "SELECT vl.cd4_id,vl.form_attributes
                    FROM form_cd4 as vl
                    WHERE vl.sample_package_code IN
                    (
                        '$sampleCode',
                        (SELECT DISTINCT sample_package_code FROM form_cd4 WHERE remote_sample_code LIKE '$sampleCode')
                    )";

$sampleResult = $db->rawQuery($sampleQuery);

$noOfSamples=0;
// Get number of samples
$formAttributes = json_decode($sampleResult[0]['form_attributes']);
if(isset($formAttributes->manifest)){
    $manifest=json_decode($formAttributes->manifest);
    if(isset($manifest->number_of_samples)){
        $noOfSamples=$manifest->number_of_samples;
    }
}

$sampleData = array_column($sampleResult, 'cd4_id');

$count=sizeof($sampleData);
if($noOfSamples > 0 && $count == $noOfSamples) {
    echo implode(',', $sampleData);
}

<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

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
$sampleQuery = "SELECT vl.vl_sample_id,vl.form_attributes
                    FROM form_vl as vl
                    WHERE vl.sample_package_code IN
                    (
                        '$sampleCode',
                        (SELECT DISTINCT sample_package_code FROM form_vl WHERE remote_sample_code LIKE '$sampleCode')
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

$sampleData = array_column($sampleResult, 'vl_sample_id');

$count=sizeof($sampleData);
if($noOfSamples>0){
    if($count==$noOfSamples){
        echo implode(',', $sampleData);
    }
}


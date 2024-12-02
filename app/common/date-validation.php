<?php

use App\Registries\AppRegistry;
use App\Utilities\DateUtility;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


    $validDateCheck = DateUtility::isDateValid($_POST['sampleCollectionDate']);
    if(($_POST['allowFutureDates']) == "false"){
        $futureDateCheck = DateUtility::hasFutureDates($_POST['sampleCollectionDate']);
    }

   if($validDateCheck == true && (isset($futureDateCheck) && $futureDateCheck == false))
        echo "0";
    else
        echo "1";


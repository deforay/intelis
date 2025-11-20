<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


$validDateCheck = DateUtility::isDateValid($_POST['sampleCollectionDate'] ?? null);
if (($_POST['allowFutureDates']) == "false") {
    $futureDateCheck = DateUtility::hasFutureDates($_POST['sampleCollectionDate']);
}

if ($validDateCheck == true && (isset($futureDateCheck) && $futureDateCheck == false)) {
    echo "0";
} else {
    echo "1";
}

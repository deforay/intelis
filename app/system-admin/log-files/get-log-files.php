<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$date = DateUtility::isoDateFormat($_GET['date']);
$file = ROOT_PATH . '/logs/' . $date . '-logfile.log';

if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo 'No files found';
}

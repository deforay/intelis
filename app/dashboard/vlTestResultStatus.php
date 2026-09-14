<?php

use App\Registries\AppRegistry;
use App\Services\SampleStatusDetailsService;

// Replaced by /reports/sample-status-details.php, which lists the samples
// behind a status slice for the module the pie belongs to. This page only ever
// read VL samples, so an old link or bookmark is sent to the VL listing for the
// same status and collection date.

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$status = (int) base64_decode((string) ($_GET['id'] ?? ''));
$collectionDate = (string) base64_decode((string) ($_GET['d'] ?? ''));

header('Location: ' . SampleStatusDetailsService::pageUrl(
    'vl',
    $status > 0 ? $status : null,
    ['sampleCollectionDate' => $collectionDate]
));

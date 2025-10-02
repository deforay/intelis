<?php

// app/specimen-referral-manifest/verify-manifest.php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;


/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);


/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$verifyManifest = $testRequestsService->verifyManifestHashWithRemote(
    manifestCode: $_POST['manifestCode'],
    testType: $_POST['testType'],
);

echo json_encode($verifyManifest);;

echo $verifyManifest['verified'];



<?php

// app/specimen-referral-manifest/verify-manifest.php
use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\TestsService;
use App\Services\TestRequestsService;

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);


/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$verifyManifest = $testRequestsService->verifyManifestHashWithRemote(
    manifestCode: $_POST['manifestCode'],
    testType: $_POST['testType'],
);

// A code from another module's manifest: name that module and its page so the
// toast can send the user there.
if (!empty($verifyManifest['manifestTestType'])) {
    $verifyManifest['manifestPage'] = TestsService::getManifestActivationPage($verifyManifest['manifestTestType']);
}

echo JsonUtility::encodeUtf8Json($verifyManifest);

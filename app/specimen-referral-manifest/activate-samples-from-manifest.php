<?php

/**
 * Shared "activate samples from manifest" handler.
 *
 * Single endpoint for every test module, POSTed to directly by the manifest
 * grid page (see _add-samples-from-manifest-body.php). The page sends
 * testType + manifestCode + sampleReceivedOn, and we hand off to
 * TestRequestsService::activateSamplesFromManifest(). The test type is read
 * from the POST body, so no per-module wrapper files are needed.
 *
 * Each module used to differ on a single thing: which global-config keys hold
 * the sample-code format/prefix for that test type. That mapping now lives here,
 * keyed by the POSTed testType.
 */

use Psr\Http\Message\ServerRequestInterface;
use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;
use App\Abstracts\AbstractTestService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$testType = $_POST['testType'];
$manifestCode = $_POST['manifestCode'];

$serviceClass = TestsService::getTestServiceClass($testType);

/** @var AbstractTestService $testTypeService */
$testTypeService = ContainerRegistry::get($serviceClass);

$globalConfig = $general->getGlobalConfig();

// Per-test-type prefix for the sample-code global-config keys. VL uses the bare
// keys (sample_code / sample_code_prefix); every other module namespaces them.
// Note generic-tests maps to 'generic_' (not 'generic-tests_').
$configKeyPrefixMap = [
    'vl'            => '',
    'eid'           => 'eid_',
    'tb'            => 'tb_',
    'covid19'       => 'covid19_',
    'cd4'           => 'cd4_',
    'generic-tests' => 'generic_',
    'hepatitis'     => 'hepatitis_',
];
$configKeyPrefix = $configKeyPrefixMap[$testType] ?? '';

// generic-tests has no fixed short code (the real prefix is resolved per sample
// inside activateSamplesFromManifest), so it falls back to 'T'.
$defaultPrefix = $testType === 'generic-tests' ? 'T' : $testTypeService->shortCode;

$sampleCodeFormat = $globalConfig["{$configKeyPrefix}sample_code"] ?? 'MMYY';
$prefix = $globalConfig["{$configKeyPrefix}sample_code_prefix"] ?? $defaultPrefix;

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

echo $testRequestsService->activateSamplesFromManifest($testType, $manifestCode, $sampleCodeFormat, $prefix);

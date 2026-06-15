<?php

use App\Services\ApiService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\STS\TokensService;
use Psr\Http\Message\ServerRequestInterface;
use App\Registries\ContainerRegistry;

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);

$payload = [];

try {
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');

    // Throttle token minting per source IP. The mint gate (X-API-KEY) is derivable
    // from the domain, so cap how fast anyone can ask for tokens. Legitimate LIS
    // instances mint rarely (createToken returns a cached token while valid), so
    // this limit is generous and only bites abuse/flooding.
    if (\App\Utilities\RateLimitUtility::exceeded('get-token:' . \App\Utilities\RateLimitUtility::clientIp(), 30, 60)) {
        throw new SystemException('Too many token requests; please retry shortly.', 429);
    }

    $data = $apiService->getJsonFromRequest($request, true);

    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $labId = $data['labId'] ?? null;

    if (empty($labId)) {
        throw new SystemException('Lab ID is missing in the request', 400);
    }
    $labId = (int) $labId;

    // Authorize the mint request, strongest proof first. Both are automatic and the
    // existing fleet (which only sends X-API-KEY) keeps working unchanged:
    //   1. Possession proof -- the LIS returns its current sts_token in X-STS-Token,
    //      so a lab that already holds its token authenticates with that token rather
    //      than the shared, domain-derivable key.
    //   2. Legacy X-API-KEY  -- the domain-derived key (unchanged behaviour), so older
    //      LIS instances and brand-new installs still work.
    $authorized = false;

    $presentedStsToken = trim((string) $request->getHeaderLine('X-STS-Token'));
    if ($presentedStsToken !== '' && $stsTokensService->tokenBelongsToFacility($presentedStsToken, $labId)) {
        $authorized = true;
    }

    if (!$authorized) {
        $apiKey = $request->getHeaderLine('X-API-Key');
        $intelisSyncApiKey = $general->getIntelisSyncAPIKey();
        if (
            !empty($apiKey)
            && !empty($intelisSyncApiKey)
            && hash_equals((string) $intelisSyncApiKey, (string) $apiKey)
        ) {
            $authorized = true;
        }
    }

    if (!$authorized) {
        throw new SystemException('Unauthorized: Invalid API Key', 401);
    }

    $token = $stsTokensService->createToken($labId);

    $payload = [
        'status' => 'success',
        'token' => $token
    ];
} catch (Throwable $e) {
    $payload = [
        'status' => 'error',
        'error' => _translate('Unable to process the request')
    ];

    LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception' => $e,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stacktrace' => $e->getTraceAsString()
    ]);
    throw new SystemException($e->getMessage(), $e->getCode(), $e);
}

$general->addApiTracking(
    $transactionId,
    'system',
    0,
    'get-token',
    null,
    $_SERVER['REQUEST_URI'],
    JsonUtility::encodeUtf8Json($data),
    $payload,
    'json',
    $labId
);

echo ApiService::generateJsonResponse($payload, $request);

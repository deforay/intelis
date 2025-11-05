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
use Laminas\Diactoros\ServerRequest;
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
    /** @var ServerRequest $request */
    $request = AppRegistry::get('request');

    // Retrieve the API key from the request header
    $apiKey = $request->getHeaderLine('X-API-Key');

    // Get the expected API key from the environment
    $intelisSyncApiKey = $general->getIntelisSyncAPIKey();

    // Check if the API key is missing or doesn't match
    if (empty($apiKey) || $apiKey !== $intelisSyncApiKey) {
        throw new SystemException('Unauthorized: Invalid API Key', 401);
    }

    $data = $apiService->getJsonFromRequest($request, true);

    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $labId = $data['labId'] ?? null;

    if (empty($labId)) {
        throw new SystemException('Lab ID is missing in the request', 400);
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

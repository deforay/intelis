<?php
// /remote/v2/results.php -- receiver for results-sender.php
use App\Services\ApiService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\STS\TokensService;
use App\Services\STS\ResultsService as STSResultsService;
use App\Registries\ContainerRegistry;

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var STSResultsService $stsResultsService */
$stsResultsService = ContainerRegistry::get(STSResultsService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);

try {
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $data = $apiService->getJsonFromRequest($request, true);
    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $authToken = ApiService::extractBearerToken($request);

    $labId = $data['labId'] ?? null;

    $isSilent = (bool) ($data['silent'] ?? false);

    if (empty($labId)) {
        throw new SystemException('Lab ID is missing in the request', 400);
    }

    $token = $stsTokensService->validateToken($authToken, $labId);

    if ($token === false || empty($token)) {
        throw new SystemException('Unauthorized Access', 401);
    }

    $testType = $data['testType'] ?? null;

    if (empty($testType)) {
        throw new SystemException('Test Type is missing in the request', 400);
    }

    $sampleCodes = $stsResultsService->receiveResults($testType, json_encode($data), $isSilent);

    $payload = JsonUtility::encodeUtf8Json($sampleCodes);

    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'results', $testType, $_SERVER['REQUEST_URI'], $data, $payload, 'json', $labId);
    $general->updateResultSyncDateTime($testType, $facilityIds, $labId);
} catch (Throwable $e) {
    $payload = json_encode([]);

    LoggerUtility::logError($e->getMessage(), [
        'lab' => $labId,
        'transactionId' => $transactionId,
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}

echo ApiService::generateJsonResponse($payload, $request);

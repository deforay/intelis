<?php
// /remote/v2/results.php -- receiver for results-sender.php
use Psr\Http\Message\ServerRequestInterface;
use App\Services\ApiService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\STS\TokensService;
use App\Registries\ContainerRegistry;
use App\Services\STS\ResultsService as STSResultsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
$db->ensureConnection();

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var STSResultsService $stsResultsService */
$stsResultsService = ContainerRegistry::get(STSResultsService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);

try {
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');

    // Parse JSON (handles gzip/deflate per your ApiService)
    $data = $apiService->getJsonFromRequest($request, true);
    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $authToken = ApiService::extractBearerToken($request);

    $labId = $data['labId'] ?? null;
    $isSilent = (bool)($data['silent'] ?? false);
    $testType = $data['testType'] ?? null;

    if (empty($labId)) {
        throw new SystemException('Lab ID is missing in the request', 400);
    }
    if (empty($testType)) {
        throw new SystemException('Test Type is missing in the request', 400);
    }

    $token = $stsTokensService->validateToken($authToken, $labId);
    if (!$token) {
        throw new SystemException('Unauthorized Access', 401);
    }

    $dataInJsonFormat = JsonUtility::encodeUtf8Json($data);



    // Manifests if any
    $manifestsStats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
    if (!empty($data['manifests']) && is_array($data['manifests'])) {
        // Process manifests for this module (TB for now; later others will reuse the same call)
        $manifestsStats = $stsResultsService->receiveReferralManifests($testType, $data['manifests']);
        $payload['manifests'] = $manifestsStats;
    }


    // Process and get array of sample codes
    $payload = $stsResultsService->receiveResults($testType, $dataInJsonFormat, $isSilent) ?? [];
    $resultCount = count($payload['results'] ?? []);

    // Tracking
    $general->addApiTracking(
        $transactionId,
        'intelis-system',
        $resultCount,
        'results',
        $testType,
        $_SERVER['REQUEST_URI'] ?? '',
        $dataInJsonFormat,
        JsonUtility::encodeUtf8Json($payload),
        'json',
        $labId
    );

    // If you have facility IDs to bump sync time for, pass them here.
    // If not available from receiveResults, skip this (prevents undefined var).
    if (!empty($data['facilityIds']) && is_array($data['facilityIds'])) {
        $general->updateResultSyncDateTime($testType, $data['facilityIds'], $labId);
    }

    echo ApiService::generateJsonResponse($payload, $request);
} catch (Throwable $e) {
    // Optional user-facing safe message, read by ErrorResponseGenerator in prod
    $_SESSION['errorDisplayMessage'] = _translate('Unable to process the results');

    // Log with context (guard undefineds)
    LoggerUtility::logError($e->getMessage(), [
        'lab'           => $labId ?? null,
        'transactionId' => $transactionId ?? null,
        'last_db_error' => isset($db) ? $db->getLastError() : null,
        'last_db_query' => isset($db) ? $db->getLastQuery() : null,
        'file'          => $e->getFile(),
        'line'          => $e->getLine(),
        'trace'         => $e->getTraceAsString(),
    ]);

    // Rethrow so ErrorResponseGenerator returns structured JSON + status
    throw new SystemException($e->getMessage(), ($e->getCode() ?: 500), $e);
}

<?php
// /remote/v2/results.php -- receiver for results-sender.php
use Psr\Http\Message\ServerRequestInterface;
use App\Services\ApiService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Utilities\ResultSyncAcknowledgement;
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
    $procStart = microtime(true);

    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $contentLength = (int) ($request->getHeaderLine('Content-Length') ?: 0);

    // Parse JSON (handles gzip/deflate per your ApiService)
    $data = $apiService->getJsonFromRequest($request, true);
    $apiRequestId = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $authToken = ApiService::extractBearerToken($request);

    $labId = $data['labId'] ?? null;
    $isSilent = (bool) ($data['silent'] ?? false);
    $testType = $data['testType'] ?? null;
    // Newer labs ask for their own identifiers back; older labs read sample codes.
    $ackByUniqueId = ($data['ackFormat'] ?? null) === ResultSyncAcknowledgement::UNIQUE_ID;

    if (empty($labId)) {
        throw new SystemException('Lab ID is missing in the request', 400);
    }
    // Labs send it as a number or a numeric string. Anything else is a malformed
    // request, not a server error: it failed as a TypeError further down and came
    // back 500, which reads as the STS being down.
    if (filter_var($labId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw new SystemException('Lab ID in the request is not a lab id', 400);
    }
    if (empty($testType)) {
        throw new SystemException('Test Type is missing in the request', 400);
    }

    $token = $stsTokensService->validateToken($authToken, $labId);
    if (!$token) {
        throw new SystemException(
            'Unauthorized Access on results sync: ' . $stsTokensService->getLastValidationFailure(),
            401
        );
    }

    $dataInJsonFormat = JsonUtility::encodeUtf8Json($data);



    // Manifests if any
    $manifestsStats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
    if (!empty($data['manifests']) && is_array($data['manifests'])) {
        // Process manifests for this module (TB for now; later others will reuse the same call)
        $manifestsStats = $stsResultsService->receiveReferralManifests($testType, $data['manifests']);
    }


    // Process and get array of sample codes
    $payload = $stsResultsService->receiveResults($testType, $data, $isSilent, $ackByUniqueId) ?? [];
    // receiveResults() returns the list of sample codes it stored, not an array
    // keyed by 'results', so counting $payload['results'] recorded 0 for every push.
    $resultCount = count($payload);

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
        $labId,
        null,
        $authToken
    );

    $facilityIds = (!empty($data['facilityIds']) && is_array($data['facilityIds'])) ? $data['facilityIds'] : null;
    $general->updateResultSyncDateTime($testType, $facilityIds, $labId);

    // Emit response hints for clients that want to adapt chunking; safe to ignore.
    $procTimeMs = (int) round((microtime(true) - $procStart) * 1000);
    $suggestedChunk = ($procTimeMs > 60000) ? 500 : 1000;

    // keep header casing consistent with client-side lookups (lowercase)
    $responseHeaders = [
        'x-proc-time' => $procTimeMs,
        'x-chunk-next' => $suggestedChunk,
        'x-chunk-bounds' => 'min=100; max=1000',
    ];
    if ($contentLength > 0) {
        $responseHeaders['x-bytes-processed'] = $contentLength;
    }
    if ($ackByUniqueId) {
        $responseHeaders['x-ack-format'] = ResultSyncAcknowledgement::UNIQUE_ID;
    }
    // The body stays a plain list of sample codes: older LIS senders read it as one.
    if (!empty($data['manifests'])) {
        $responseHeaders['x-manifests'] = http_build_query($manifestsStats, '', '; ');
    }

    echo ApiService::generateJsonResponse($payload, $request, $responseHeaders);
} catch (Throwable $e) {
    // Optional user-facing safe message, read by ErrorResponseGenerator in prod
    $_SESSION['errorDisplayMessage'] = _translate('Unable to process the results');

    // Log with context (guard undefineds)
    LoggerUtility::logError($e->getMessage(), [
        'lab' => $labId ?? null,
        'transactionId' => $transactionId ?? null,
        'last_db_error' => isset($db) ? $db->getLastError() : null,
        'last_db_query' => isset($db) ? $db->getLastQuery() : null,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    // Rethrow so ErrorResponseGenerator returns structured JSON + status
    throw new SystemException($e->getMessage(), ($e->getCode() ?: 500), $e);
}

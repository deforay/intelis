<?php

// /remote/v2/request-receipts.php -- a lab's receipt for the requests it pulled
//
// Called only by labs that asked requests.php for receipts and were told this STS
// supports them. Body: {labId, testType, saved: [unique_id], failed: [{unique_id, reason}]}.
// Nothing calls this unless both sides are new enough, so the existing request
// sync is unaffected.
use Psr\Http\Message\ServerRequestInterface;
use App\Services\ApiService;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Services\STS\TokensService;
use App\Registries\ContainerRegistry;
use App\Services\STS\RequestReceiptsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
$db->ensureConnection();

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var RequestReceiptsService $receiptsService */
$receiptsService = ContainerRegistry::get(RequestReceiptsService::class);

try {
    $procStart = microtime(true);

    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');

    $data = $apiService->getJsonFromRequest($request, true);
    $transactionId = $apiService->getHeader($request, 'X-Request-ID') ?? MiscUtility::generateULID();
    $authToken = ApiService::extractBearerToken($request);

    $labId = $data['labId'] ?? null;
    $testType = $data['testType'] ?? null;
    $saved = $data['saved'] ?? [];
    $failed = $data['failed'] ?? [];

    if (empty($labId) || !ctype_digit((string) $labId)) {
        throw new SystemException('Lab ID is missing in the request', 400);
    }
    if (empty($testType) || !is_string($testType)) {
        throw new SystemException('Test Type is missing in the request', 400);
    }
    if (!is_array($saved) || !is_array($failed)) {
        throw new SystemException('saved and failed must be lists', 400);
    }

    if (!$stsTokensService->validateToken($authToken, (int) $labId)) {
        throw new SystemException(
            'Unauthorized Access on request receipts: ' . $stsTokensService->getLastValidationFailure(),
            401
        );
    }

    try {
        $counts = $receiptsService->apply(
            (int) $labId,
            $testType,
            array_values($saved),
            array_values($failed),
            $facilitiesService->getTestingLabFacilityMap($labId)
        );
    } catch (InvalidArgumentException $e) {
        throw new SystemException($e->getMessage(), 400, $e);
    }

    $payload = ['status' => 'success'] + $counts;

    $general->addApiTracking(
        $transactionId,
        'system',
        $counts['confirmed'] + $counts['failed'],
        'request-receipts',
        $testType,
        $_SERVER['REQUEST_URI'] ?? null,
        null,
        $counts,
        'json',
        $labId,
        null,
        $authToken
    );

    echo ApiService::generateJsonResponse($payload, $request, [
        'x-proc-time' => (int) round((microtime(true) - $procStart) * 1000),
    ]);
} catch (Throwable $e) {
    $_SESSION['errorDisplayMessage'] = _translate('Unable to process the request receipt');
    LoggerUtility::logError($e->getMessage(), [
        'lab' => $labId ?? null,
        'transactionId' => $transactionId ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    throw new SystemException($e->getMessage(), ($e->getCode() ?: 500), $e);
}

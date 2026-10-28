<?php

// The manifests Move Manifest can offer: those of one lab, or the one with a
// given code. JSON for the page.

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\ManifestMoveService;
use App\Registries\ContainerRegistry;

header('Content-Type: application/json');

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$testType = trim((string) ($_POST['module'] ?? ''));
try {
    TestsService::getTestTableName($testType);
} catch (Throwable) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown test type']);
    exit;
}

// AJAX requests bypass the AclMiddleware, so the page's own gate is checked here.
if (
    !$general->isSTSInstance()
    || $general->isCloudLisNonAdmin()
    || !_isAllowed('/specimen-referral-manifest/move-manifest.php?t=' . $testType)
) {
    http_response_code(403);
    echo json_encode(['error' => 'Not allowed']);
    exit;
}

/** @var ManifestMoveService $manifestMoveService */
$manifestMoveService = ContainerRegistry::get(ManifestMoveService::class);

// A manifest code finds that one manifest wherever it is; otherwise a lab's list.
$manifestCode = trim((string) ($_POST['manifestCode'] ?? ''));
$labId = (int) ($_POST['testingLab'] ?? 0);
if ($manifestCode !== '') {
    $manifests = $manifestMoveService->findByCode($testType, $manifestCode, $_SESSION['facilityMap'] ?? null);
} elseif ($labId > 0) {
    [$from, $to] = DateUtility::convertDateRange($_POST['daterange'] ?? null, includeTime: true);
    $manifests = $manifestMoveService->listForLab(
        $testType,
        $labId,
        $from ?: null,
        $to ?: null,
        $_SESSION['facilityMap'] ?? null
    );
} else {
    $manifests = [];
}

echo json_encode(['manifests' => array_map(static fn(array $m): array => $m + [
    'createdLabel' => DateUtility::humanReadableDateFormat($m['created'] ?? '', true),
], $manifests)]);

<?php

// The manifests Move Manifest can offer: those of one lab, or those with the
// given codes. JSON for the page.

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

// Manifest codes find those manifests wherever they are; otherwise a lab's list.
// Codes come typed, pasted or scanned: one per line, or separated by commas.
$manifestCodes = array_slice(array_values(array_unique(array_filter(
    preg_split('/[\s,;]+/', (string) ($_POST['manifestCodes'] ?? '')) ?: [],
    'strlen'
))), 0, 200);
$labId = (int) ($_POST['testingLab'] ?? 0);
$notFound = [];
if ($manifestCodes !== []) {
    $manifests = $manifestMoveService->findByCodes($testType, $manifestCodes, $_SESSION['facilityMap'] ?? null);
    $found = array_map('strtoupper', array_column($manifests, 'code'));
    $notFound = array_values(array_filter(
        $manifestCodes,
        static fn(string $code): bool => !in_array(strtoupper($code), $found, true)
    ));
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

echo json_encode([
    'manifests' => array_map(static fn(array $m): array => $m + [
        'createdLabel' => DateUtility::humanReadableDateFormat($m['created'] ?? '', true),
    ], $manifests),
    'notFound' => $notFound,
]);

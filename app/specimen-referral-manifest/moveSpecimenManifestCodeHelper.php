<?php

use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\FacilitiesService;
use App\Services\ManifestMoveService;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var ManifestMoveService $manifestMoveService */
$manifestMoveService = ContainerRegistry::get(ManifestMoveService::class);

$testType = trim((string) ($_POST['testType'] ?? '')) ?: 'generic-tests';
$back = 'view-manifests.php?t=' . urlencode($testType);

// Which lab a sample belongs to is decided on the STS. A lab moving its own
// samples would be handing them to another lab, which a lab cannot do.
if (!$general->isSTSInstance() || $general->isCloudLisNonAdmin()) {
    $_SESSION['alertMsg'] = _translate('Manifests can only be moved by an administrator on the Sample Tracking System');
    header("Location: $back");
    exit;
}

try {
    TestsService::getTestTableName($testType);
} catch (Throwable) {
    $_SESSION['alertMsg'] = _translate('Unknown test type');
    header('Location: view-manifests.php');
    exit;
}

$toLabId = (int) ($_POST['assignLab'] ?? 0);
$testingLabs = $facilitiesService->getTestingLabs($testType);
if ($toLabId <= 0 || !array_key_exists($toLabId, $testingLabs)) {
    $_SESSION['alertMsg'] = _translate('Please choose the testing lab to move the manifests to');
    header('Location: move-manifest.php?t=' . urlencode($testType));
    exit;
}
$reason = trim((string) ($_POST['reasonForChange'] ?? ''));
if ($reason === '') {
    $_SESSION['alertMsg'] = _translate('Please enter the reason for moving the manifests');
    header('Location: move-manifest.php?t=' . urlencode($testType));
    exit;
}

try {
    $outcome = $manifestMoveService->move(
        $testType,
        (array) ($_POST['packageCode'] ?? []),
        $toLabId,
        $reason,
        $_SESSION['userId'] ?? null,
        $_SESSION['facilityMap'] ?? null,
        ($_POST['receivedConfirmed'] ?? '') === 'yes'
    );
} catch (Throwable $e) {
    LoggerUtility::logError('Move Manifest failed: ' . $e->getMessage(), [
        'test_type' => $testType,
        'to_lab_id' => $toLabId,
        'trace' => $e->getTraceAsString(),
    ]);
    $_SESSION['alertMsg'] = _translate('The manifests could not be moved. Nothing was changed.');
    header('Location: move-manifest.php?t=' . urlencode($testType));
    exit;
}

$toLabName = $testingLabs[$toLabId];
$messages = [];
if ($outcome['moved'] !== []) {
    $messages[] = sprintf(
        _translate('Moved to %s: %s'),
        $toLabName,
        implode(', ', array_keys($outcome['moved']))
    );
    $general->activityLog(
        'move-manifest',
        ($_SESSION['userName'] ?? '') . ' moved manifest(s) ' . implode(', ', array_keys($outcome['moved']))
            . " to $toLabName: $reason",
        'specimen-manifest'
    );
}
$refusalText = [
    'tested' => _translate('has samples already tested'),
    'referral' => _translate('is a referral manifest'),
    'already-there' => _translate('is already at that lab'),
    'received' => _translate('was received at its lab and was not confirmed as sent on'),
    'not-found' => _translate('was not found'),
];
foreach ($outcome['refused'] as $code => $why) {
    $messages[] = sprintf(_translate('Not moved: %s %s'), $code, $refusalText[$why] ?? $why);
}
foreach ($outcome['withdrawals'] as $withdrawal) {
    $fromLabName = $facilitiesService->getFacilityName($withdrawal['labId']) ?: (string) $withdrawal['labId'];
    $messages[] = $withdrawal['commandId'] !== null
        ? sprintf(
            _translate('%s will cancel its copies of %d sample(s) at its next sync'),
            $fromLabName,
            $withdrawal['samples']
        )
        : sprintf(
            _translate('%s cannot be told automatically: its copies of %d sample(s) must be cancelled there'),
            $fromLabName,
            $withdrawal['samples']
        );
}

// The footer writes alertMsg into a JavaScript string as it is: each line is
// escaped for that, and lines are joined with an escaped newline, not a real one.
$_SESSION['alertMsg'] = $messages === []
    ? _translate('No manifests were selected', true)
    : implode('\\n', array_map(
        static fn(string $m): string => substr((string) json_encode(
            $m,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ), 1, -1),
        $messages
    ));
header("Location: $back");

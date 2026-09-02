<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;
use App\Repositories\Reference\ReferenceDataRepository;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
// The stored action is read raw: _sanitizeInput() is an HTML sanitizer and would
// persist "Redraw & resubmit" as "Redraw &amp; resubmit". Escaping belongs to rendering.
$correctiveAction = trim((string) _rawInput("correctiveAction", ""));

/** @var ReferenceDataRepository $referenceData */
$referenceData = ContainerRegistry::get(ReferenceDataRepository::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
	if ($correctiveAction !== "") {
		$correctiveActionId = (isset($_POST['correctiveActionId']) && $_POST['correctiveActionId'] != "")
			? (int) base64_decode((string) $_POST['correctiveActionId'], true)
			: null;
		$lastId = $referenceData->save(
			'corrective-action',
			'common',
			$correctiveAction,
			(string) ($_POST['correctiveActionStatus'] ?? ''),
			['test_type' => (string) ($_POST['testType'] ?? '')],
			$correctiveActionId
		);
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Recommended Corrective Action saved successfully");
			$general->activityLog('Recommended Corrective Action', $_SESSION['userName'] . ' saved Recommended Corrective Action ' . $correctiveAction, 'common-reference');
		}
	}
	header("Location:recommended-corrective-actions.php?testType=" . urlencode((string) ($_POST['testType'] ?? '')));
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

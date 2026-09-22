<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Exceptions\SystemException;
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

$testType = (string) ($_POST['testType'] ?? '');
$correctiveActionId = (isset($_POST['correctiveActionId']) && $_POST['correctiveActionId'] != "")
	? (int) base64_decode((string) $_POST['correctiveActionId'], true)
	: null;

// The page hides Add and Edit on a LIS, which receives this list from the STS.
if ($general->isLISInstance()) {
	throw new SystemException(_translate('You do not have permission to perform this action.'), 403);
}
// Each test type's list belongs to that module's reference permission. An edit
// keeps the record's own test type, so it cannot be moved to another module.
if ($correctiveActionId) {
	$stored = $referenceData->findById('corrective-action', 'common', $correctiveActionId);
	$testType = (string) ($stored['test_type'] ?? $testType);
	_requirePrivilege("/common/reference/edit-recommended-corrective-action.php?testType=$testType");
} else {
	_requirePrivilege("/common/reference/add-recommended-corrective-action.php?testType=$testType");
}

try {
	if ($correctiveAction !== "") {
		$lastId = $referenceData->save(
			'corrective-action',
			'common',
			$correctiveAction,
			(string) ($_POST['correctiveActionStatus'] ?? ''),
			['test_type' => $testType],
			$correctiveActionId
		);
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Recommended Corrective Action saved successfully");
			$general->activityLog('Recommended Corrective Action', $_SESSION['userName'] . ' saved Recommended Corrective Action ' . $correctiveAction, 'common-reference');
		}
	}
	header("Location:recommended-corrective-actions.php?testType=" . urlencode($testType));
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

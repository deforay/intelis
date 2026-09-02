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
// The stored name is read raw: _sanitizeInput() is an HTML sanitizer and would
// persist "Routine & Follow-up" as "Routine &amp; Follow-up". Escaping belongs to rendering.
$reasonName = trim((string) _rawInput("testReasonName", ""));

/** @var ReferenceDataRepository $referenceData */
$referenceData = ContainerRegistry::get(ReferenceDataRepository::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
	if ($reasonName !== "") {
		$reasonId = (isset($_POST['testReasonId']) && $_POST['testReasonId'] != "")
			? (int) base64_decode((string) $_POST['testReasonId'], true)
			: null;
		$lastId = $referenceData->save(
			'test-reason',
			'cd4',
			$reasonName,
			(string) ($_POST['testReasonStatus'] ?? ''),
			['parent_reason' => (string) ($_POST['parentReason'] ?? '')],
			$reasonId
		);
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("CD4 Test Reason details saved successfully");
			$general->activityLog('CD4 Test Reason details', $_SESSION['userName'] . ' saved Test Reason ' . $reasonName, 'cd4-reference');
		}
	}
	header("Location:cd4-test-reasons.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

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
		$referenceData->save(
			'test-reason',
			'covid19',
			$reasonName,
			(string) ($_POST['testReasonStatus'] ?? ''),
			['parent_reason' => (string) ($_POST['parentReason'] ?? '')]
		);
		$_SESSION['alertMsg'] = _translate("COVID 19 Test reasons details added successfully");
		$general->activityLog('add-test-reasons', $_SESSION['userName'] . ' added new reference test reasons ' . $reasonName, 'reference-covid19-test-reasons');
	}
	header("Location:covid19-test-reasons.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

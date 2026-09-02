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
		// This page only ever edits, so the id is not optional here.
		$reasonId = (int) base64_decode((string) ($_POST['testReasonId'] ?? ''), true);
		$referenceData->save(
			'test-reason',
			'tb',
			$reasonName,
			(string) ($_POST['testReasonStatus'] ?? ''),
			['parent_reason' => (string) ($_POST['parentReason'] ?? '')],
			$reasonId
		);
		$_SESSION['alertMsg'] = _translate("Test reason details updated successfully");
		$general->activityLog('update-test-reasons', $_SESSION['userName'] . ' updated reference test reasons ' . $reasonName, 'reference-tb-test-reasons');
	}
	header("Location:tb-test-reasons.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

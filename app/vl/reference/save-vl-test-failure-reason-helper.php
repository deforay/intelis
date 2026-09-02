<?php

use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;
use App\Repositories\Reference\ReferenceDataRepository;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
// The stored reason is read raw: _sanitizeInput() is an HTML sanitizer and would
// persist "Clotted & Insufficient" as "Clotted &amp; Insufficient". Escaping belongs to rendering.
$failureReason = trim((string) _rawInput("failureReason", ""));

/** @var ReferenceDataRepository $referenceData */
$referenceData = ContainerRegistry::get(ReferenceDataRepository::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
	if ($failureReason !== "") {
		$failureId = (isset($_POST['failureId']) && $_POST['failureId'] != "")
			? (int) base64_decode((string) $_POST['failureId'], true)
			: null;
		$lastId = $referenceData->save(
			'test-failure-reason',
			'vl',
			$failureReason,
			(string) ($_POST['status'] ?? ''),
			rowId: $failureId
		);
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("VL Test Failure Reason Saved Successfully");
			$general->activityLog('VL Test Failure Reason', $_SESSION['userName'] . ' saved vl test failure reason ' . $failureReason, 'vl-reference');
		}
	}
	MiscUtility::redirect("/vl/reference/vl-test-failure-reasons.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

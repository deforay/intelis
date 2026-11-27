<?php

use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
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


try {
	if ($reasonName !== "") {
		$reasonId = (isset($_POST['testReasonId']) && $_POST['testReasonId'] != "")
			? (int) base64_decode((string) $_POST['testReasonId'], true)
			: null;
		$lastId = $referenceData->save(
			'test-reason',
			'vl',
			$reasonName,
			(string) ($_POST['testReasonStatus'] ?? ''),
			['parent_reason' => (string) ($_POST['parentReason'] ?? '')],
			$reasonId
		);
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("VL Test Reason details saved successfully");
		}
	}
	MiscUtility::redirect("/vl/reference/vl-test-reasons.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

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
// Stored values are read raw: _sanitizeInput() is an HTML sanitizer and would
// persist "Broken & Leaking" as "Broken &amp; Leaking". Escaping belongs to rendering.
$reasonName = trim((string) _rawInput("rejectionReasonName", ""));
$reasonType = (string) _rawInput("rejectionType", "");
$reasonCode = (string) _rawInput("rejectionReasonCode", "");

/** @var ReferenceDataRepository $referenceData */
$referenceData = ContainerRegistry::get(ReferenceDataRepository::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
	if ($reasonName !== "") {
		$reasonId = (isset($_POST['rejectionReasonId']) && $_POST['rejectionReasonId'] != "")
			? (int) base64_decode((string) $_POST['rejectionReasonId'], true)
			: null;
		$lastId = $referenceData->save(
			'rejection-reason',
			'vl',
			$reasonName,
			(string) ($_POST['rejectionReasonStatus'] ?? ''),
			[
				'rejection_type' => $reasonType,
				'rejection_reason_code' => $reasonCode,
			],
			$reasonId
		);
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("VL Sample Rejection Reasons details added successfully");
			$general->activityLog('VL Sample Rejection Reasons For VL', $_SESSION['userName'] . ' added new reference Sample Rejection Reasons for VL  ' . $reasonName, 'vl-reference');
		}
	}
	MiscUtility::redirect("/vl/reference/vl-sample-rejection-reasons.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

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
		$referenceData->save(
			'rejection-reason',
			'tb',
			$reasonName,
			(string) ($_POST['rejectionReasonStatus'] ?? ''),
			[
				'rejection_type' => $reasonType,
				'rejection_reason_code' => $reasonCode,
			]
		);
		$_SESSION['alertMsg'] = _translate("TB Sample Rejection Reasons details added successfully");
		$general->activityLog('add-Sample Rejection Reasons', $_SESSION['userName'] . ' added new reference Sample Rejection Reasons ' . $reasonName, 'reference-tb-Sample Rejection Reasons');
	}
	header("Location:tb-sample-rejection-reasons.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

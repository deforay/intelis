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
// persist "Plasma & Serum" as "Plasma &amp; Serum". Escaping belongs to rendering.
$sampleName = trim((string) _rawInput("sampleName", ""));

/** @var ReferenceDataRepository $referenceData */
$referenceData = ContainerRegistry::get(ReferenceDataRepository::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
	if ($sampleName !== "") {
		$referenceData->save(
			'sample-type',
			'tb',
			$sampleName,
			(string) ($_POST['sampleStatus'] ?? '')
		);
		$_SESSION['alertMsg'] = _translate("Sample Type details added successfully");
		$general->activityLog('add-sample-type', $_SESSION['userName'] . ' added new TB sample type : ' . $sampleName, 'reference-tb-sample-type');
	}
	header("Location:tb-sample-type.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

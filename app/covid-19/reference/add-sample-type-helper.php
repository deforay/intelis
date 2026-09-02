<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;
use App\Repositories\Reference\SampleTypeRepository;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
// The stored name is read raw: _sanitizeInput() is an HTML sanitizer and would
// persist "Plasma & Serum" as "Plasma &amp; Serum". Escaping belongs to rendering.
$sampleName = trim((string) _rawInput("sampleName", ""));

/** @var SampleTypeRepository $sampleTypes */
$sampleTypes = ContainerRegistry::get(SampleTypeRepository::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
	if ($sampleName !== "") {
		$sampleTypes->save(
			'covid19',
			$sampleName,
			(string) ($_POST['sampleStatus'] ?? '')
		);
		$_SESSION['alertMsg'] = _translate("Sample Type details added successfully");
		$general->activityLog('add-sample-type', $_SESSION['userName'] . ' added new Covid-19 sample type : ' . $sampleName, 'reference-covid19-sample-type');
	}
	header("Location:covid19-sample-type.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

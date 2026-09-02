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
		$sampleTypeId = (isset($_POST['sampleId']) && $_POST['sampleId'] != "")
			? (int) base64_decode((string) $_POST['sampleId'], true)
			: null;
		$lastId = $sampleTypes->save(
			'hepatitis',
			$sampleName,
			(string) ($_POST['sampleStatus'] ?? ''),
			$sampleTypeId
		);
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Hepatitis Sample details saved successfully");
			$general->activityLog('Hepatitis Sample Type details', $_SESSION['userName'] . ' saved Hepatitis sample type : ' . $sampleName, 'hepatitis-reference');
		}
	}
	header("Location:hepatitis-sample-type.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

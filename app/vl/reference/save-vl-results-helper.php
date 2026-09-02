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
// persist "< 20 copies/mL" or "Detected & Confirmed" with entities. Escaping
// belongs to rendering.
$resultName = trim((string) _rawInput("resultName", ""));
$interpretation = (string) _rawInput("interpretation", "");

/** @var ReferenceDataRepository $referenceData */
$referenceData = ContainerRegistry::get(ReferenceDataRepository::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
	if ($resultName !== "") {
		// NULL means the result is available on every instrument.
		$jsonInstruments = (!empty($_POST['selectedInstruments']))
			? json_encode(explode(',', (string) $_POST['selectedInstruments']))
			: null;
		$resultId = (isset($_POST['resultId']) && $_POST['resultId'] != "")
			? (int) base64_decode((string) $_POST['resultId'], true)
			: null;
		$lastId = $referenceData->save(
			'vl-result',
			'vl',
			$resultName,
			(string) ($_POST['resultStatus'] ?? ''),
			[
				'interpretation' => $interpretation,
				'available_for_instruments' => $jsonInstruments,
			],
			$resultId
		);
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("VL Results details saved successfully");
			$general->activityLog('VL Results details', $_SESSION['userName'] . ' saved results ' . $resultName, 'vl-reference');
		}
	}
	MiscUtility::redirect("/vl/reference/vl-results.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

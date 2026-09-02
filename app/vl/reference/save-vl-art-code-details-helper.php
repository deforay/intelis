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
// The stored code is read raw: _sanitizeInput() is an HTML sanitizer and would
// mangle codes containing an ampersand. Escaping belongs to rendering.
$artCode = trim((string) _rawInput("artCode", ""));
$category = (string) _rawInput("category", "");

/** @var ReferenceDataRepository $referenceData */
$referenceData = ContainerRegistry::get(ReferenceDataRepository::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
	if ($artCode !== "") {
		$artCodeId = (isset($_POST['artCodeId']) && $_POST['artCodeId'] != "")
			? (int) base64_decode((string) $_POST['artCodeId'], true)
			: null;
		$lastId = $referenceData->save(
			'art-code',
			'vl',
			$artCode,
			(string) ($_POST['artStatus'] ?? ''),
			[
				'parent_art' => (string) ($_POST['parentArtCode'] ?? ''),
				'headings' => $category,
			],
			$artCodeId
		);
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Art Code details saved successfully");
			$general->activityLog('Add art code details', $_SESSION['userName'] . ' saved art code ' . $artCode, 'vl-reference');
		}
	}
	MiscUtility::redirect("/vl/reference/vl-art-code-details.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

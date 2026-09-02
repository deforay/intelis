<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Repositories\Reference\ReferenceDataRepository;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
// The stored name is read raw: _sanitizeInput() is an HTML sanitizer and would
// persist "PEPFAR & Global Fund" with entities. Escaping belongs to rendering.
$fundingSourceName = trim((string) _rawInput("fundingSrcName", ""));

/** @var ReferenceDataRepository $referenceData */
$referenceData = ContainerRegistry::get(ReferenceDataRepository::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
	if ($fundingSourceName !== "") {
		$fundingId = (isset($_POST['fundingId']) && $_POST['fundingId'] != "")
			? (int) base64_decode((string) $_POST['fundingId'], true)
			: null;
		$lastId = $referenceData->save(
			'funding-source',
			'common',
			$fundingSourceName,
			(string) ($_POST['fundingStatus'] ?? ''),
			rowId: $fundingId
		);
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Funding Source saved successfully");
			$general->activityLog('Funding Source', $_SESSION['userName'] . ' saved Funding Source ' . $fundingSourceName, 'common-reference');
		}
	}
} catch (Throwable $e) {
	throw new SystemException($e->getMessage(), 500, $e);
}
header("Location:funding-sources.php");

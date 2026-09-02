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
// persist a name with an ampersand as entities. Escaping belongs to rendering.
$partnerName = trim((string) _rawInput("partnerName", ""));

/** @var ReferenceDataRepository $referenceData */
$referenceData = ContainerRegistry::get(ReferenceDataRepository::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
	if ($partnerName !== "") {
		$partnerId = (isset($_POST['partnerId']) && $_POST['partnerId'] != "")
			? (int) base64_decode((string) $_POST['partnerId'], true)
			: null;
		$lastId = $referenceData->save(
			'implementation-partner',
			'common',
			$partnerName,
			(string) ($_POST['partnerStatus'] ?? ''),
			rowId: $partnerId
		);
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Implementation Partners saved successfully");
			$general->activityLog('Implementation Partners', $_SESSION['userName'] . ' saved Implementation Partner ' . $partnerName, 'common-reference');
		}
	}
} catch (Throwable $e) {
	throw new SystemException($e->getMessage(), 500, $e);
}
header("Location:implementation-partners.php");

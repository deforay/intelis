<?php

use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var GenericTestsService $generic */
$generic = ContainerRegistry::get(GenericTestsService::class);

try {
	$db->beginTransaction();

	/** @var Laminas\Diactoros\ServerRequest $request */
	$request = AppRegistry::get('request');
	$data = $apiService->getJsonFromRequest($request, true);


	$payload = [];

	$labId = $data['labName'] ?? $data['labId'] ?? null;


	if (empty($labId)) {
		throw new SystemException('Lab ID is missing in the request', 400);
	}

	$dataSyncInterval = $general->getGlobalConfig('data_sync_interval');
	$dataSyncInterval = !empty($dataSyncInterval) ? $dataSyncInterval : 30;

	$apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
	$transactionId = $apiRequestId ?? MiscUtility::generateULID();

	$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
	$fMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

	if (!empty($fMapResult)) {
		$condition = "(lab_id =$labId OR facility_id IN ($fMapResult))";
	} else {
		$condition = "lab_id = $labId";
	}

	$general->updateNullColumnsWithDefaults('form_generic', [
		'is_result_mail_sent' => 'no',
		'is_request_mail_sent' => 'no',
		'is_result_sms_sent' => 'no'
	]);

	$sQuery = "SELECT * FROM form_generic WHERE $condition ";

	if (!empty($data['manifestCode'])) {
		$sQuery .= " AND sample_package_code like '{$data['manifestCode']}'";
	} else {
		$sQuery .= " AND data_sync=0 AND last_modified_datetime >= SUBDATE( '" . DateUtility::getCurrentDateTime() . "', INTERVAL $dataSyncInterval DAY)";
	}

	[$rResult, $resultCount] = $db->getDataAndCount($sQuery, returnGenerator: false);

	$sampleIds = $facilityIds = [];
	if ($resultCount > 0) {
		$sampleIds = array_column($rResult, 'sample_id');
		$facilityIds = array_column($rResult, 'facility_id');

		foreach ($rResult as $r) {
			$response[$r['sample_id']] = $r;
			$response[$r['sample_id']]['data_from_tests'] = $generic->getTestsByGenericSampleIds($r['sample_id']);
		}
		$payload = JsonUtility::encodeUtf8Json($response);
	} else {
		$payload = json_encode([]);
	}

	$general->addApiTracking($transactionId, 'vlsm-system', $resultCount, 'requests', 'generic-tests', $_SERVER['REQUEST_URI'], JsonUtility::encodeUtf8Json($data), $payload, 'json', $labId);

	if (!empty($facilityIds)) {
		$general->updateTestRequestsSyncDateTime('generic', $facilityIds, $labId);
	}


	if (!empty($sampleIds)) {
		$updateData = [
			'data_sync' => 1
		];
		$db->where('sample_id', $sampleIds, 'IN');
		$db->update('form_generic', $updateData);
	}


	$db->commitTransaction();
} catch (Throwable $e) {
	$db->rollbackTransaction();

	$payload = json_encode([]);

	if ($db->getLastError()) {
		LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $db->getLastErrno());
		LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $db->getLastError());
		LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $db->getLastQuery());
	}
	throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), $e->getCode(), $e);
}

echo ApiService::sendJsonResponse($payload, $request);

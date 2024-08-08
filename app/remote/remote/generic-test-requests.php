<?php

use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
try {
	$db->beginTransaction();

	/** @var ApiService $apiService */
	$apiService = ContainerRegistry::get(ApiService::class);

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



	$transactionId = MiscUtility::generateULID();

	$counter = 0;


	$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
	$fMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

	if (!empty($fMapResult)) {
		$condition = "(lab_id =" . $labId . " OR facility_id IN (" . $fMapResult . "))";
	} else {
		$condition = "lab_id =" . $labId;
	}


	$removeKeys = [
		'sample_code',
		'sample_code_key',
		'sample_code_format',
		'sample_batch_id',
		'sample_received_at_lab_datetime',
		'eid_test_platform',
		'import_machine_name',
		'sample_tested_datetime',
		'is_sample_rejected',
		'lab_id',
		'result',
		'tested_by',
		'lab_tech_comments',
		'result_approved_by',
		'result_approved_datetime',
		'revised_by',
		'revised_on',
		'result_reviewed_by',
		'result_reviewed_datetime',
		'result_dispatched_datetime',
		'reason_for_changing',
		'result_status',
		'data_sync',
		'reason_for_sample_rejection',
		'rejection_on',
		'last_modified_by',
		'result_printed_datetime',
		'last_modified_datetime'
	];

	$general->updateNullColumnsWithDefaults('generic_tests', [
		'is_result_mail_sent' => 'no',
		'is_request_mail_sent' => 'no',
		'is_result_sms_sent' => 'no'
	]);

	$sQuery = "SELECT * FROM form_generic
                    WHERE $condition ";

	if (!empty($data['manifestCode'])) {
		$sQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "'";
	} else {
		$sQuery .= " AND data_sync=0 AND last_modified_datetime >= SUBDATE( '" . DateUtility::getCurrentDateTime() . "', INTERVAL $dataSyncInterval DAY)";
	}

	[$rResult, $resultCount] = $db->getQueryResultAndCount($sQuery, returnGenerator: false);

	$response = $sampleIds = $facilityIds = [];
	if ($resultCount > 0) {
		$payload = $rResult;
		$counter = $resultCount;
		$sampleIds = array_column($rResult, 'sample_id');
		$facilityIds = array_column($rResult, 'facility_id');

		/** @var GenericTestsService $general */
		$generic = ContainerRegistry::get(GenericTestsService::class);
		foreach ($rResult as $r) {
			$response[$r['sample_id']] = $r;
			$response[$r['sample_id']]['data_from_tests'] = $generic->getTestsByGenericSampleIds($r['sample_id']);
		}
		$payload = JsonUtility::encodeUtf8Json($response);
	} else {
		$payload = json_encode([]);
	}

	if ($resultCount > 0) {

		$sampleIds = array_column($rResult, 'vl_sample_id');
		$facilityIds = array_column($rResult, 'facility_id');

		$payload = JsonUtility::encodeUtf8Json($rResult);
	} else {
		$payload = json_encode([]);
	}
	$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'generic-tests', $_SERVER['REQUEST_URI'], JsonUtility::encodeUtf8Json($data), $payload, 'json', $labId);
	$general->updateTestRequestsSyncDateTime('generic', $facilityIds, $labId);
	$db->commitTransaction();
} catch (Throwable $e) {
	$db->rollbackTransaction();

	$payload = json_encode([]);

	if ($db->getLastErrno() > 0) {
		error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastErrno());
		error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
		error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
	}
	throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), $e->getCode(), $e);
}

echo $apiService->sendJsonResponse($payload);

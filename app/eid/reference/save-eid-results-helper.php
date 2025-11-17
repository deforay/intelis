<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_eid_results";
$primaryKey = "result_id";

try {
	if (isset($_POST['resultName']) && trim((string) $_POST['resultName']) !== "") {
		$data = [
			'result_id' => strtolower((string) $_POST['resultName']),
			'result' => $_POST['resultName'],
			'status' => $_POST['resultStatus'],
			'updated_datetime' => DateUtility::getCurrentDateTime(),
		];
		if (isset($_POST['resultId']) && $_POST['resultId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['resultId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("EID Results details saved successfully");
			$general->activityLog('EID Results details', $_SESSION['userName'] . ' added new results for ' . $_POST['resultName'], 'eid-reference');
		}
	}
	header("Location:eid-results.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}

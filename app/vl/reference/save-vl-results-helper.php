<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_vl_results";
$primaryKey = "result_id";
// print_r(base64_decode($_POST['resultId']));die;
try {
	//echo '<pre>'; print_r($_POST); die;
	if (isset($_POST['resultName']) && trim((string) $_POST['resultName']) !== "") {
		if (!empty($_POST['selectedInstruments'])) {
			$jsonInstruments = json_encode(explode(',', (string) $_POST['selectedInstruments']), true);
		} else {
			$jsonInstruments = null;
		}
		$data = [
			'result' => ($_POST['resultName']),
			'available_for_instruments' => $jsonInstruments,
			'interpretation' => $_POST['interpretation'],
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
			$_SESSION['alertMsg'] = _translate("VL Results details saved successfully");
			$general->activityLog('VL Results details', $_SESSION['userName'] . ' added new results for ' . $_POST['resultName'], 'vl-reference');
		}
	}

} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
_invalidateFileCacheByTags(['r_vl_results']);
MiscUtility::redirect("/vl/reference/vl-results.php");

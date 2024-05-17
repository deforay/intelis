<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_vl_results";
$primaryKey = "result_id";
// print_r(base64_decode($_POST['resultId']));die;
try {
	//echo '<pre>'; print_r($_POST); die;
	if (isset($_POST['resultName']) && trim((string) $_POST['resultName']) != "") {
		if (!empty($_POST['selectedInstruments'])) {
			$jsonInstruments = json_encode(explode(',',$_POST['selectedInstruments']), true);
		} else {
			$jsonInstruments = null;
		}
		$data = array(
			'result' 		=> ($_POST['resultName']),
			'available_for_instruments' => $jsonInstruments,
			'interpretation' => $_POST['interpretation'],
			'status' 	    => $_POST['resultStatus'],
			'updated_datetime' 	=> DateUtility::getCurrentDateTime(),
		);
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
	header("Location:vl-results.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => __FILE__,
		'line' => __LINE__,
		'trace' => $e->getTraceAsString(),
	]);
}

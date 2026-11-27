<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "r_covid19_results";
$primaryKey = "result_id";
// print_r(base64_decode($_POST['resultId']));die;
// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (string) base64_decode((string) ($_POST['resultId'] ?? ''));
$activityBefore = $activityLog->row('r_covid19_results', 'result_id', $activityId);

try {
	if (isset($_POST['resultName']) && trim((string) $_POST['resultName']) !== "") {
		$data = ['result' 		=> ($_POST['resultName']), 'status' 	    => $_POST['resultStatus'], 'updated_datetime' 	=> DateUtility::getCurrentDateTime()];
		if (isset($_POST['resultId']) && $_POST['resultId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['resultId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$lastId = $db->insert($tableName, $data);
		}

		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Covid-19 Results details saved successfully");
			$activityLog->recordRowSave(
			    'covid19-result-save',
			    ($activityBefore === null ? 'Added ' : 'Updated ') . 'COVID-19 result ' . $_POST['resultName'],
			    'covid19-result',
			    'r_covid19_results',
			    'result_id',
			    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
			    'result',
			    $activityBefore, [], ['result', 'status']
			);
		}
	}
	header("Location:covid19-results.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

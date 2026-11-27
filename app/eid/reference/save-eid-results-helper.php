<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "r_eid_results";
$primaryKey = "result_id";

// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (string) base64_decode((string) ($_POST['resultId'] ?? ''));
$activityBefore = $activityLog->row('r_eid_results', 'result_id', $activityId);

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
			$activityLog->recordRowSave(
			    'eid-result-save',
			    ($activityBefore === null ? 'Added ' : 'Updated ') . 'EID result ' . $_POST['resultName'],
			    'eid-result',
			    'r_eid_results',
			    'result_id',
			    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
			    'result',
			    $activityBefore, [], ['result', 'status']
			);
		}
	}
	header("Location:eid-results.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}

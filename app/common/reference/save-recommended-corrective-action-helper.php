<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "r_recommended_corrective_actions";
$primaryKey = "recommended_corrective_action_id";

try {

	if (isset($_POST['correctiveAction']) && trim((string) $_POST['correctiveAction']) != "") {

		$data = array(
			'recommended_corrective_action_name' 	=> $_POST['correctiveAction'],
			'test_type'							 	=> $_POST['testType'],
			'status' 								=> $_POST['correctiveActionStatus'],
			'updated_datetime'						=> DateUtility::getCurrentDateTime()
		);
		//	echo '<pre>'; print_r($_POST	); die;
		if (isset($_POST['correctiveActionId']) && $_POST['correctiveActionId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['correctiveActionId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Recommended Corrective Action saved successfully");
			$general->activityLog('Recommended Corrective Action', $_SESSION['userName'] . ' added new Recommended Corrective Action for ' . $_POST['correctiveAction'], 'common-reference');
		}
	}
	header("Location:recommended-corrective-actions.php?testType=" . $_POST['testType']);
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}

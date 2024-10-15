<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


try {
	if (!empty($_POST['geoName'])) {
		$lastId = 0;
		$data = array(
			'geo_name' 			=> $_POST['geoName'],
			'geo_code' 			=> $_POST['geoCode'] ?? null,
			'geo_parent' 		=> $_POST['geoParent'] ?? 0,
			'geo_status' 		=> $_POST['geoStatus'] ?? 'active',
			'updated_datetime'	=> DateUtility::getCurrentDateTime()
		);
		if (isset($_POST['geoId']) && $_POST['geoId'] != "") {
			$db->where("geo_id", base64_decode((string) $_POST['geoId']));
			$geoId = base64_decode((string) $_POST['geoId']);
			$lastId = $db->update("geographical_divisions", $data);
		} else {
			$data['created_by'] = $_SESSION['userId'];
			$data['created_on'] = DateUtility::getCurrentDateTime();
			$data['data_sync'] = 0;
			$db->insert("geographical_divisions", $data);
			$geoId = $lastId = $db->getInsertId();
		}
		if ($lastId > 0) {

			$facilityData = [];
			if ($data['geo_parent'] == 0) {
				$facilityData['facility_state'] = $data['geo_name'];
				$facilityData['facility_state_id'] = $data['geo_id'];
				$db->where("facility_state", $data['geo_name']);
				$db->where("facility_state_id", $data['geo_id']);
			} else {
				$facilityData['facility_state_id'] = $data['geo_parent'];
				$facilityData['facility_district'] = $data['geo_name'];
				$facilityData['facility_district_id'] = $data['geo_id'];
				$db->where('facility_state', $data['geo_parent']);
				$db->where("facility_district", $data['geo_name']);
				$db->where("facility_district_id", $data['geo_id']);
			}
			$db->update("facility_details", $facilityData);

			$_SESSION['alertMsg'] = _translate("Geographical Divisions details saved successfully");
			$general->activityLog('Geographical Divisions details', $_SESSION['userName'] . ' saved geographical division - ' . $_POST['geoName'], 'common-reference');
		}
	}
	header("Location:geographical-divisions-details.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}

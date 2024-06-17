<?php

use App\Utilities\LoggerUtility;

$tableName = "testing_lab_health_facilities_map";
try {
	if (isset($_POST['vlLab']) && trim((string) $_POST['vlLab']) != "" && trim((string) $_POST['facilityTo']) != '') {
		$facilityTo = explode(",", (string) $_POST['facilityTo']);
		for ($j = 0; $j < count($facilityTo); $j++) {
			$data = array(
				'vl_lab_id' => $_POST['vlLab'],
				'facility_id' => $facilityTo[$j],
			);
			$db->insert($tableName, $data);
		}
		$_SESSION['alertMsg'] = "Facility map details added successfully";
	}
	header("Location:facilityMap.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => __FILE__,
		'line' => __LINE__,
		'trace' => $e->getTraceAsString(),
	]);
}

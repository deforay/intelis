<?php

use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "lab_storage";
$primaryKey = "storage_id";

try {
	if (isset($_POST['storageCode']) && trim((string) $_POST['storageCode']) != "") {

		$data = array(
			'storage_id' => $general->generateUUID(),
			'storage_code' 	=> $_POST['storageCode'],
			'lab_id' 	=> $_POST['labId'],
			'storage_status' => $_POST['storageStatus'],
			'updated_datetime'		=> DateUtility::getCurrentDateTime()
		);
		if (isset($_POST['storageId']) && $_POST['storageId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['storageId']));
			$save = $db->update($tableName, $data);
		} else {
			$save = $db->insert($tableName, $data);
		}
		if ($save) {
			$_SESSION['alertMsg'] = _translate("Lab Storage saved successfully");
			$general->activityLog('Lab Storage', $_SESSION['userName'] . ' added new Lab Storage for ' . $_POST['storageCode'], 'common-reference');
		}
	}
	header("Location:lab-storage.php");
} catch (Exception $exc) {
	throw new SystemException($exc->getMessage(), 500);
}

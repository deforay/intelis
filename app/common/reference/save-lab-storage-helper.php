<?php

use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\StorageService;





/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var StorageService $storageService */
$storageService = ContainerRegistry::get(StorageService::class);


$tableName = "lab_storage";
$primaryKey = "storage_id";

try {
	if (isset($_POST['storageCode']) && !empty($_POST['storageCode']) && trim((string) $_POST['storageCode']) !== "") {

		$save = $storageService->saveLabStorage($_POST);
		if ($save) {
			$_SESSION['alertMsg'] = _translate("Lab Storage saved successfully");
			$general->activityLog('Lab Storage', $_SESSION['userName'] . ' added new Lab Storage for ' . $_POST['storageCode'], 'common-reference');
		}
	}
	header("Location:lab-storage.php");
} catch (Throwable $exc) {
	throw new SystemException($exc->getMessage(), 500);
}

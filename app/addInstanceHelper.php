<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Utilities\ImageResizeUtility;
use App\Utilities\FileCacheUtility;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "s_vlsm_instance";
$globalTable = "global_config";
$systemConfigTable = "system_config";
$sanitizedLogoFile = _sanitizeFiles($_FILES['logo'], ['png', 'jpg', 'jpeg', 'gif']);

$_POST = _sanitizeInput($_POST);
function getMacLinux(): bool|string
{
	try {
		$mac = exec('getmac');
		return strtok($mac, ' ') ?? "notfound";
	} catch (Exception $exc) {
		error_log($exc->getMessage());
		error_log($exc->getTraceAsString());
		return "not found";
	}
}
function getMacWindows(): string
{
	// Turn on output buffering
	ob_start();
	//Get the ipconfig details using system commond
	system('ipconfig /all');

	// Capture the output into a variable
	$mycom = ob_get_contents();
	// Clean (erase) the output buffer
	ob_clean();

	$findme = "Physical";
	//Search the "Physical" | Find the position of Physical text
	$pmac = strpos($mycom, $findme);

	// Get Physical Address
	return substr($mycom, ($pmac + 36), 17);
}
try {
	if ((isset($_POST['facilityId']) && trim((string) $_POST['facilityId']) != "") || isset($_POST['labId']) && trim((string) $_POST['labId']) != "") {
		if (isset($_POST['labId']) && trim((string) $_POST['labId']) != "") {
			$labResults = $general->fetchDataFromTable('facility_details', 'facility_id = ' . $_POST['labId'], array('facility_type', 'facility_name', 'facility_code'));
			if (isset($labResults[0]['facility_name']) && trim((string) $labResults[0]['facility_name']) != "") {
				$_POST['facilityId'] = $labResults[0]['facility_name'];
				$_POST['facilityCode'] = $labResults[0]['facility_code'];
				$_POST['fType'] = $labResults[0]['facility_type'];
			}
		}
		$instanceId = '';
		if (isset($_SESSION['instanceId'])) {
			$instanceId = $_SESSION['instanceId'];
		} else {
			$instanceId = $general->generateUUID();
			// deleting just in case there is a row already inserted
			$db->delete('s_vlsm_instance');
			$db->insert('s_vlsm_instance', array('vlsm_instance_id' => $instanceId));
			$_SESSION['instanceId'] = $instanceId;
		}
		$db->where('name', 'instance_type');
		$db->update($globalTable, array('value' => $_POST['fType']));
		$data = [
			'instance_facility_name' => $_POST['facilityId'],
			'instance_facility_code' => $_POST['facilityCode'],
			'instance_facility_type' => $_POST['fType'],
			'instance_added_on' => DateUtility::getCurrentDateTime(),
			'instance_update_on' => DateUtility::getCurrentDateTime()
		];
		$data['instance_mac_address'] = "not found";
		if (PHP_OS == 'Linux') {
			$data['instance_mac_address'] = getMacLinux();
		} elseif (PHP_OS == 'WINNT') {
			$data['instance_mac_address'] = getMacWindows();
		}

		$db->where('vlsm_instance_id', $instanceId);
		$id = $db->update($tableName, $data);

		$db->where('name', 'sc_testing_lab_id');
		$db->update($systemConfigTable, array('value' => $_POST['labId']));

		(ContainerRegistry::get(FileCacheUtility::class))->clear();


		if ($id === true) {
			$_SESSION['instanceFacilityName'] = $_POST['facilityId'];

			$systemInfo = $general->getSystemConfig();

			$_SESSION['instance']['type'] = $systemInfo['sc_user_type'];
			$_SESSION['instance']['labId'] = !empty($systemInfo['sc_testing_lab_id']) ? $systemInfo['sc_testing_lab_id'] : null;


			if (isset($sanitizedLogoFile['name']) && $sanitizedLogoFile['name'] != "") {

				MiscUtility::makeDirectory(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo");

				$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $sanitizedLogoFile['name'], PATHINFO_EXTENSION));
				$string = $general->generateRandomString(6) . ".";
				$imageName = "logo" . $string . $extension;
				if (move_uploaded_file($_FILES["logo"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $imageName)) {
					$resizeObj = new ImageResizeUtility(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $imageName);
					$resizeObj->resizeToWidth(100);
					$resizeObj->save(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $imageName);

					$image = ['instance_facility_logo' => $imageName];
					$db->where('vlsm_instance_id', $instanceId);
					$db->update($tableName, $image);
				}
			}
			//Add event log
			$eventType = 'add-instance';
			$action = $_SESSION['userName'] . ' added instance id';
			$resource = 'instance-details';

			$general->activityLog($eventType, $action, $resource);

			$_SESSION['alertMsg'] = "Instance details added successfully";
			$_SESSION['success'] = "success";
		} else {
			$_SESSION['alertMsg'] = "Something went wrong! Please try adding the instance again.";
		}
	}
	header("Location:addInstanceDetails.php");
} catch (Exception $exc) {
	throw new SystemException($exc->getMessage(), 500, $exc);
}

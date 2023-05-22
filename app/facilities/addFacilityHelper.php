<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;
use App\Utilities\DateUtility;
use App\Utilities\ImageResizeUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocation */
$geolocation = ContainerRegistry::get(GeoLocationsService::class);

/* For reference we define the table names */
$facilityTable = "facility_details";
$provinceTable = "geographical_divisions";
$vlUserFacilityMapTable = "user_facility_map";
$testingLabsTable = "testing_labs";
$healthFacilityTable = "health_facilities";
$labSignTable = "lab_report_signatories";

$jsonData = file_get_contents('php://input');
$apiData = json_decode($jsonData, true);
if (isset($apiData['result']) && !empty($apiData['result'])) {
	$_POST = $apiData['result'];
}
try {

	//Province Table
	if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != "") {
		if (isset($_POST['provinceNew']) && $_POST['provinceNew'] != "" && $_POST['stateId'] == 'other') {
			$_POST['stateId'] = $geolocation->addGeoLocation($_POST['provinceNew']);
			$_POST['state'] = $_POST['provinceNew'];
			// if (trim($_POST['state']) != "") {
			$strSearch = (isset($_POST['provinceNew']) && trim($_POST['provinceNew']) != '' && $_POST['state'] == 'other') ? $_POST['provinceNew'] : $_POST['state'];
			$facilityQuery = "SELECT geo_name from geographical_divisions where geo_name= ?";
			$facilityInfo = $db->rawQuery($facilityQuery, [$strSearch]);
			if (isset($facilityInfo[0]['geo_name'])) {
				$_POST['state'] = $facilityInfo[0]['geo_name'];
			} else {
				$data = array(
					'geo_name' => $_POST['provinceNew'],
					'updated_datetime' => DateUtility::getCurrentDateTime(),
				);
				$db->insert($provinceTable, $data);
				$_POST['state'] = $_POST['provinceNew'];
			}
		}
		$instanceId = '';
		if (isset($_SESSION['instanceId'])) {
			$instanceId = $_SESSION['instanceId'];
			$_POST['instanceId'] = $instanceId;
		}
		$email = '';
		if (isset($_POST['reportEmail']) && trim($_POST['reportEmail']) != '') {
			$expEmail = explode(",", $_POST['reportEmail']);
			for ($i = 0; $i < count($expEmail); $i++) {
				$reportEmail = filter_var($expEmail[$i], FILTER_VALIDATE_EMAIL);
				if ($reportEmail != '') {
					if ($email != '') {
						$email .= "," . $reportEmail;
					} else {
						$email .= $reportEmail;
					}
				}
			}
		}

		if (!empty($_POST['testingPoints'])) {
			$_POST['testingPoints'] = explode(",", $_POST['testingPoints']);
			$_POST['testingPoints'] = array_map('trim', $_POST['testingPoints']);
			$_POST['testingPoints'] = json_encode($_POST['testingPoints']);
		} else {
			$_POST['testingPoints'] = null;
		}

		if (isset($_POST['districtNew']) && $_POST['districtNew'] != "" && $_POST['districtId'] == 'other') {
			$_POST['districtId'] = $geolocation->addGeoLocation($_POST['districtNew'], $_POST['stateId']);
			$_POST['district'] = $_POST['districtNew'];
		}

		$data = array(
			'facility_name' => $_POST['facilityName'],
			'facility_code' => !empty($_POST['facilityCode']) ? $_POST['facilityCode'] : null,
			'vlsm_instance_id' => $instanceId,
			'other_id' => !empty($_POST['otherId']) ? $_POST['otherId'] : null,
			'facility_mobile_numbers' => $_POST['phoneNo'],
			'address' => $_POST['address'],
			'country' => $_POST['country'],
			'facility_state_id' => $_POST['stateId'],
			'facility_district_id' => $_POST['districtId'],
			'facility_state' => $_POST['state'],
			'facility_district' => $_POST['district'],
			'facility_hub_name' => $_POST['hubName'],
			'latitude' => $_POST['latitude'],
			'longitude' => $_POST['longitude'],
			'facility_emails' => $_POST['email'],
			'report_email' => $email,
			'contact_person' => $_POST['contactPerson'],
			'facility_type' => $_POST['facilityType'],
			'test_type' => (isset($_POST['testType']) && !empty($_POST['testType'])) ?  implode(', ', $_POST['testType'])  : null,
			'testing_points' => $_POST['testingPoints'],
			'header_text' => $_POST['headerText'],
			'report_format' => (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) ? json_encode($_POST['reportFormat']) : null,
			'updated_datetime' => DateUtility::getCurrentDateTime(),
			'status' => 'active'
		);

		$facilityAttributes = [];
		if (isset($_POST['allowResultUpload']) && !empty($_POST['allowResultUpload'])) {
			$facilityAttributes['allow_results_file_upload'] = $_POST['allowResultUpload'];
		}
		if (!empty($_POST['sampleType'])) {
			foreach ($_POST['sampleType'] as $testType => $sampleTypes) {
				$facilityAttributes['sampleType'][$testType] = implode(",", $sampleTypes);
			}
		}
		if (!empty($facilityAttributes)) {
			$data['facility_attributes'] = json_encode($facilityAttributes, true);
		}
		if (isset(SYSTEM_CONFIG['remoteURL']) && SYSTEM_CONFIG['remoteURL'] != "" && $_POST['fromAPI'] == "yes") {
			/* Facility sync to remote */
			$url = SYSTEM_CONFIG['remoteURL'] . '/facilities/addFacilityHelper.php';
			$apiData = array(
				"result" => $_POST,
				"api-type" => "sync",
				"Key" => "vlsm-lab-data-",
			);
			//open connection
			$ch = curl_init($url);
			$json_data = json_encode($apiData);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					'Content-Type: application/json',
					'Content-Length: ' . strlen($json_data)
				)
			);
			// execute post
			$curl_response = curl_exec($ch);
			$facilityId = $curl_response;
			if (isset($facilityId) && $facilityId > 0) {
				$data['facility_id'] = $facilityId;
			}
			//close connection
			curl_close($ch);
		}

		$db->insert($facilityTable, $data);
		$lastId = $db->getInsertId();

		if (isset($_POST['testType']) && !empty($_POST['testType'])) {
			foreach ($_POST['testType'] as $testType) {
				// Mapping facility as a Health Facility
				if (isset($_POST['facilityType']) && $_POST['facilityType'] == 1) {
					$db->insert($healthFacilityTable, array(
						'test_type' => $testType,
						'facility_id' => $lastId,
						'updated_datetime' => DateUtility::getCurrentDateTime()
					));
					// Mapping facility as a Testing Lab
				} elseif (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) {
					$data = array(
						'test_type' => $testType,
						'facility_id' => $lastId,
						'updated_datetime' => DateUtility::getCurrentDateTime()
					);
					if (isset($_POST['availablePlatforms']) && !empty($_POST['availablePlatforms'])) {
						$attributes['platforms'] = $_POST['availablePlatforms'];
					}
					if (isset($attributes) && !empty($attributes)) {
						$data['attributes'] = json_encode($attributes, true);
					}
					$db->insert($testingLabsTable, $data);
				}
			}
		}
		// Mapping facility with users
		if ($lastId > 0 && trim($_POST['selectedUser']) != '') {
			$selectedUser = explode(",", $_POST['selectedUser']);
			for ($j = 0; $j < count($selectedUser); $j++) {
				$data = array(
					'user_id' => $selectedUser[$j],
					'facility_id' => $lastId,
				);
				$db->insert($vlUserFacilityMapTable, $data);
			}
		}
		if ($lastId > 0) {
			// Mapping facility as a Testing Lab
			for ($tf = 0; $tf < count($_POST['testData']); $tf++) {
				$dataTest = array(
					'test_type' => $_POST['testData'][$tf],
					'facility_id' => $lastId,
					'monthly_target' => $_POST['monTar'][$tf],
					'suppressed_monthly_target' => $_POST['supMonTar'][$tf],
					"updated_datetime" => DateUtility::getCurrentDateTime()
				);
				$db->insert($testingLabsTable, $dataTest);
			}
		}

		if (isset($_FILES['labLogo']['name']) && $_FILES['labLogo']['name'] != "") {
			if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo")) {
				mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo", 0777, true);
			}
			mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId, 0777, true);
			$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['labLogo']['name'], PATHINFO_EXTENSION));
			$string = $general->generateRandomString(12) . ".";
			$actualImageName = "actual-logo-" . $string . $extension;
			$imageName = "logo-" . $string . $extension;
			$actualImagePath = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo") . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $actualImageName;
			if (move_uploaded_file($_FILES["labLogo"]["tmp_name"], $actualImagePath)) {

				$resizeObj = new ImageResizeUtility();
				$resizeObj = $resizeObj->setFileName($actualImagePath);
				$resizeObj->resizeToWidth(100);
				$resizeObj->save(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $imageName);

				$image = array('facility_logo' => $imageName);
				$db = $db->where('facility_id', $lastId);
				$db->update($facilityTable, $image);
			}
		}
		// Uploading signatories
		if (isset($_FILES['signature']['name']) && $_FILES['signature']['name'] != ""  && !empty($_FILES['signature']['name']) && isset($_POST['signName']) && $_POST['signName'] != "" && !empty($_POST['signName'])) {
			foreach ($_POST['signName'] as $key => $name) {
				if (isset($name) && $name != "") {
					$signData = array(
						'name_of_signatory'	=> $name,
						'designation' 		=> $_POST['designation'][$key],
						'test_types' 		=> implode(",", $_POST['testSignType'][($key + 1)]),
						'lab_id' 			=> $lastId,
						'display_order' 	=> $_POST['sortOrder'][$key],
						'signatory_status' 	=> $_POST['signStatus'][$key],
						"added_by" 			=> $_SESSION['userId'],
						"added_on" 			=> DateUtility::getCurrentDateTime()
					);

					$db->insert($labSignTable, $signData);
					$lastSignId = $db->getInsertId();
					if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs")) {
						mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs", 0777, true);
					}
					if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId)) {
						mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs"  . DIRECTORY_SEPARATOR . $lastId, 0777, true);
					}
					if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures')) {
						mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures', 0777, true);
					}
					$pathname = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures' . DIRECTORY_SEPARATOR;
					$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['signature']['name'][$key], PATHINFO_EXTENSION));
					$string = $general->generateRandomString(4) . ".";
					$imageName = $string . $extension;

					if (move_uploaded_file($_FILES["signature"]["tmp_name"][$key], $pathname . $imageName)) {
						$resizeObj = new ImageResizeUtility();
						$resizeObj = $resizeObj->setFileName($pathname . $imageName);
						$resizeObj->resizeToWidth(100);
						$resizeObj->save($pathname . $imageName);
						$image = array('signature' => $imageName);
						$db = $db->where('signatory_id', $lastSignId);
						$db->update($labSignTable, $image);
					}
				}
			}
		}

		$general->activityLog('add-facility', $_SESSION['userName'] . ' added new facility ' . $_POST['facilityName'], 'facility');
	}
	if (isset($apiData['api-type']) && $apiData['api-type'] == "sync" && !isset($_POST['fromAPI'])) {
		echo $lastId;
		exit;
	}

	if (isset($_POST['reqForm']) && $_POST['reqForm'] != '') {
		$currentDateTime = DateUtility::getCurrentDateTime();
		$data = array(
			'test_type'     => "covid19",
			'facility_id'   => $lastId,
			'updated_datetime'  => $currentDateTime
		);
		$db->insert("health_facilities", $data);
		return 1;
	} else {
		$_SESSION['alertMsg'] = _("Facility details added successfully");
		header("Location:/facilities/facilities.php");
	}
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}

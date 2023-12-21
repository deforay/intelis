<?php

use App\Registries\AppRegistry;
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;
use App\Utilities\ImageResizeUtility;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

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

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$sanitizedReportTemplate = _sanitizeFiles($_FILES['reportTemplate'], ['pdf']);
$sanitizedLabLogo = _sanitizeFiles($_FILES['labLogo'], ['png', 'jpg', 'jpeg', 'gif']);
$sanitizedSignature = _sanitizeFiles($_FILES['signature'], ['png', 'jpg', 'jpeg', 'gif']);

try {

	//Province Table
	if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) != "") {
		if (isset($_POST['provinceNew']) && $_POST['provinceNew'] != "" && $_POST['stateId'] == 'other') {
			$_POST['stateId'] = $geolocation->addGeoLocation($_POST['provinceNew']);
			$_POST['state'] = $_POST['provinceNew'];
			// if (trim($_POST['state']) != "") {
			$strSearch = (isset($_POST['provinceNew']) && trim((string) $_POST['provinceNew']) != '' && $_POST['state'] == 'other') ? $_POST['provinceNew'] : $_POST['state'];
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
		if (isset($_POST['reportEmail']) && trim((string) $_POST['reportEmail']) != '') {
			$expEmail = explode(",", (string) $_POST['reportEmail']);
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
			$_POST['testingPoints'] = explode(",", (string) $_POST['testingPoints']);
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
			'test_type' => (!empty($_POST['testType'])) ?  implode(', ', $_POST['testType'])  : null,
			'testing_points' => $_POST['testingPoints'],
			'header_text' => $_POST['headerText'],
			'report_format' => (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) ? json_encode($_POST['reportFormat']) : null,
			'updated_datetime' => DateUtility::getCurrentDateTime(),
			'status' => 'active'
		);

		$db->insert($facilityTable, $data);
		$lastId = $db->getInsertId();

		$facilityAttributes = [];
		if (!empty($_POST['allowResultUpload'])) {
			$facilityAttributes['allow_results_file_upload'] = $_POST['allowResultUpload'];
		}
		// Upload Report Template
		if ($lastId > 0 && !empty($sanitizedReportTemplate['name'])) {
			$directoryPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . "report-template";
			MiscUtility::makeDirectory($directoryPath, 0777, true);
			$string = $general->generateRandomString(12) . ".";
			$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $sanitizedReportTemplate['name'], PATHINFO_EXTENSION));
			$fileName = "report-template-" . $string . $extension;
			$filePath = $directoryPath . DIRECTORY_SEPARATOR . $fileName;
			if (move_uploaded_file($_FILES["reportTemplate"]["tmp_name"], $filePath)) {
				$facilityAttributes['report_template'] = $fileName;
			}
		}

		if (!empty($_POST['sampleType'])) {
			foreach ($_POST['sampleType'] as $testType => $sampleTypes) {
				$facilityAttributes['sampleType'][$testType] = implode(",", $sampleTypes);
			}
		}
		if ($lastId > 0 && !empty($facilityAttributes)) {
			$facilityAttributesJson = array('facility_attributes' => json_encode($facilityAttributes, true));
			$db->where('facility_id', $lastId);
			$db->update($facilityTable, $facilityAttributesJson);
		}


		if (!empty($_POST['testType'])) {
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
					if (!empty($_POST['availablePlatforms'])) {
						$attributes['platforms'] = $_POST['availablePlatforms'];
					}
					if (!empty($attributes)) {
						$data['attributes'] = json_encode($attributes, true);
					}
					$db->insert($testingLabsTable, $data);
				}
			}
		}
		// Mapping facility with users
		if ($lastId > 0 && trim((string) $_POST['selectedUser']) != '') {
			$selectedUser = explode(",", (string) $_POST['selectedUser']);
			for ($j = 0; $j < count($selectedUser); $j++) {
				$data = array(
					'user_id' => $selectedUser[$j],
					'facility_id' => $lastId,
				);
				$db->insert($vlUserFacilityMapTable, $data);
			}
		}
		if ($lastId > 0 && !empty($_POST['testData'])) {
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

		if ($lastId > 0 && isset($sanitizedLabLogo['name']) && $sanitizedLabLogo['name'] != "") {
			MiscUtility::makeDirectory(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId, 0777, true);
			$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $sanitizedLabLogo['name'], PATHINFO_EXTENSION));
			$string = $general->generateRandomString(12) . ".";
			$actualImageName = "actual-logo-" . $string . $extension;
			$imageName = "logo-" . $string . $extension;
			$actualImagePath = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo") . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $actualImageName;
			if (move_uploaded_file($_FILES["labLogo"]["tmp_name"], $actualImagePath)) {

				$resizeObj = new ImageResizeUtility($actualImagePath);
				$resizeObj->resizeToWidth(100);
				$resizeObj->save(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $imageName);

				$image = array('facility_logo' => $imageName);
				$db->where('facility_id', $lastId);
				$db->update($facilityTable, $image);
			}
		}
		// Uploading signatories
		if ($_FILES['signature']['name'] != "" && !empty($_FILES['signature']['name']) && $_POST['signName'] != "" && !empty($_POST['signName'])) {
			foreach ($_POST['signName'] as $key => $name) {
				if (isset($name) && $name != "") {
					$signData = array(
						'name_of_signatory'	=> $name,
						'designation' 		=> $_POST['designation'][$key],
						'test_types' 		=> implode(",", (array)$_POST['testSignType'][($key + 1)]),
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
						$resizeObj = new ImageResizeUtility($pathname . $imageName);
						$resizeObj->resizeToWidth(100);
						$resizeObj->save($pathname . $imageName);
						$image = array('signature' => $imageName);
						$db->where('signatory_id', $lastSignId);
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
		$_SESSION['alertMsg'] = _translate("Facility details added successfully");
		header("Location:/facilities/facilities.php");
	}
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}

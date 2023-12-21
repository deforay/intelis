<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;
use App\Utilities\ImageResizeUtility;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocation */
$geolocation = ContainerRegistry::get(GeoLocationsService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$sanitizedReportTemplate = _sanitizeFiles($_FILES['reportTemplate'], ['pdf']);
$sanitizedLabLogo = _sanitizeFiles($_FILES['labLogo'], ['png', 'jpg', 'jpeg', 'gif']);
$sanitizedSignature = _sanitizeFiles($_FILES['signature'], ['png', 'jpg', 'jpeg', 'gif']);

/* For reference we define the table names */
$tableName = "facility_details";
$facilityId = base64_decode((string) $_POST['facilityId']);
$provinceTable = "geographical_divisions";
$vlUserFacilityMapTable = "user_facility_map";
$testingLabsTable = "testing_labs";
$healthFacilityTable = "health_facilities";
$signTableName = "lab_report_signatories";

$facilityRow = $db->rawQueryOne('SELECT facility_attributes from facility_details where facility_id= ?', [$facilityId]);
$facilityAttributes = json_decode((string) $facilityRow['facility_attributes'], true);

try {
	//Province Table
	if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) != "") {
		if (isset($_POST['provinceNew']) && $_POST['provinceNew'] != "" && $_POST['stateId'] == 'other') {
			$_POST['stateId'] = $geolocation->addGeoLocation($_POST['provinceNew']);
			$_POST['state'] = $_POST['provinceNew'];
			$provinceName = (isset($_POST['provinceNew']) && trim((string) $_POST['provinceNew']) != '' && $_POST['state'] == 'other') ? $_POST['provinceNew'] : $_POST['state'];
			$facilityQuery = "SELECT geo_name FROM geographical_divisions WHERE geo_name= ?";
			$facilityInfo = $db->rawQueryOne($facilityQuery, [$provinceName]);
			if (isset($facilityInfo['geo_name'])) {
				$_POST['state'] = $facilityInfo['geo_name'];
			} else {
				$data = array(
					'geo_name' => $_POST['provinceNew'],
					'updated_datetime' => DateUtility::getCurrentDateTime(),
				);
				$db->insert($provinceTable, $data);
				$_POST['state'] = $_POST['provinceNew'];
			}
		}

		if (isset($_POST['districtNew']) && $_POST['districtNew'] != "" && $_POST['districtId'] == 'other') {
			$_POST['districtId'] = $geolocation->addGeoLocation($_POST['districtNew'], $_POST['stateId']);
			$_POST['district'] = $_POST['districtNew'];
		}

		$email = '';
		if (isset($_POST['reportEmail']) && trim((string) $_POST['reportEmail']) != '') {
			$expEmail = explode(",", (string) $_POST['reportEmail']);
			if (!empty($_POST['reportEmail'])) {
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
		}


		if (!empty($_POST['testingPoints'])) {
			$_POST['testingPoints'] = explode(",", (string) $_POST['testingPoints']);
			$_POST['testingPoints'] = array_map('trim', $_POST['testingPoints']);
			$_POST['testingPoints'] = json_encode($_POST['testingPoints']);
		} else {
			$_POST['testingPoints'] = null;
		}

		$data = array(
			'facility_name' => $_POST['facilityName'],
			'facility_code' => !empty($_POST['facilityCode']) ? $_POST['facilityCode'] : null,
			'other_id' => !empty($_POST['otherId']) ? $_POST['otherId'] : null,
			'facility_mobile_numbers' => $_POST['phoneNo'],
			'address' => $_POST['address'],
			'country' => $_POST['country'],
			'facility_state_id' => $_POST['stateId'],
			'facility_district_id' => $_POST['districtId'],
			'facility_state' => (isset($_POST['oldState']) && $_POST['oldState'] != "") ? $_POST['oldState'] : $_POST['state'],
			'facility_district' => (isset($_POST['oldDistrict']) && $_POST['oldDistrict'] != "") ? $_POST['oldDistrict'] : $_POST['district'],
			'facility_hub_name' => $_POST['hubName'],
			'latitude' => $_POST['latitude'],
			'longitude' => $_POST['longitude'],
			'facility_emails' => $_POST['email'],
			'report_email' => $email,
			'contact_person' => $_POST['contactPerson'],
			'facility_type' => $_POST['facilityType'],
			'test_type' => implode(', ', $_POST['testType'] ?? []),
			'testing_points' => $_POST['testingPoints'],
			'header_text' => $_POST['headerText'],
			'report_format' => (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) ? json_encode($_POST['reportFormat'], true) : null,
			'updated_datetime' => DateUtility::getCurrentDateTime(),
			'status' => $_POST['status']
		);

		//$facilityAttributes = [];
		if (!empty($_POST['allowResultUpload'])) {
			$facilityAttributes['allow_results_file_upload'] = $_POST['allowResultUpload'];
		}
		if (!empty($_POST['sampleType'])) {
			foreach ($_POST['sampleType'] as $testType => $sampleTypes) {
				$facilityAttributes['sampleType'][$testType] = implode(",", $sampleTypes ?? []);
			}
		}
		// Upload Report Template
		if (isset($sanitizedReportTemplate['name']) && $sanitizedReportTemplate['name'] != "") {

			$directoryPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . "report-template";
			MiscUtility::removeDirectory($directoryPath);
			MiscUtility::makeDirectory($directoryPath, 0777, true);
			$string = $general->generateRandomString(12) . ".";
			$extension = strtolower(pathinfo($directoryPath . DIRECTORY_SEPARATOR . $sanitizedReportTemplate['name'], PATHINFO_EXTENSION));
			$fileName = "report-template-" . $string . $extension;
			$filePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $fileName;
			if (move_uploaded_file($_FILES["reportTemplate"]["tmp_name"], $filePath)) {
				$facilityAttributes['report_template'] = $fileName;
			}
		}
		if (!empty($facilityAttributes)) {
			$data['facility_attributes'] = json_encode($facilityAttributes, true);
		}

		$db->where('facility_id', $facilityId);
		$id = $db->update($tableName, $data);

		// Mapping facility with users
		$db->where('facility_id', $facilityId);
		$delId = $db->delete($vlUserFacilityMapTable);
		if ($facilityId > 0 && trim((string) $_POST['selectedUser']) != '') {
			$selectedUser = explode(",", (string) $_POST['selectedUser']);
			if (!empty($_POST['selectedUser'])) {
				for ($j = 0; $j < count($selectedUser); $j++) {
					$data = array(
						'user_id' => $selectedUser[$j],
						'facility_id' => $facilityId,
					);
					$db->insert($vlUserFacilityMapTable, $data);
				}
			}
		}
		$lastId = $facilityId;
		// Mapping facility as a Testing Lab
		// if (isset($_POST['testType']) && !empty($_POST['testType'])) {
		// 	$db->where('test_type NOT IN(' . sprintf("'%s'", implode("', '", $_POST['testType'])) . ')');
		// 	$db->where('facility_id', $facilityId);
		// 	$delId = $db->delete($testingLabsTable);
		// } else {
		// 	$db->where('facility_id', $facilityId);
		// 	$delId = $db->delete($testingLabsTable);
		// }
		if ($lastId > 0) {

			if (!empty($_POST['testType'])) {

				if (isset($_POST['facilityType']) && $_POST['facilityType'] == 1) {
					$db->where('facility_id', $facilityId);
					$delId = $db->delete($healthFacilityTable);
				}
				if (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) {

					$db->where('facility_id', $facilityId);
					$delId = $db->delete($testingLabsTable);
				}
				$tid = $hid = 0;
				foreach ($_POST['testType'] as $testType) {
					// Mapping facility as a Health Facility
					if (isset($_POST['facilityType']) && $_POST['facilityType'] == 1) {
						$hid = $db->insert($healthFacilityTable, array(
							'test_type' => $testType,
							'facility_id' => $facilityId,
							'updated_datetime' => DateUtility::getCurrentDateTime()
						));
						// Mapping facility as a Testing Lab
					} else if (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) {
						$data = array(
							'test_type' => $testType,
							'facility_id' => $facilityId,
							'updated_datetime' => DateUtility::getCurrentDateTime()
						);
						if (!empty($_POST['availablePlatforms'])) {
							$attributes['platforms'] = $_POST['availablePlatforms'];
						}
						if (!empty($attributes)) {
							$data['attributes'] = json_encode($attributes, true);
						}
						$tid = $db->insert($testingLabsTable, $data);
					}
				}
			}

			if (!empty($_POST['testData'])) {
				for ($tf = 0; $tf < count($_POST['testData']); $tf++) {
					$dataTest = array(
						'test_type' => $_POST['testData'][$tf],
						'facility_id' => $lastId,
						'monthly_target' => $_POST['monTar'][$tf],
						'suppressed_monthly_target' => $_POST['supMonTar'][$tf],
						"updated_datetime" => DateUtility::getCurrentDateTime()
					);

					$updateColumns = array_keys($dataTest);

					$db->onDuplicate($updateColumns)
						->insert($testingLabsTable, $dataTest);
				}
			}
		}

		if (isset($_POST['removedLabLogoImage']) && trim((string) $_POST['removedLabLogoImage']) != "" && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $_POST['removedLabLogoImage'])) {
			unlink(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . "actual-" . $_POST['removedLabLogoImage']);
			unlink(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $_POST['removedLabLogoImage']);
			$data = array('facility_logo' => null);
			$db->where('facility_id', $lastId);
			$db->update($tableName, $data);
		}

		if (isset($sanitizedLabLogo['name']) && $sanitizedLabLogo['name'] != "") {
			if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo")) {
				mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo", 0777, true);
			}
			if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId)) {
				mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId, 0777, true);
			}


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
				$db->update($tableName, $image);
			}
		}
		// Uploading signatories
		if ($_FILES['signature']['name'] != "" && !empty($_FILES['signature']['name']) && $_POST['signName'] != "" && !empty($_POST['signName'])) {
			$deletedRow = explode(",", (string) $_POST['deletedRow']);
			foreach ($deletedRow as $delete) {
				$db->where('signatory_id', $delete);
				$db->delete($signTableName);
			}
			$pathname = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures' . DIRECTORY_SEPARATOR;
			// unlink($pathname);
			foreach ($_POST['signName'] as $key => $name) {
				if (isset($name) && $name != "") {
					$signData = array(
						'name_of_signatory'	=> $name,
						'designation' 		=> $_POST['designation'][$key],
						'test_types' 		=> implode(",", $_POST['testSignType'][($key + 1)] ?? []),
						'lab_id' 			=> $lastId,
						'display_order' 	=> $_POST['sortOrder'][$key],
						'signatory_status' 	=> $_POST['signStatus'][$key]
					);
					if (isset($_POST['signId'][$key]) && $_POST['signId'][$key] != "") {
						$db->where('signatory_id', $_POST['signId'][$key]);
						$db->update($signTableName, $signData);
						$lastSignId = $_POST['signId'][$key];
					} else {
						$signData['added_by'] = $_SESSION['userId'];
						$signData['added_on'] = DateUtility::getCurrentDateTime();
						$db->insert($signTableName, $signData);
						$lastSignId = $db->getInsertId();
					}
					if (!empty($_FILES["signature"]["tmp_name"][$key])) {
						if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs")) {
							mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs", 0777, true);
						}
						if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId)) {
							mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs"  . DIRECTORY_SEPARATOR . $lastId, 0777, true);
						}
						if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures')) {
							mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures', 0777, true);
						}

						$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['signature']['name'][$key], PATHINFO_EXTENSION));
						$string = $general->generateRandomString(4) . ".";
						$imageName = $string . $extension;
						if (move_uploaded_file($_FILES["signature"]["tmp_name"][$key], $pathname . $imageName)) {

							$resizeObj = new ImageResizeUtility($pathname . $imageName);
							$resizeObj->resizeToWidth(100);
							$resizeObj->save($pathname . $imageName);

							$image = array('signature' => $imageName);
							$db->where('signatory_id', $lastSignId);
							$db->update($signTableName, $image);
						}
					}
				}
			}
		}

		$_SESSION['alertMsg'] = _translate("Facility details updated successfully");
		$general->activityLog('update-facility', $_SESSION['userName'] . ' updated facility ' . $_POST['facilityName'], 'facility');
	}
	header("Location:facilities.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}

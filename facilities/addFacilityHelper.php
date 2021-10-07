<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

include_once(APPLICATION_PATH . '/includes/ImageResize.php');

#require_once('../startup.php');  


$general = new \Vlsm\Models\General($db);
$geolocation = new \Vlsm\Models\GeoLocations($db);
/* For reference we define the table names */
$tableName = "facility_details";
$tableName1 = "province_details";
$tableName2 = "vl_user_facility_map";
$tableName3 = "testing_labs";
$tableName4 = "health_facilities";
$signTableName = "lab_report_signatories";
// print_r($_POST);die;
try {
	if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != "") {
		if (isset($_POST['provinceNew']) && $_POST['provinceNew'] != "" && $_POST['stateId'] == 'other') {
			$_POST['stateId'] = $geolocation->addNewQuickGeoLocation($_POST['provinceNew']);
			$_POST['state'] = $_POST['provinceNew'];
			// if (trim($_POST['state']) != "") {
			$strSearch = (isset($_POST['provinceNew']) && trim($_POST['provinceNew']) != '' && $_POST['state'] == 'other') ? $_POST['provinceNew'] : $_POST['state'];
			$facilityQuery = "SELECT province_name from province_details where province_name='" . $strSearch . "'";
			$facilityInfo = $db->query($facilityQuery);
			if (isset($facilityInfo[0]['province_name'])) {
				$_POST['state'] = $facilityInfo[0]['province_name'];
			} else {
				$data = array(
					'province_name' => $_POST['provinceNew'],
					'updated_datetime' => $general->getDateTime(),
				);
				$db->insert($tableName1, $data);
				$_POST['state'] = $_POST['provinceNew'];
			}
		}
		$instanceId = '';
		if (isset($_SESSION['instanceId'])) {
			$instanceId = $_SESSION['instanceId'];
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
			$_POST['testingPoints'] = array_map('trim', $_POST['testingPoints']);;
			$_POST['testingPoints'] = json_encode($_POST['testingPoints']);
		} else {
			$_POST['testingPoints'] = null;
		}

		if (isset($_POST['districtNew']) && $_POST['districtNew'] != "" && $_POST['districtId'] == 'other') {
			$_POST['districtId'] = $geolocation->addNewQuickGeoLocation($_POST['districtNew'], $_POST['stateId']);
			$_POST['district'] = $_POST['districtNew'];
		}

		$data = array(
			'facility_name' => $_POST['facilityName'],
			'facility_code' => $_POST['facilityCode'],
			'vlsm_instance_id' => $instanceId,
			'other_id' => $_POST['otherId'],
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
			'updated_datetime' => $general->getDateTime(),
			'status' => 'active'
		);

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		if (isset($_POST['testType']) && !empty($_POST['testType'])) {
			foreach ($_POST['testType'] as $testType) {
				if (isset($_POST['facilityType']) && $_POST['facilityType'] == 1) {
					$db->insert($tableName4, array(
						'test_type' => $testType,
						'facility_id' => $lastId,
						'updated_datetime' => $general->getDateTime()
					));
				} else if (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) {
					$db->insert($tableName3, array(
						'test_type' => $testType,
						'facility_id' => $lastId,
						'updated_datetime' => $general->getDateTime()
					));
				}
			}
		}

		if ($lastId > 0 && trim($_POST['selectedUser']) != '') {
			$selectedUser = explode(",", $_POST['selectedUser']);
			for ($j = 0; $j < count($selectedUser); $j++) {
				$data = array(
					'user_id' => $selectedUser[$j],
					'facility_id' => $lastId,
				);
				$db->insert($tableName2, $data);
			}
		}
		if ($lastId > 0) {
			for ($tf = 0; $tf < count($_POST['testData']); $tf++) {
				$dataTest = array(
					'test_type' => $_POST['testData'][$tf],
					'facility_id' => $lastId,
					'monthly_target' => $_POST['monTar'][$tf],
					'suppressed_monthly_target' => $_POST['supMonTar'][$tf],
					"updated_datetime" => $general->getDateTime()
				);
				$db->insert($tableName3, $dataTest);
			}
		}

		if (isset($_FILES['labLogo']['name']) && $_FILES['labLogo']['name'] != "") {
			if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo")) {
				mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo", 0777, true);
			}
			mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId);
			$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['labLogo']['name'], PATHINFO_EXTENSION));
			$string = $general->generateRandomString(6) . ".";
			$imageName = "logo" . $string . $extension;
			if (move_uploaded_file($_FILES["labLogo"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $imageName)) {

				$resizeObj = new \Vlsm\Helpers\ImageResize(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $imageName);
				$resizeObj->resizeToWidth(80);
				$resizeObj->save(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $imageName);

				$image = array('facility_logo' => $imageName);
				$db = $db->where('facility_id', $lastId);
				$db->update($tableName, $image);
			}
		}

		if (isset($_FILES['signature']['name']) && $_FILES['signature']['name'] != ""  && count($_FILES['signature']['name']) > 0 && isset($_POST['signName']) && $_POST['signName'] != "" && count($_POST['signName']) > 0) {
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
						"added_on" 			=> $general->getDateTime()
					);

					$db->insert($signTableName, $signData);
					$lastSignId = $db->getInsertId();
					if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs")) {
						mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs");
					}
					if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId)) {
						mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs"  . DIRECTORY_SEPARATOR . $lastId);
					}
					if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures')) {
						mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures');
					}
					$pathname = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures' . DIRECTORY_SEPARATOR;
					$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['signature']['name'][$key], PATHINFO_EXTENSION));
					$string = $general->generateRandomString(4) . ".";
					$imageName = $string . $extension;

					if (move_uploaded_file($_FILES["signature"]["tmp_name"][$key], $pathname . $imageName)) {
						$resizeObj = new \Vlsm\Helpers\ImageResize($pathname . $imageName);
						$resizeObj->resizeToWidth(80);
						$resizeObj->save($pathname . $imageName);
						$image = array('signature' => $imageName);
						$db = $db->where('signatory_id', $lastSignId);
						$db->update($signTableName, $image);
					}
				}
			}
		}

		$general->activityLog('add-facility', $_SESSION['userName'] . ' added new facility ' . $_POST['facilityName'], 'facility');
	}
	if (isset($_POST['reqForm']) && $_POST['reqForm'] != '') {
		$currentDateTime = $general->getDateTime();
		$data = array(
			'test_type'     => "covid19",
			'facility_id'   => $lastId,
			'updated_datetime'  => $currentDateTime
		);
		$db->insert("health_facilities", $data);
		return 1;
	} else {
		$_SESSION['alertMsg'] = "Facility details added successfully";
		header("location:facilities.php");
	}
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}

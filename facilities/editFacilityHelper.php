<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
#require_once('../startup.php');  
include_once(APPLICATION_PATH . '/includes/ImageResize.php');

$general = new \Vlsm\Models\General($db);
$geolocation = new \Vlsm\Models\GeoLocations($db);
/* For reference we define the table names */
$tableName = "facility_details";
$facilityId = base64_decode($_POST['facilityId']);
$tableName1 = "province_details";
$tableName2 = "vl_user_facility_map";
$tableName3 = "testing_labs";
$tableName4 = "health_facilities";
$signTableName = "lab_report_signatories";
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

		if (isset($_POST['districtNew']) && $_POST['districtNew'] != "" && $_POST['districtId'] == 'other') {
			$_POST['districtId'] = $geolocation->addNewQuickGeoLocation($_POST['districtNew'], $_POST['stateId']);
			$_POST['district'] = $_POST['districtNew'];
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

		$data = array(
			'facility_name' => $_POST['facilityName'],
			'facility_code' => $_POST['facilityCode'],
			'other_id' => $_POST['otherId'],
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
			'test_type' => implode(', ', $_POST['testType']),
			'testing_points' => $_POST['testingPoints'],
			'header_text' => $_POST['headerText'],
			'report_format' => (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) ? json_encode($_POST['reportFormat'], true) : null,
			'updated_datetime' => $general->getDateTime(),
			'status' => $_POST['status']
		);
		$db = $db->where('facility_id', $facilityId);
		$id = $db->update($tableName, $data);
		$db = $db->where('facility_id', $facilityId);
		$delId = $db->delete($tableName2);
		if ($facilityId > 0 && trim($_POST['selectedUser']) != '') {
			$selectedUser = explode(",", $_POST['selectedUser']);
			for ($j = 0; $j < count($selectedUser); $j++) {
				$data = array(
					'user_id' => $selectedUser[$j],
					'facility_id' => $facilityId,
				);
				$db->insert($tableName2, $data);
			}
		}
		$lastId = $facilityId;

		if (isset($_POST['testType']) && !empty($_POST['testType'])) {
			$db = $db->where('test_type NOT IN(' . sprintf("'%s'", implode("', '", $_POST['testType'])) . ')');
			$db = $db->where('facility_id', $facilityId);
			$delId = $db->delete($tableName3);
		} else {
			$db = $db->where('facility_id', $facilityId);
			$delId = $db->delete($tableName3);
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

			if (isset($_POST['testType']) && !empty($_POST['testType'])) {

				$db = $db->where('facility_id', $facilityId);
				$delId = $db->delete($tableName4);
				$tid = $hid = 0;
				foreach ($_POST['testType'] as $testType) {
					if (isset($_POST['facilityType']) && $_POST['facilityType'] == 1) {
						$hid = $db->insert($tableName4, array(
							'test_type' => $testType,
							'facility_id' => $facilityId,
							'updated_datetime' => $general->getDateTime()
						));
					} else if (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) {
						$tid = $db->insert($tableName3, array(
							'test_type' => $testType,
							'facility_id' => $facilityId,
							'updated_datetime' => $general->getDateTime()
						));
					}
				}
			}
		}
		if (isset($_POST['removedLabLogoImage']) && trim($_POST['removedLabLogoImage']) != "" && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $_POST['removedLabLogoImage'])) {
			unlink(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $_POST['removedLabLogoImage']);
			$data = array('facility_logo' => '');
			$db = $db->where('facility_id', $lastId);
			$db->update($tableName, $data);
		}

		if (isset($_FILES['labLogo']['name']) && $_FILES['labLogo']['name'] != "") {
			if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo")) {
				mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo");
			}
			if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId)) {
				mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId);
			}


			$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['labLogo']['name'], PATHINFO_EXTENSION));
			$string = $general->generateRandomString(6) . ".";
			$imageName = "logo" . $string . $extension;
			if (move_uploaded_file($_FILES["labLogo"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $imageName)) {
				$resizeObj = new ImageResize(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $imageName);
				$resizeObj->resizeImage(80, 80, 'auto');
				$resizeObj->saveImage(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $imageName, 100);
				$image = array('facility_logo' => $imageName);
				$db = $db->where('facility_id', $lastId);
				$db->update($tableName, $image);
			}
		}
		if (isset($_FILES['signature']['name']) && $_FILES['signature']['name'] != ""  && count($_FILES['signature']['name']) > 0 && isset($_POST['signName']) && $_POST['signName'] != "" && count($_POST['signName']) > 0) {
			$deletedRow = explode(",", $_POST['deletedRow']);
			foreach ($deletedRow as $delete) {
				$db = $db->where('signatory_id', $delete);
				$db->delete($signTableName);
			}
			$pathname = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures' . DIRECTORY_SEPARATOR;
			// unlink($pathname);
			foreach ($_POST['signName'] as $key => $name) {
				if (isset($name) && $name != "") {
					$signData = array(
						'name_of_signatory'	=> $name,
						'designation' 		=> $_POST['designation'][$key],
						'test_types' 		=> implode(",", $_POST['testSignType'][($key + 1)]),
						'lab_id' 			=> $lastId,
						'display_order' 	=> $_POST['sortOrder'][$key],
						'signatory_status' 	=> $_POST['signStatus'][$key]
					);
					if (isset($_POST['signId'][$key]) && $_POST['signId'][$key] != "") {
						$db = $db->where('signatory_id', $_POST['signId'][$key]);
						$db->update($signTableName, $signData);
						$lastSignId = $_POST['signId'][$key];
					} else {
						$signData['added_by'] = $_SESSION['userId'];
						$signData['added_on'] = $general->getDateTime();
						$db->insert($signTableName, $signData);
						$lastSignId = $db->getInsertId();
					}
					if (isset($_FILES["signature"]["tmp_name"][$key]) && !empty($_FILES["signature"]["tmp_name"][$key])) {
						if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs")) {
							mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs");
						}
						if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId)) {
							mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs"  . DIRECTORY_SEPARATOR . $lastId);
						}
						if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures')) {
							mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . 'signatures');
						}

						$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['signature']['name'][$key], PATHINFO_EXTENSION));
						$string = $general->generateRandomString(4) . ".";
						$imageName = $string . $extension;
						if (move_uploaded_file($_FILES["signature"]["tmp_name"][$key], $pathname . $imageName)) {
							$resizeObj = new ImageResize($pathname . $imageName);
							$resizeObj->resizeImage(80, 80, 'auto');
							$resizeObj->saveImage($pathname . $imageName, 100);
							$image = array('signature' => $imageName);
							$db = $db->where('signatory_id', $lastSignId);
							$db->update($signTableName, $image);
						}
					}
				}
			}
		}

		$_SESSION['alertMsg'] = "Facility details updated successfully";
		$general->activityLog('update-facility', $_SESSION['userName'] . ' updated facility ' . $_POST['facilityName'], 'facility');
	}
	header("location:facilities.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}

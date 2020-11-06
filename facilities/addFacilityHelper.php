<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

include_once(APPLICATION_PATH . '/includes/ImageResize.php');

#require_once('../startup.php');  


$general = new \Vlsm\Models\General($db);



$tableName = "facility_details";
$tableName1 = "province_details";
$tableName2 = "vl_user_facility_map";
$tableName3 ="testing_labs";
try {
	if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != "") {
		if (trim($_POST['state']) != "") {
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


		if(!empty($_POST['testingPoints'])){
			$_POST['testingPoints'] = explode(",", $_POST['testingPoints']);
			$_POST['testingPoints'] = array_map('trim', $_POST['testingPoints']);;
			$_POST['testingPoints'] = json_encode($_POST['testingPoints']);
		}else{
			$_POST['testingPoints'] = null;
		}


		$data = array(
			'facility_name' => $_POST['facilityName'],
			'facility_code' => $_POST['facilityCode'],
			'vlsm_instance_id' => $instanceId,
			'other_id' => $_POST['otherId'],
			'facility_mobile_numbers' => $_POST['phoneNo'],
			'address' => $_POST['address'],
			'country' => $_POST['country'],
			'facility_state' => $_POST['state'],
			'facility_district' => $_POST['district'],
			'facility_hub_name' => $_POST['hubName'],
			'latitude' => $_POST['latitude'],
			'longitude' => $_POST['longitude'],
			'facility_emails' => $_POST['email'],
			'report_email' => $email,
			'contact_person' => $_POST['contactPerson'],
			'facility_type' => $_POST['facilityType'],
			'testing_points' => $_POST['testingPoints'],
			'header_text' => $_POST['headerText'],
			'updated_datetime' => $general->getDateTime(),
			'status' => 'active'
		);

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();
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
					"updated_datetime" => $general->getDateTime()
				);
				$db->insert($tableName3, $dataTest);
			}
		}

		if (isset($_FILES['labLogo']['name']) && $_FILES['labLogo']['name'] != "") {
			if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo")) {
				mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo");
			}
			mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId);
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

		$_SESSION['alertMsg'] = "Facility details added successfully";
		$general->activityLog('add-facility', $_SESSION['userName'] . ' added new facility ' . $_POST['facilityName'], 'facility');
	}
	header("location:facilities.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}

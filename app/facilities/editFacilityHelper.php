<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Utilities\JsonUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Services\STS\TokensService;
use Laminas\Diactoros\UploadedFile;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;
use App\Utilities\ImageResizeUtility;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocation */
$geolocation = ContainerRegistry::get(GeoLocationsService::class);

/** @var FacilitiesService $facilityService */
$facilityService = ContainerRegistry::get(FacilitiesService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

// Get the uploaded files from the request object
$uploadedFiles = $request->getUploadedFiles();


try {
	// Sanitize and validate the uploaded files
	$sanitizedLabLogo = $sanitizedSignature = $sanitizedReportTemplate = "";
	if (isset($uploadedFiles['reportTemplate']))
		$sanitizedReportTemplate = _sanitizeFiles($uploadedFiles['reportTemplate'], ['pdf']);
	if (isset($uploadedFiles['labLogo']))
		$sanitizedLabLogo = _sanitizeFiles($uploadedFiles['labLogo'], ['png', 'jpg', 'jpeg', 'gif']);
	if (isset($uploadedFiles['signature']))
		$sanitizedSignature = _sanitizeFiles($uploadedFiles['signature'], ['png', 'jpg', 'jpeg', 'gif']);


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
				$geoData = array(
					'geo_name' => $_POST['provinceNew'],
					'updated_datetime' => DateUtility::getCurrentDateTime(),
				);
				$db->insert($provinceTable, $geoData);
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

		$data = [
			'facility_name' => $_POST['facilityName'],
			'facility_code' => $_POST['facilityCode'] ?? null,
			'other_id' => !empty($_POST['otherId']) ? $_POST['otherId'] : null,
			'facility_mobile_numbers' => $_POST['phoneNo'] ?? null,
			'address' => $_POST['address'] ?? null,
			'country' => $_POST['country'] ?? null,
			'facility_state_id' => $_POST['stateId'] ?? null,
			'facility_district_id' => $_POST['districtId'] ?? null,
			'facility_state' => (isset($_POST['oldState']) && $_POST['oldState'] != "") ? $_POST['oldState'] : $_POST['state'],
			'facility_district' => (isset($_POST['oldDistrict']) && $_POST['oldDistrict'] != "") ? $_POST['oldDistrict'] : $_POST['district'],
			'facility_hub_name' => $_POST['hubName'] ?? null,
			'latitude' => $_POST['latitude'] ?? null,
			'longitude' => $_POST['longitude'] ?? null,
			'facility_emails' => $_POST['email'] ?? null,
			'report_email' => $email,
			'contact_person' => $_POST['contactPerson'],
			'facility_type' => $_POST['facilityType'],
			'test_type' => implode(', ', $_POST['testType'] ?? []),
			'testing_points' => $_POST['testingPoints'],
			'header_text' => $_POST['headerText'],
			// 'report_format' => (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) ? json_encode($_POST['reportFormat'], true) : null,
			'updated_datetime' => DateUtility::getCurrentDateTime(),
			'status' => $_POST['status']
		];

		//$facilityAttributes = [];
		if (!empty($_POST['allowResultUpload'])) {
			$facilityAttributes['allow_results_file_upload'] = $_POST['allowResultUpload'];
		}
		if (!empty($_POST['sampleType'])) {
			foreach ($_POST['sampleType'] as $testType => $sampleTypes) {
				$facilityAttributes['sampleType'][$testType] = implode(",", $sampleTypes ?? []);
			}
		}
		if (!empty($_POST['displayPagenoInFooter'])) {
			$facilityAttributes['display_page_number_in_footer'] = $_POST['displayPagenoInFooter'];
		}
		if (!empty($_POST['displaySignatureTable'])) {
			$facilityAttributes['display_signature_table'] = $_POST['displaySignatureTable'];
		}
		if (!empty($_POST['reportTopMargin'])) {
			$facilityAttributes['report_top_margin'] = $_POST['reportTopMargin'];
		}
		if (!empty($_POST['bottomTextLocation'])) {
			$facilityAttributes['bottom_text_location'] = $_POST['bottomTextLocation'];
		}
		/* echo "<pre>";
		print_r($_POST);
		print_r($uploadedFiles['reportTemplate']);
		die; */
		if ($data['facility_type'] == 2) {
			if (isset($_POST['testTypeFile']) && !empty($_POST['testTypeFile'])) {
				$fileResponse = [];
				$directories = [UPLOAD_PATH, 'labs', $facilityId, 'report-template'];
				$currentPath = '';
				foreach ($directories as $directory) {
					$currentPath = $currentPath === '' ? $directory : $currentPath . DIRECTORY_SEPARATOR . $directory;
					MiscUtility::makeDirectory($currentPath, 0777); // will just skip if exists
				}
				foreach ($_POST['testTypeFile'] as $key => $test) {
					$sanitizedReportTemplate = _sanitizeFiles($uploadedFiles['reportTemplate'][$key], ['pdf']);
					if (isset($uploadedFiles['reportTemplate'][$key]) && $sanitizedReportTemplate instanceof UploadedFile && $sanitizedReportTemplate->getError() === UPLOAD_ERR_OK) {
						$directoryPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $test . DIRECTORY_SEPARATOR . $facilityId;
						MiscUtility::makeDirectory($directoryPath, 0777, true);
						$string = MiscUtility::generateRandomString(12) . "-" . $test . ".";
						$extension = MiscUtility::getFileExtension($sanitizedReportTemplate->getClientFilename());
						$fileName = "report-template-" . $string . $extension;
						$filePath = $directoryPath . DIRECTORY_SEPARATOR . $fileName;
						$fileResponse[$test]['file'] = $fileName;
						$fileResponse[$test]['mtop'] = $_POST['headerMargin'][$key];

						// Move the uploaded file to the desired location
						$sanitizedReportTemplate->moveTo($filePath);
					} else {
						$fileResponse[$test]['file'] = $_POST['oldTemplate'][$key];
						$fileResponse[$test]['mtop'] = $_POST['headerMargin'][$key];
					}
					if (isset($_POST['deleteTemplate'][$key]) && !empty($_POST['deleteTemplate'][$key])) {
						unlink(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR  . $test . DIRECTORY_SEPARATOR . $facilityId);
						$fileResponse[$test]['file'] = $_POST['oldTemplate'][$key];
						$fileResponse[$test]['mtop'] = $_POST['headerMargin'][$key];
					}
				}
				$reportFormatJson = JsonUtility::jsonToSetString(json_encode($fileResponse), 'report_format');
				$db->where('facility_id', $facilityId);
				$db->update('facility_details', ['report_format' => $db->func($reportFormatJson)]);
			}
		}

		// Mapping facility with users
		$db->where('facility_id', $facilityId);
		$delId = $db->delete($vlUserFacilityMapTable);
		if ($facilityId > 0 && trim((string) $_POST['selectedUser']) != '') {
			$selectedUser = explode(",", (string) $_POST['selectedUser']);
			if (!empty($_POST['selectedUser'])) {
				for ($j = 0; $j < count($selectedUser); $j++) {
					$uData = array(
						'user_id' => $selectedUser[$j],
						'facility_id' => $facilityId,
					);
					$db->insert($vlUserFacilityMapTable, $uData);
				}
			}
		}

		// Mapping facility as a Testing Lab
		// if (isset($_POST['testType']) && !empty($_POST['testType'])) {
		// 	$db->where('test_type NOT IN(' . sprintf("'%s'", implode("', '", $_POST['testType'])) . ')');
		// 	$db->where('facility_id', $facilityId);
		// 	$delId = $db->delete($testingLabsTable);
		// } else {
		// 	$db->where('facility_id', $facilityId);
		// 	$delId = $db->delete($testingLabsTable);
		// }
		if ($facilityId > 0) {

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
						$facilityTypeData = [
							'test_type' => $testType,
							'facility_id' => $facilityId,
							'updated_datetime' => DateUtility::getCurrentDateTime()
						];
						if (!empty($_POST['availablePlatforms'])) {
							$attributes['platforms'] = $_POST['availablePlatforms'];
						}
						if (!empty($attributes)) {
							$data['attributes'] = json_encode($attributes, true);
						}
						$tid = $db->insert($testingLabsTable, $facilityTypeData);
					}
				}
			}

			if (!empty($_POST['testData'])) {
				for ($tf = 0; $tf < count($_POST['testData']); $tf++) {
					$dataTest = [
						'test_type' => $_POST['testData'][$tf],
						'facility_id' => $facilityId,
						'monthly_target' => $_POST['monTar'][$tf],
						'suppressed_monthly_target' => $_POST['supMonTar'][$tf],
						"updated_datetime" => DateUtility::getCurrentDateTime()
					];

					$updateColumns = array_keys($dataTest);

					$db->upsert($testingLabsTable, $dataTest, $updateColumns);
				}
			}
		}

		if (isset($_POST['removedLabLogoImage']) && trim((string) $_POST['removedLabLogoImage']) != "" && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . $_POST['removedLabLogoImage'])) {
			unlink(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . "actual-" . $_POST['removedLabLogoImage']);
			unlink(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . $_POST['removedLabLogoImage']);
			$data['facility_logo'] = null;
			// $db->where('facility_id', $facilityId);
			// $db->update('facility_details',  $data);
		}


		if ($sanitizedReportTemplate instanceof UploadedFile && $sanitizedReportTemplate->getError() === UPLOAD_ERR_OK) {

			$directoryPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . "report-template";
			MiscUtility::makeDirectory($directoryPath, 0777, true);
			$string = MiscUtility::generateRandomString(12) . ".";
			$extension = MiscUtility::getFileExtension($sanitizedReportTemplate->getClientFilename());
			$fileName = "report-template-" . $string . $extension;
			$filePath = $directoryPath . DIRECTORY_SEPARATOR . $fileName;

			// Move the uploaded file to the desired location
			$sanitizedReportTemplate->moveTo($filePath);

			$facilityAttributes['report_template'] = $fileName;
		}

		if (!empty($facilityAttributes)) {
			$data['facility_attributes'] = json_encode($facilityAttributes, true);
		}


		if ($sanitizedLabLogo instanceof UploadedFile && $sanitizedLabLogo->getError() === UPLOAD_ERR_OK) {
			MiscUtility::makeDirectory(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityId, 0777, true);
			$extension = MiscUtility::getFileExtension($sanitizedLabLogo->getClientFilename());
			$string = MiscUtility::generateRandomString(12) . ".";
			$actualImageName = "actual-logo-" . $string . $extension;
			$imageName = "logo-" . $string . $extension;
			$actualImagePath = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo") . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . $actualImageName;

			// Move the uploaded file to the desired location
			$sanitizedLabLogo->moveTo($actualImagePath);

			// Resize the image
			$resizeObj = new ImageResizeUtility($actualImagePath);
			$resizeObj->resizeToWidth(100);
			$resizeObj->save(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . $imageName);


			$data['facility_logo'] = $imageName;
		}

		$db->where('facility_id', $facilityId);
		$id = $db->update('facility_details', $data);

		if ($data['facility_type'] == 2) {
			$facilityInfo = $facilityService->getFacilityById($facilityId);
			if ($facilityInfo['sts_token'] == "" || $facilityInfo['sts_token'] == NULL) {
				$stsTokensService->createToken($facilityId);
			}
		}

		// Uploading signatories
		if (!empty($sanitizedSignature) && !empty($_POST['signName'])) {
			foreach ($_POST['signName'] as $key => $name) {
				if (isset($name) && $name != "" && isset($sanitizedSignature[$key]) && $sanitizedSignature[$key] instanceof UploadedFile && $sanitizedSignature[$key]->getError() === UPLOAD_ERR_OK) {
					$signData = [
						'name_of_signatory' => $name,
						'designation' => $_POST['designation'][$key],
						'test_types' => implode(",", (array)$_POST['testSignType'][($key + 1)]),
						'lab_id' => $facilityId,
						'display_order' => $_POST['sortOrder'][$key],
						'signatory_status' => $_POST['signStatus'][$key],
						"added_by" => $_SESSION['userId'],
						"added_on" => DateUtility::getCurrentDateTime()
					];

					$pathname = MiscUtility::buildSafePath(UPLOAD_PATH, ['labs', $facilityId, 'signatures']) . DIRECTORY_SEPARATOR;
					$extension = MiscUtility::getFileExtension($sanitizedSignature[$key]->getClientFilename());
					$imageName = MiscUtility::generateRandomString(12) . ".";
					$imageName = $imageName . $extension;

					// Move the uploaded file to the desired location
					$sanitizedSignature[$key]->moveTo($pathname . $imageName);

					// Resize the image
					$resizeObj = new ImageResizeUtility($pathname . $imageName);
					$resizeObj->resizeToWidth(100);
					$resizeObj->save($pathname . $imageName);
					$signData['signature'] = $imageName;

					$db->insert('lab_report_signatories', $signData);
				}
			}
		}

		$_SESSION['alertMsg'] = _translate("Facility details updated successfully");
		$general->activityLog('update-facility', $_SESSION['userName'] . ' updated facility ' . $_POST['facilityName'], 'facility');
	}
	header("Location:facilities.php");
} catch (Exception $e) {

	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	// $_SESSION['alertMsg'] = _translate("Something went wrong please try again.");
	header("Location:facilities.php");
}

<?php

use App\Services\ApiService;
use App\Utilities\JsonUtility;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use Laminas\Diactoros\UploadedFile;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;
use App\Utilities\ImageResizeUtility;
use App\Services\STS\TokensService;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocation */
$geolocation = ContainerRegistry::get(GeoLocationsService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);

/* For reference we define the table names */
$facilityTable = "facility_details";
$provinceTable = "geographical_divisions";
$userFacilityMapTable = "user_facility_map";
$testingLabsTable = "testing_labs";
$healthFacilityTable = "health_facilities";

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

// Get the uploaded files from the request object
$uploadedFiles = $request->getUploadedFiles();

// Sanitize and validate the uploaded files
$sanitizedLabLogo = $sanitizedSignature = $sanitizedReportTemplate = "";
if (isset($uploadedFiles['reportTemplate']))
	$sanitizedReportTemplate = _sanitizeFiles($uploadedFiles['reportTemplate'], ['pdf']);
if (isset($uploadedFiles['labLogo']))
	$sanitizedLabLogo = _sanitizeFiles($uploadedFiles['labLogo'], ['png', 'jpg', 'jpeg', 'gif']);
if (isset($uploadedFiles['signature']))
	$sanitizedSignature = _sanitizeFiles($uploadedFiles['signature'], ['png', 'jpg', 'jpeg', 'gif']);

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
				$geoData = array(
					'geo_name' => $_POST['provinceNew'],
					'updated_datetime' => DateUtility::getCurrentDateTime(),
				);
				$db->insert($provinceTable, $geoData);
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

		$data = [
			'facility_name' => $_POST['facilityName'],
			'facility_code' => !empty($_POST['facilityCode']) ? $_POST['facilityCode'] : null,
			'vlsm_instance_id' => $instanceId ?? null,
			'other_id' => !empty($_POST['otherId']) ? $_POST['otherId'] : null,
			'facility_mobile_numbers' => $_POST['phoneNo'] ?? null,
			'address' => $_POST['address'] ?? null,
			'country' => $_POST['country'] ?? null,
			'facility_state_id' => $_POST['stateId'] ?? null,
			'facility_district_id' => $_POST['districtId'] ?? null,
			'facility_state' => $_POST['state'] ?? null,
			'facility_district' => $_POST['district'] ?? null,
			'facility_hub_name' => $_POST['hubName'] ?? null,
			'latitude' => $_POST['latitude'] ?? null,
			'longitude' => $_POST['longitude'] ?? null,
			'facility_emails' => $_POST['email'] ?? null,
			'report_email' => $email,
			'contact_person' => $_POST['contactPerson'],
			'facility_type' => $_POST['facilityType'],
			'test_type' => (!empty($_POST['testType'])) ?  implode(', ', $_POST['testType'])  : null,
			'testing_points' => $_POST['testingPoints'],
			'header_text' => $_POST['headerText'],
			// 'report_format' => (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) ? json_encode($fileResponse) : null,
			'updated_datetime' => DateUtility::getCurrentDateTime(),
			'status' => 'active'
		];


		$db->insert('facility_details', $data);
		$lastId = $db->getInsertId();

		if ($data['facility_type'] == 2) {
			$stsTokensService->createToken($lastId);
			if (isset($_POST['testTypeFile']) && !empty($_POST['testTypeFile'])) {
				$fileResponse = [];
				$directories = [UPLOAD_PATH, 'labs', $lastId, 'report-template'];
				$currentPath = '';
				foreach ($directories as $directory) {
					$currentPath = $currentPath === '' ? $directory : $currentPath . DIRECTORY_SEPARATOR . $directory;
					MiscUtility::makeDirectory($currentPath, 0777); // will just skip if exists
				}
				foreach ($_POST['testTypeFile'] as $key => $test) {
					$sanitizedReportTemplate = _sanitizeFiles($uploadedFiles['reportTemplate'][$key], ['pdf']);
					if ($sanitizedReportTemplate instanceof UploadedFile && $sanitizedReportTemplate->getError() === UPLOAD_ERR_OK) {
						$directoryPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $test . DIRECTORY_SEPARATOR . $lastId;
						MiscUtility::makeDirectory($directoryPath, 0777, true);
						$string = MiscUtility::generateRandomString(12) . "-" . $test . ".";
						$extension = MiscUtility::getFileExtension($sanitizedReportTemplate->getClientFilename());
						$fileName = "report-template-" . $string . $extension;
						$filePath = $directoryPath . DIRECTORY_SEPARATOR . $fileName;
						$fileResponse[$test]['file'] = $fileName;
						$fileResponse[$test]['mtop'] = $_POST['headerMargin'][$key];

						// Move the uploaded file to the desired location
						$sanitizedReportTemplate->moveTo($filePath);
					}
				}
				$reportFormatJson = JsonUtility::jsonToSetString(json_encode($fileResponse), 'report_format');
				$db->where('facility_id', $lastId);
				$db->update('facility_details', ['report_format' => json_encode($fileResponse)]);
			}
		}

		$facilityAttributes = [];
		if (!empty($_POST['allowResultUpload'])) {
			$facilityAttributes['allow_results_file_upload'] = $_POST['allowResultUpload'];
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

		if (!empty($_POST['sampleType'])) {
			foreach ($_POST['sampleType'] as $testType => $sampleTypes) {
				$facilityAttributes['sampleType'][$testType] = implode(",", $sampleTypes);
			}
		}

		// Upload Report Template
		if ($sanitizedReportTemplate instanceof UploadedFile && $sanitizedReportTemplate->getError() === UPLOAD_ERR_OK) {
			$directoryPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $lastId;
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
			MiscUtility::makeDirectory(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId, 0777, true);
			$extension = MiscUtility::getFileExtension($sanitizedLabLogo->getClientFilename());
			$string = MiscUtility::generateRandomString(12) . ".";
			$actualImageName = "actual-logo-" . $string . $extension;
			$imageName = "logo-" . $string . $extension;
			$actualImagePath = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo") . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $actualImageName;

			// Move the uploaded file to the desired location
			$sanitizedLabLogo->moveTo($actualImagePath);

			// Resize the image
			$resizeObj = new ImageResizeUtility($actualImagePath);
			$resizeObj->resizeToWidth(100);
			$resizeObj->save(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $lastId . DIRECTORY_SEPARATOR . $imageName);

			$data['facility_logo'] = $imageName;
		}

		$db->where('facility_id', $lastId);
		$db->update('facility_details', $data);

		if ($lastId > 0 && !empty($_POST['testType'])) {
			foreach ($_POST['testType'] as $testType) {
				// Mapping facility as a Health Facility
				if (isset($_POST['facilityType']) && $_POST['facilityType'] == 1) {
					$db->insert($healthFacilityTable, [
						'test_type' => $testType,
						'facility_id' => $lastId,
						'updated_datetime' => DateUtility::getCurrentDateTime()
					]);
					// Mapping facility as a Testing Lab
				} elseif (isset($_POST['facilityType']) && $_POST['facilityType'] == 2) {
					$testTypeData = [
						'test_type' => $testType,
						'facility_id' => $lastId,
						'updated_datetime' => DateUtility::getCurrentDateTime()
					];
					if (!empty($_POST['availablePlatforms'])) {
						$attributes['platforms'] = $_POST['availablePlatforms'];
					}
					if (!empty($attributes)) {
						$testTypeData['attributes'] = json_encode($attributes, true);
					}
					$db->insert($testingLabsTable, $testTypeData);
				}
			}
		}
		// Mapping facility with users
		if ($lastId > 0 && trim((string) $_POST['selectedUser']) != '') {
			$selectedUser = explode(",", (string) $_POST['selectedUser']);
			for ($j = 0; $j < count($selectedUser); $j++) {
				$uData = [
					'user_id' => $selectedUser[$j],
					'facility_id' => $lastId,
				];
				$db->insert($userFacilityMapTable, $uData);
			}
		}
		if ($lastId > 0 && !empty($_POST['testData'])) {
			// Mapping facility as a Testing Lab
			for ($tf = 0; $tf < count($_POST['testData']); $tf++) {
				$dataTest = [
					'test_type' => $_POST['testData'][$tf],
					'facility_id' => $lastId,
					'monthly_target' => $_POST['monTar'][$tf],
					'suppressed_monthly_target' => $_POST['supMonTar'][$tf],
					"updated_datetime" => DateUtility::getCurrentDateTime()
				];
				$db->insert($testingLabsTable, $dataTest);
			}
		}

		// Uploading signatories
		if ($lastId > 0 && !empty($sanitizedSignature) && !empty($_POST['signName'])) {
			foreach ($_POST['signName'] as $key => $name) {
				if (isset($name) && $name != "" && isset($sanitizedSignature[$key]) && $sanitizedSignature[$key] instanceof UploadedFile && $sanitizedSignature[$key]->getError() === UPLOAD_ERR_OK) {
					$signData = [
						'name_of_signatory' => $name,
						'designation' => $_POST['designation'][$key],
						'test_types' => implode(",", (array)$_POST['testSignType'][($key + 1)]),
						'lab_id' => $lastId,
						'display_order' => $_POST['sortOrder'][$key],
						'signatory_status' => $_POST['signStatus'][$key],
						"added_by" => $_SESSION['userId'],
						"added_on" => DateUtility::getCurrentDateTime()
					];


					$pathname = MiscUtility::buildSafePath(UPLOAD_PATH, ['labs', $lastId, 'signatures']) . DIRECTORY_SEPARATOR;

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

		$general->activityLog('add-facility', $_SESSION['userName'] . ' added new facility ' . $_POST['facilityName'], 'facility');
	}
	if (isset($apiData['api-type']) && $apiData['api-type'] == "sync" && !isset($_POST['fromAPI'])) {
		echo $lastId;
		exit;
	}

	if (isset($_POST['reqForm']) && $_POST['reqForm'] != '') {
		$currentDateTime = DateUtility::getCurrentDateTime();
		$healthFacilityData = array(
			'test_type'     => "covid19",
			'facility_id'   => $lastId,
			'updated_datetime'  => $currentDateTime
		);
		$db->insert("health_facilities", $healthFacilityData);
		return 1;
	} else {
		$_SESSION['alertMsg'] = _translate("Facility details added successfully");
		header("Location:/facilities/facilities.php");
	}
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	// $_SESSION['alertMsg'] = _translate("Something went wrong please try again.");
	header("Location:facilities.php");
}

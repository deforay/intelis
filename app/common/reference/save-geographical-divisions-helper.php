<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


try {
	if (!empty($_POST['geoName'])) {
		$geoId = !empty($_POST['geoId']) ? base64_decode((string) $_POST['geoId']) : null;
		$existingDivision = null;
		if (!empty($geoId)) {
			$existingDivision = $db->rawQueryOne("SELECT * FROM geographical_divisions WHERE geo_id = ?", [$geoId]);
		}

		$geoParent = isset($_POST['geoParent']) ? (int) $_POST['geoParent'] : 0;
		$currentDateTime = DateUtility::getCurrentDateTime();

		$data = [
			'geo_name' => $_POST['geoName'],
			'geo_code' => $_POST['geoCode'] ?? null,
			'geo_parent' => $geoParent,
			'geo_status' => $_POST['geoStatus'] ?? 'active',
			'updated_datetime' => $currentDateTime
		];

		// Guard against duplicate name + parent before attempting save
		$db->where('geo_name', $data['geo_name']);
		$db->where('geo_parent', $geoParent);
		if (!empty($geoId)) {
			$db->where('geo_id', $geoId, '!=');
		}
		$existingDuplicate = $db->getOne('geographical_divisions', 'geo_id');
		if (!empty($existingDuplicate)) {
			$_SESSION['alertMsg'] = _translate("A geographical division with this name already exists under the selected parent. Please use a different name.");
			if (!empty($geoId)) {
				header("Location:edit-geographical-divisions.php?id=" . base64_encode((string) $geoId));
			} else {
				header("Location:add-geographical-divisions.php");
			}
			exit;
		}

		$operationSuccessful = false;

		if (!empty($geoId)) {
			$db->where("geo_id", $geoId);
			$updateResult = $db->update("geographical_divisions", $data);
			if ($updateResult !== false) {
				$operationSuccessful = true;
			} else {
				$lastError = (string) $db->getLastError();
				if (stripos($lastError, 'Duplicate entry') !== false) {
					$_SESSION['alertMsg'] = _translate("A geographical division with this name and parent already exists. Please use a different name.");
					header("Location:edit-geographical-divisions.php?id=" . base64_encode((string) $geoId));
					exit;
				}
			}
		} else {
			$data['created_by'] = $_SESSION['userId'];
			$data['created_on'] = $currentDateTime;
			$data['data_sync'] = 0;
			$db->insert("geographical_divisions", $data);
			$geoId = $db->getInsertId();
			if (!empty($geoId)) {
				$operationSuccessful = true;
			} else {
				$lastError = (string) $db->getLastError();
				if (stripos($lastError, 'Duplicate entry') !== false) {
					$_SESSION['alertMsg'] = _translate("A geographical division with this name and parent already exists. Please use a different name.");
					header("Location:add-geographical-divisions.php");
					exit;
				}
			}
		}

		if ($operationSuccessful) {
			$parentProvinceName = null;
			if ($geoParent > 0) {
				$parentProvince = $db->rawQueryOne("SELECT geo_name FROM geographical_divisions WHERE geo_id = ?", [$geoParent]);
				$parentProvinceName = $parentProvince['geo_name'] ?? null;
			}

			$facilityData = [];
			if ($geoParent === 0) {
				$facilityData['facility_state'] = $data['geo_name'];
				$facilityData['facility_state_id'] = $geoId;
				$db->where("facility_state_id", $geoId);
			} else {
				$facilityData['facility_state_id'] = $geoParent;
				$facilityData['facility_state'] = $parentProvinceName;
				$facilityData['facility_district'] = $data['geo_name'];
				$facilityData['facility_district_id'] = $geoId;
				$db->where("facility_district_id", $geoId);
			}

			if (!empty($facilityData)) {
				$db->update("facility_details", $facilityData);
			}

			if (!empty($existingDivision) && ($data['geo_status'] ?? 'active') === 'inactive' && !empty($geoId)) {
				if ((int) $existingDivision['geo_parent'] === 0) {
					$targetProvinceId = isset($_POST['moveToProvince']) ? (int) $_POST['moveToProvince'] : 0;
					if ($targetProvinceId > 0 && $targetProvinceId !== (int) $geoId) {
						$targetProvince = $db->rawQueryOne(
							"SELECT geo_id, geo_name FROM geographical_divisions WHERE geo_id = ? AND geo_status = 'active' AND geo_parent = 0",
							[$targetProvinceId]
						);
						if (!empty($targetProvince)) {
							$db->where('geo_parent', $geoId);
							$db->where('geo_status', 'active');
							$db->update('geographical_divisions', [
								'geo_parent' => $targetProvinceId,
								'updated_datetime' => $currentDateTime
							]);

							$db->where('facility_state_id', $geoId);
							$db->where('status', 'active');
							$db->update('facility_details', [
								'facility_state_id' => $targetProvinceId,
								'facility_state' => $targetProvince['geo_name'],
								'updated_datetime' => $currentDateTime
							]);
						}
					}
				} elseif ((int) $existingDivision['geo_parent'] > 0) {
					$targetDistrictId = isset($_POST['moveToDistrict']) ? (int) $_POST['moveToDistrict'] : 0;
					if ($targetDistrictId > 0 && $targetDistrictId !== (int) $geoId) {
						$targetDistrict = $db->rawQueryOne(
							"SELECT d.geo_id,
									d.geo_name,
									CAST(NULLIF(d.geo_parent, '') AS UNSIGNED) AS province_id,
									p.geo_name AS province_name
								FROM geographical_divisions d
								LEFT JOIN geographical_divisions p ON p.geo_id = CAST(NULLIF(d.geo_parent, '') AS UNSIGNED)
								WHERE d.geo_id = ?
								AND d.geo_status = 'active'",
							[$targetDistrictId]
						);
						if (!empty($targetDistrict) && !empty($targetDistrict['province_id'])) {
							$db->where('facility_district_id', $geoId);
							$db->where('status', 'active');
							$db->update('facility_details', [
								'facility_district_id' => $targetDistrictId,
								'facility_district' => $targetDistrict['geo_name'],
								'facility_state_id' => $targetDistrict['province_id'],
								'facility_state' => $targetDistrict['province_name'],
								'updated_datetime' => $currentDateTime
							]);
						}
					}
				}
			}

			$_SESSION['alertMsg'] = _translate("Geographical Divisions details saved successfully");
			$general->activityLog('Geographical Divisions details', $_SESSION['userName'] . ' saved geographical division - ' . $_POST['geoName'], 'common-reference');
		}
	}
	header("Location:geographical-divisions-details.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}

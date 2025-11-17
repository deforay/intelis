<?php

use Laminas\Diactoros\ServerRequest;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Services\GeoLocationsService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GeoLocationsService $geoLocationsService */
$geoLocationsService = ContainerRegistry::get(GeoLocationsService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

$option = '<option value=""> ' . _translate("-- Select --") . ' </option>';

$testType = $_POST['testType'] ?? 'vl';

$facilityIdRequested = empty($_POST['cName']) ? null : $_POST['cName'];
$provinceRequested = empty($_POST['pName']) ? null : $_POST['pName'];
$districtRequested = empty($_POST['dName']) ? null : $_POST['dName'];
$facilityTypeRequested = empty($_POST['fType']) ? 1 : $_POST['fType'];   // 1 = Health Facilities


if (!empty($facilityIdRequested)) {
	// Fetch District and Province for the selected facility
	$db->where("f.facility_id", $facilityIdRequested);
	$db->join("user_details u", "u.user_id=f.contact_person", "LEFT");

	$facilityInfo = $db->getOne('facility_details f', 'f.* , u.user_name as contact_person');

	$provinceOptions = $geoLocationsService->getProvinceDropdown(selectedProvince: $facilityInfo['facility_state_id'], option: $option);
	$districtOptions = $geoLocationsService->getDistrictDropdown(selectedProvince: $facilityInfo['facility_state_id'], selectedDistrict: $facilityInfo['facility_district_id'], option: $option);
	echo $provinceOptions . "###" . $districtOptions . "###" . $facilityInfo['contact_person'];
} elseif (!empty($provinceRequested) && !empty($districtRequested) && !empty($_POST['requestType']) && $_POST['requestType'] == 'patient') {
	// Fetch Districts for the selected Province
	$provinceName = explode("##", (string) $provinceRequested);
	$districtOptions = $geoLocationsService->getDistrictDropdown(selectedProvince: $provinceName[0], selectedDistrict: $districtRequested, option: $option);
	echo "###" . $districtOptions . "###";
} elseif (!empty($provinceRequested) && !empty($districtRequested) && is_numeric($provinceRequested) && is_numeric($districtRequested)) {
	$districtOptions = $geoLocationsService->getDistrictDropdown(selectedProvince: $provinceRequested, selectedDistrict: $districtRequested, option: $option);
	echo "###" . $districtOptions . "###";
} elseif (!empty($provinceRequested)) {
	// Fetch Districts for the selected Province
	$provinceName = explode("##", (string) $provinceRequested);
	$facilityOptions = $facilitiesService->getFacilitiesDropdown(testType: $testType, facilityType: $facilityTypeRequested, provinceName: $provinceName[0], districtRequested: null, option: $option, comingFromUser: $_POST['comingFromUser'] ?? null);
	$districtOptions = $geoLocationsService->getDistrictDropdown(selectedProvince: $provinceName[0], selectedDistrict: null, option: $option);

	echo $facilityOptions . "###" . $districtOptions . "###";
} elseif (!empty($districtRequested)) {
	// Fetch Facilities for the selected District
	$facilityOptions = $facilitiesService->getFacilitiesDropdown($testType, $facilityTypeRequested, null, $districtRequested, $option, $_POST['comingFromUser'] ?? null);
	$testingLabsList = $facilitiesService->getTestingLabs($GLOBALS['testType']);
	$testingLabsOptions = $general->generateSelectOptions($testingLabsList, null, '-- Select --');

	echo $facilityOptions . "###" . $testingLabsOptions . "###";
} elseif (!empty($facilityTypeRequested)) {
	$facilityOptions = $facilitiesService->getFacilitiesDropdown($testType, $facilityTypeRequested, null, $districtRequested, $option, $_POST['comingFromUser'] ?? null);
    $testingLabsList = $facilitiesService->getTestingLabs($GLOBALS['testType']);
    $testingLabsOptions = $general->generateSelectOptions($testingLabsList, null, '-- Select --');
	echo $facilityOptions . "###" . $testingLabsOptions . "###";
}

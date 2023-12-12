<?php

use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$formId = (int) $general->getGlobalConfig('vl_form');


if ($formId == '3') {
	$GLOBALS['option'] = '<option value=""><?= _translate("-- Select --"); ?> </option>';
} else {
	$GLOBALS['option'] = '<option value=""> ' . _translate("-- Select --") . ' </option>';
}

if (!empty($_POST['testType'])) {
	$GLOBALS['testType'] = $_POST['testType'];
} else {
	$GLOBALS['testType'] = 'vl';
}


$facilityTypeTableList = array(
	1 => "health_facilities",
	2 => "testing_labs",
);

$facilityIdRequested = !empty($_POST['cName']) ? $_POST['cName'] : null;
$provinceRequested = !empty($_POST['pName']) ? $_POST['pName'] : null;
$districtRequested = !empty($_POST['dName']) ? $_POST['dName'] : null;
$facilityTypeRequested = !empty($_POST['fType']) ? $_POST['fType'] : 1;   // 1 = Health Facilities

$GLOBALS['facilityTypeTable'] = $facilityTypeTableList[$facilityTypeRequested];

$GLOBALS['facilityMap'] = $_SESSION['facilityMap'];
// if (empty($_POST['comingFromUser']) || $_POST['comingFromUser'] != 'yes') {
// 	$GLOBALS['facilityMap'] = $facilitiesService->getUserFacilityMap($_SESSION['userId']);
// }

function getFacilitiesDropdown($provinceName = null, $districtRequested = null, $usersService = null): string
{
	/** @var DatabaseService $db */
	$db = $GLOBALS['db'];

	$option = $GLOBALS['option'];
	$testType = $GLOBALS['testType'];
	$facilityTypeTable = $GLOBALS['facilityTypeTable'];

	$db->where("f.status", 'active');
	$db->orderBy("f.facility_name", "ASC");

	if (!empty($provinceName)) {
		$db->where("f.facility_state", $provinceName);
	}

	if (!empty($districtRequested)) {
		$db->where("f.facility_district", $districtRequested);
	}
	//$db->where("f.facility_type", $facilityTypeRequested);
	$db->join("$facilityTypeTable h", "h.facility_id=f.facility_id", "INNER");
	$db->joinWhere("$facilityTypeTable h", "h.test_type", $testType);

	if (!empty($_SESSION['facilityMap'])) {
		$db->where("f.facility_id IN (" . $_SESSION['facilityMap'] . ")");
	}

	$facilityInfo = $db->get('facility_details f');
	$facility = '';
	if ($facilityInfo) {
		if (!isset($_POST['comingFromUser'])) {
			$facility .= $option;
		}
		foreach ($facilityInfo as $fDetails) {
			$fcode = (isset($fDetails['facility_code']) && $fDetails['facility_code'] != "") ? ' - ' . $fDetails['facility_code'] : '';

			$labContactUser = $usersService->getUserInfo($fDetails['contact_person']);
			if (!empty($labContactUser)) {
				$fDetails['contact_person'] = $labContactUser['user_name'];
			}

			$facility .= "<option data-code='" . $fDetails['facility_code'] . "' data-emails='" . $fDetails['facility_emails'] . "' data-mobile-nos='" . $fDetails['facility_mobile_numbers'] . "' data-contact-person='" . ($fDetails['contact_person']) . "' value='" . $fDetails['facility_id'] . "'>" . (addslashes((string) $fDetails['facility_name'])) . $fcode . "</option>";
		}
	} else {
		// if(isset($_POST['comingFromUser'])){
		//     $option = ' ';
		// }
		$facility .= $option;
	}
	return $facility;
}

function getDistrictDropdown($selectedProvince = null, $selectedDistrict = null)
{
	/** @var DatabaseService $db */
	$db = $GLOBALS['db'];
	$option = $GLOBALS['option'];

	if (!empty($selectedProvince)) {
		if (is_numeric($selectedProvince)) {
			$db->where("geo_parent", $selectedProvince);
			$db->orderBy("geo_name", "ASC");

			$districtInfo = $db->setQueryOption('DISTINCT')
				->get('geographical_divisions', null, 'geo_id, geo_name');
			$district = $option;
			foreach ($districtInfo as $pdRow) {
				$selected = '';
				if ($selectedDistrict == $pdRow['geo_id']) {
					$selected = "selected='selected'";
				}
				$district .= "<option $selected value='" . $pdRow['geo_id'] . "'>" . ($pdRow['geo_name']) . "</option>";
			}
			return $district;
		} else {

			$db->where("f.facility_state", $selectedProvince);
		}
	}

	if (!empty($_SESSION['facilityMap'])) {
		$db->where("f.facility_id IN (" . $_SESSION['facilityMap'] . ")");
	}
	$db->orderBy("f.facility_name", "ASC");
	$facilityInfo = $db->setQueryOption('DISTINCT')
		->get('facility_details f', null, 'facility_district');

	$district = $option;
	foreach ($facilityInfo as $pdRow) {
		$selected = '';
		if ($selectedDistrict == $pdRow['facility_district']) {
			$selected = "selected='selected'";
		}
		$district .= "<option $selected value='" . $pdRow['facility_district'] . "'>" . ($pdRow['facility_district']) . "</option>";
	}
	return $district;
}



if (!empty($facilityIdRequested)) {
	$db->where("f.facility_id", $facilityIdRequested);
	$facilityInfo = $db->getOne('facility_details f');

	$labContactUser = $usersService->getUserInfo($facilityInfo['contact_person']);
	if (!empty($labContactUser)) {
		$facilityInfo['contact_person'] = $labContactUser['user_name'];
	}

	$provinceOptions = getProvinceDropdown($facilityInfo['facility_state_id']);
	$districtOptions = getDistrictDropdown($facilityInfo['facility_state_id'], $facilityInfo['facility_district_id']);
	echo $provinceOptions . "###" . $districtOptions . "###" . $facilityInfo['contact_person'];
} elseif (!empty($provinceRequested) && !empty($districtRequested) && $_POST['requestType'] == 'patient') {
	$provinceName = explode("##", (string) $provinceRequested);
	$districtOptions = getDistrictDropdown($provinceName[0], $districtRequested);
	echo "###" . $districtOptions . "###";
} elseif (!empty($provinceRequested) && !empty($districtRequested) && is_numeric($provinceRequested) && is_numeric($districtRequested)) {
	$districtOptions = getDistrictDropdown($provinceRequested, $districtRequested);
	echo "###" . $districtOptions . "###";
} elseif (!empty($provinceRequested)) {
	$provinceName = explode("##", (string) $provinceRequested);

	$facilityOptions = getFacilitiesDropdown($provinceName[0], null, $usersService);
	$districtOptions = getDistrictDropdown($provinceName[0]);

	echo $facilityOptions . "###" . $districtOptions . "###";
} elseif (!empty($districtRequested)) {

	$facilityOptions = getFacilitiesDropdown(null, $districtRequested, $usersService);
	$testingLabsList = $facilitiesService->getTestingLabs($GLOBALS['testType']);
	$testingLabsOptions = $general->generateSelectOptions($testingLabsList, null, '-- Select --');

	echo $facilityOptions . "###" . $testingLabsOptions . "###";
} elseif (!empty($facilityTypeRequested)) {
	$facilityOptions = getFacilitiesDropdown(null, $districtRequested, $usersService);
	echo $facilityOptions . "###" . $testingLabsOptions . "###";
}

function getProvinceDropdown($selectedProvince = null)
{
	/** @var DatabaseService $db */
	$db = $GLOBALS['db'];
	$option = $GLOBALS['option'];

	if (!empty($_SESSION['facilityMap'])) {
		$db->join("facility_details f", "f.facility_state_id=p.geo_id", "INNER");
		//$db->joinWhere("facility_details f", "h.test_type", $testType);
		$db->where("f.facility_id IN (" . $_SESSION['facilityMap'] . ")");
	}

	$db->where("p.geo_parent = 0");
	$db->orderBy("p.geo_name", "ASC");
	$pdResult = $db->setQueryOption('DISTINCT')
		->get('geographical_divisions p', null, 'geo_id,geo_name,geo_code');
	//$pdResult = $db->get('geographical_divisions p');
	$state = $option;
	foreach ($pdResult as $pdRow) {
		$selected = '';
		if ($selectedProvince == $pdRow['geo_id']) {
			$selected = "selected='selected'";
		}
		$state .= "<option data-code='" . $pdRow['geo_code'] . "' data-province-id='" . $pdRow['geo_id'] . "' data-name='" . $pdRow['geo_name'] . "' value='" . $pdRow['geo_name'] . "##" . $pdRow['geo_code'] . "' $selected>" . ($pdRow['geo_name']) . "</option>";
	}
	return $state;
}

<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}
#require_once('../startup.php');


$general = new \Vlsm\Models\General($db);
$facilitiesDb = new \Vlsm\Models\Facilities($db);

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();



if ($arr['vl_form'] == '3') {
  $option = "<option value=''> -- Sélectionner -- </option>";
} else {
  $option = "<option value=''> -- Select -- </option>";
}

if (!empty($_POST['testType'])) {
  $testType = $_POST['testType'];
} else {
  $testType = 'vl';
}


$facilityTypeTableList = array(
  1 => "health_facilities",
  2 => "testing_labs",
);

$facilityIdRequested = !empty($_POST['cName']) ? $_POST['cName'] : null;
$provinceRequested = !empty($_POST['pName']) ? $_POST['pName'] : null;
$districtRequested = !empty($_POST['dName']) ? $_POST['dName'] : null;
$facilityTypeRequested = !empty($_POST['fType']) ? $_POST['fType'] : 1;   // 1 = Health Facilities

$facilityTypeTable = !empty($facilityTypeRequested) ? $facilityTypeTableList[$facilityTypeRequested] : $facilityTypeTableList[$facilityTypeRequested];

$facilityMap = null;
if (empty($_POST['comingFromUser']) || $_POST['comingFromUser'] != 'yes') {
  $facilityMap = $facilitiesDb->getFacilityMap($_SESSION['userId'], null);
}


if (!empty($facilityIdRequested)) {

  $db->where("f.facility_id", $facilityIdRequested);
  $facilityInfo = $db->getOne('facility_details f');

  $provinceOptions = getProvinceDropdown($facilityInfo['facility_state']);
  $districtOptions = getDistrictDropdown($facilityInfo['facility_state'], $facilityInfo['facility_district']);
  echo $provinceOptions . "###" . $districtOptions . "###" . $facilityInfo['contact_person'];
} else if (!empty($provinceRequested)) {

  $provinceName = explode("##", $provinceRequested);

  $facilityOptions = getFacilitiesDropdown($provinceName[0], null);
  $districtOptions = getDistrictDropdown($provinceName[0]);

  echo $facilityOptions . "###" . $districtOptions . "###" . '';
} else if (!empty($districtRequested)) {

  $facilityOptions = getFacilitiesDropdown(null, $districtRequested);
  $testingLabsList = $facilitiesDb->getTestingLabs($testType);
  $testingLabsOptions = $general->generateSelectOptions($testingLabsList, null, '-- Select --');

  echo $facilityOptions . "###" . $testingLabsOptions . "###";
}


function getProvinceDropdown($selectedProvince = null)
{
  global $db;
  global $option;
  global $facilityMap;

  if (!empty($facilityMap)) {
    $db->join("facility_details f", "f.facility_state=p.province_name", "INNER");
    //$db->joinWhere("facility_details f", "h.test_type", $testType);
    $db->where("f.facility_id IN (" . $facilityMap . ")");
  }

  $pdResult = $db->get('province_details p');
  $state = $option;
  foreach ($pdResult as $pdRow) {
    $selected = '';
    if (strtolower($selectedProvince) == strtolower($pdRow['province_name'])) {
      $selected = "selected='selected'";
    }
    $state .= "<option data-code='" . $pdRow['province_code'] . "' data-province-id='" . $pdRow['province_id'] . "' data-name='" . $pdRow['province_name'] . "' value='" . $pdRow['province_name'] . "##" . $pdRow['province_code'] . "' $selected>" . ($pdRow['province_name']) . "</option>";
  }
  return $state;
}


function getDistrictDropdown($selectedProvince = null, $selectedDistrict = null)
{
  global $db;
  global $option;
  global $facilityMap;

  if (!empty($selectedProvince)) {
    $db->where("f.facility_state", $selectedProvince);
  }

  if (!empty($facilityMap)) {
    $db->where("f.facility_id IN (" . $facilityMap . ")");
  }
  $facilityInfo = $db->setQueryOption('DISTINCT')->get('facility_details f', null, array('facility_district'));

  $district = $option;
  foreach ($facilityInfo as $pdRow) {
    $selected = '';
    if (strtolower($selectedDistrict) == strtolower($pdRow['facility_district'])) {
      $selected = "selected='selected'";
    }
    $district .= "<option $selected value='" . $pdRow['facility_district'] . "'>" . ($pdRow['facility_district']) . "</option>";
  }
  return $district;
}


function getFacilitiesDropdown($provinceName = null, $districtRequested = null)
{
  global $db;
  global $option;
  global $testType;
  global $facilityMap;
  global $facilityTypeTable;

  $db->where("f.status", 'active');


  if (!empty($provinceName)) {
    $db->where("f.facility_state", $provinceName);
  }


  if (!empty($districtRequested)) {
    $db->where("f.facility_district", $districtRequested);
  }


  //$db->where("f.facility_type", $facilityTypeRequested);
  $db->join("$facilityTypeTable h", "h.facility_id=f.facility_id", "INNER");
  $db->joinWhere("$facilityTypeTable h", "h.test_type", $testType);

  if (!empty($facilityMap)) {
    $db->where("f.facility_id IN (" . $facilityMap . ")");
  }

  $facilityInfo = $db->get('facility_details f');
  $facility = '';
  if ($facilityInfo) {
    if (!isset($_POST['comingFromUser'])) {
      $facility .= $option;
    }
    foreach ($facilityInfo as $fDetails) {
      $facility .= "<option data-code='" . $fDetails['facility_code'] . "' data-emails='" . $fDetails['facility_emails'] . "' data-mobile-nos='" . $fDetails['facility_mobile_numbers'] . "' data-contact-person='" . ($fDetails['contact_person']) . "' value='" . $fDetails['facility_id'] . "'>" . (addslashes($fDetails['facility_name'])) . ' - ' . $fDetails['facility_code'] . "</option>";
    }
  } else {
    // if(isset($_POST['comingFromUser'])){
    //     $option = ' ';
    // }
    $facility .= $option;
  }
  return $facility;
}

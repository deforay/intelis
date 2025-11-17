<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$province     = $_POST['provinceId'];
$district   = $_POST['districtId'];
$selectedFacility = $_POST['selectedFacility'];
$selectedFacilityArray = explode(',',(string) $selectedFacility);

$facilityQuery = "SELECT facility_id, facility_name FROM facility_details WHERE `status` = 'active' ";

if(isset($province) && $province!="")
{
    $where[] = " facility_state_id = $province ";
}
if(isset($district) && $district!="")
{
    $where[] = " facility_district_id = $district ";
}
if(isset($where) && count($where)>0) 
{
    $whereCondition = implode(" AND ",$where);
    $facilityQuery .= " AND $whereCondition";
}


$facilityResult = $db->rawQuery($facilityQuery. " order by facility_name");

if (empty($facilityResult)) {
    echo "";
    exit;
}


$response = '';
foreach ($facilityResult as $row) {
    //$selectedText = in_array($row['facility_id'], $selectedFacilityArray) ? "selected='selected'" : "";
    if (!in_array($row['facility_id'], $selectedFacilityArray)) {
        $response .= "<option value='" . $row['facility_id'] . "'>" . $row['facility_name'] . "</option>";
    }
}
echo $response;

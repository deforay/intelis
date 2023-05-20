<?php

use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;


/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);
$list = [];

if (isset($_POST['districtId'])) {
    $districtId = $_POST['districtId'];
    $result = $geolocationService->getByDistrictId($districtId, true, true);

    //Get Facilities by district
    if (isset($_POST['facilities'])) {
        $facilityList = $result['facilities'];
        $option = "<option value=''>--Select--</option>";
        if (isset($_POST['facilityCode'])) {
            foreach ($facilityList as $facility) {
                $option .= '<option value="' . $facility['facility_id'] . '">' . $facility['facility_name'] . ' - ' . $facility['facility_code'] . ' </option>';
            }
        } else {
            foreach ($facilityList as $facility) {
                $option .= '<option value="' . $facility['facility_id'] . '">' . $facility['facility_name'] . '</option>';
            }
        }
        $list['facilities'] = $option;
    }

    //Get Labs by district
    if (isset($_POST['labs'])) {
        $labList = $result['labs'];
        $option = "<option value=''>--Select--</option>";
        foreach ($labList as $lab) {
            $option .= '<option value="' . $lab['facility_id'] . '">' . $lab['facility_name'] . ' </option>';
        }
        $list['labs'] = $option;
    }
    echo json_encode($list);
}

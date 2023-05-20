<?php

use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;


/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);
$list = [];

if (isset($_POST['provinceId'])) {
    $provinceId = $_POST['provinceId'];
    $result = $geolocationService->getByProvinceId($provinceId, true, true, true);

    //Get Districts by province
    if (isset($_POST['districts'])) {
        $districtList = $result['districts'];
        $option = "<option value=''>--Select--</option>";
        foreach ($districtList as $district) {
            $option .= '<option value="' . $district['geo_id'] . '">' . $district['geo_name'] . ' </option>';
        }
        $list['districts'] = $option;
    }
    //Get Facilities by province
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
    //Get Labs by province
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

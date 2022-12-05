<?php
$geoLocationDb = new \Vlsm\Models\GeoLocations();
$list = array();

if(isset($_POST['provinceId']))
{
    $provinceId = $_POST['provinceId'];
    $result = $geoLocationDb->getByProvinceId($provinceId, true, true, true);

    //Get Districts by province
        $districtList = $result['districts'];
        $option = "<option value=''>--Select--</option>";
        foreach($districtList as $district)
        {
            $option .= '<option value="'.$district['geo_id'].'">'.$district['geo_name'].' </option>';
        }
        $list['districts'] = $option;

    //Get Facilities by province
    
        $facilityList = $result['facilities'];
        $option = "<option value=''>--Select--</option>";
        foreach($facilityList as $facility)
        {
            $option .= '<option value="'.$facility['facility_id'].'">'.$facility['facility_name'].' </option>';
        }
        $list['facilities'] = $option;

    //Get Labs by province

        $labList = $result['labs'];
        $option = "<option value=''>--Select--</option>";
        foreach($labList as $lab)
        {
            $option .= '<option value="'.$lab['facility_id'].'">'.$lab['facility_name'].' </option>';
        }
        $list['labs'] = $option;

        echo json_encode($list);
}
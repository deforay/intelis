<?php
$geoLocationDb = new \Vlsm\Models\GeoLocations();
$list = array();

if(isset($_POST['districtId']))
{
    $districtId = $_POST['districtId'];
    $result = $geoLocationDb->getByDistrictId($districtId, true, true);
//echo '<pre>'; print_r($result); 
    //Get Facilities by district
    
        $facilityList = $result['facilities'];
        $option = "<option value=''>--Select--</option>";
        foreach($facilityList as $facility)
        {
            $option .= '<option value="'.$facility['facility_id'].'">'.$facility['facility_name'].' </option>';
        }
        $list['facilities'] = $option;

    //Get Labs by district

        $labList = $result['labs'];
        $option = "<option value=''>--Select--</option>";
        foreach($labList as $lab)
        {
            $option .= '<option value="'.$lab['facility_id'].'">'.$lab['facility_name'].' </option>';
        }
        $list['labs'] = $option;

        echo json_encode($list);
}
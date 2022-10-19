<?php
if(isset($_POST['districtId']))
{
    $fQuery = "SELECT * FROM facility_details where facility_district_id = ".$_POST['districtId']." and status = 'active'";
    $facilityList = $db->rawQuery($fQuery);
    $option = "<option>--Select--</option>";
    foreach($facilityList as $facility)
    {
        $option .= '<option value="'.$facility['facility_id'].'">'.$facility['facility_name'].' </option>';
    }
    echo $option;
}
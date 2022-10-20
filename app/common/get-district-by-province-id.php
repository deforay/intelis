<?php
if(isset($_POST['provinceId']))
{
    $districtQuery = "SELECT * FROM geographical_divisions where geo_parent = ".$_POST['provinceId']." and geo_status = 'active'";
    $districtList = $db->rawQuery($districtQuery);
    $option = "<option value=''>--Select--</option>";
    foreach($districtList as $district)
    {
        $option .= '<option value="'.$district['geo_id'].'">'.$district['geo_name'].' </option>';
    }
    echo $option;
}
<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
  

$tableName="testing_lab_health_facilities_map";
try {
    if(isset($_POST['vlLab']) && trim($_POST['vlLab'])!="" && trim($_POST['facilityTo']!='')){
		$db=$db->where('vl_lab_id',$_POST['vlLab']);
        $delId = $db->delete($tableName);
		if($delId){
		$facilityTo = explode(",",$_POST['facilityTo']);
		for($j = 0; $j < count($facilityTo); $j++){
			$data=array(
				'vl_lab_id'=>$_POST['vlLab'],
				'facility_id'=>$facilityTo[$j],
			);
			$db->insert($tableName,$data);
		}
        $_SESSION['alertMsg']="Facility map details updated successfully";
		}
    }
    header("Location:facilityMap.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
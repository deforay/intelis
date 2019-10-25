<?php
session_start();
require_once('../startup.php');  include_once(APPLICATION_PATH.'/includes/MysqliDb.php');
include_once(APPLICATION_PATH.'/models/General.php');
$general=new General($db);
$tableName="vl_request_form";
$primaryKey="vl_sample_id";
//config  query
$configQuery="SELECT * from global_config";
$configResult=$db->query($configQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
  $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
//system config
$systemConfigQuery ="SELECT * from system_config";
$systemConfigResult=$db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
  $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('vl.sample_code','vl.remote_sample_code','f.facility_name','vl.patient_art_no','vl.patient_first_name',"DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')",'fd.facility_name','rsrr.rejection_reason_name');
        $orderColumns = array('vl.sample_code','vl.remote_sample_code','f.facility_name','vl.patient_art_no','vl.patient_first_name','vl.sample_collection_date','fd.facility_name','rsrr.rejection_reason_name');
        
        if($sarr['user_type']=='standalone') {
        $aColumns = array('vl.sample_code','f.facility_name','vl.patient_art_no','vl.patient_first_name',"DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')",'fd.facility_name','rsrr.rejection_reason_name');
        $orderColumns = array('vl.sample_code','f.facility_name','vl.patient_art_no','vl.patient_first_name','vl.sample_collection_date','fd.facility_name','rsrr.rejection_reason_name');
        }
        
        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $primaryKey;
        
        $sTable = $tableName;
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
            $sOffset = $_POST['iDisplayStart'];
            $sLimit = $_POST['iDisplayLength'];
        }
        
        /*
         * Ordering
        */
        
        $sOrder = "";
        if (isset($_POST['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($_POST['iSortingCols']); $i++) {
                if ($_POST['bSortable_' . intval($_POST['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($_POST['iSortCol_' . $i])] . "
				 	" . ( $_POST['sSortDir_' . $i] ) . ", ";
                }
            }
            $sOrder = substr_replace($sOrder, "", -2);
        }
        
        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
        */
        
        $sWhere = "";
        if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
			$sWhere = " AND ";
            $searchArray = explode(" ", $_POST['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);
                
                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }
        
        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($_POST['bSearchable_' . $i]) && $_POST['bSearchable_' . $i] == "true" && $_POST['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
                }
            }
        }
        
        /*
         * SQL queries
         * Get data to display
        */
	$aWhere = '';
	$sQuery="SELECT vl.*,f.*,s.*,fd.facility_name as labName,rsrr.rejection_reason_name FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id LEFT JOIN r_art_code_details as art ON vl.current_regimen=art.art_id JOIN r_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection where vl.is_sample_rejected='yes' AND vl.vlsm_country_id='".$arr['vl_form']."'";
	$start_date = '';
	$end_date = '';
	if(isset($_POST['rjtBatchCode']) && trim($_POST['rjtBatchCode'])!= ''){
	    $sWhere = $sWhere.' AND b.batch_code LIKE "%'.$_POST['rjtBatchCode'].'%"';
	}
	
	if(isset($_POST['rjtSampleTestDate']) && trim($_POST['rjtSampleTestDate'])!= ''){
        $s_c_date = explode("to", $_POST['rjtSampleTestDate']);
        //print_r($s_c_date);die;
        if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
            $start_date = $general->dateFormat(trim($s_c_date[0]));
        }
        if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
            $end_date = $general->dateFormat(trim($s_c_date[1]));
        }
	    if (trim($start_date) == trim($end_date)) {
					$sWhere = $sWhere.' AND DATE(vl.sample_tested_datetime) = "'.$start_date.'"';
	    }else{
	       $sWhere = $sWhere.' AND DATE(vl.sample_tested_datetime) >= "'.$start_date.'" AND DATE(vl.sample_tested_datetime) <= "'.$end_date.'"';
	    }
  }
	if(isset($_POST['rjtSampleType']) && $_POST['rjtSampleType']!=''){
		$sWhere = $sWhere.' AND s.sample_id = "'.$_POST['rjtSampleType'].'"';
	}
	if(isset($_POST['rjtFacilityName']) && $_POST['rjtFacilityName']!=''){
		$sWhere = $sWhere.' AND f.facility_id IN ('.$_POST['rjtFacilityName'].')';
	}
	if(isset($_POST['rjtGender']) && $_POST['rjtGender']!=''){
		$sWhere = $sWhere.' AND vl.patient_gender = "'.$_POST['rjtGender'].'"';
	}
	if(isset($_POST['rjtPatientPregnant']) && $_POST['rjtPatientPregnant']!=''){
		$sWhere = $sWhere.' AND vl.is_patient_pregnant = "'.$_POST['rjtPatientPregnant'].'"';
	}
	if(isset($_POST['rjtPatientBreastfeeding']) && $_POST['rjtPatientBreastfeeding']!=''){
		$sWhere = $sWhere.' AND vl.is_patient_breastfeeding = "'.$_POST['rjtPatientBreastfeeding'].'"';
	}
	
	$dWhere = '';
    if($sarr['user_type']=='remoteuser'){
        //$sWhere = $sWhere." AND request_created_by='".$_SESSION['userId']."'";
        //$dWhere = $dWhere." AND request_created_by='".$_SESSION['userId']."'";
        $userfacilityMapQuery = "SELECT GROUP_CONCAT(DISTINCT facility_id ORDER BY facility_id SEPARATOR ',') as facility_id FROM vl_user_facility_map where user_id='".$_SESSION['userId']."'";
        $userfacilityMapresult = $db->rawQuery($userfacilityMapQuery);
        if($userfacilityMapresult[0]['facility_id']!=null && $userfacilityMapresult[0]['facility_id']!=''){
            $sWhere = $sWhere." AND vl.facility_id IN (".$userfacilityMapresult[0]['facility_id'].")   AND remote_sample='yes'";
            $dWhere = $dWhere." AND vl.facility_id IN (".$userfacilityMapresult[0]['facility_id'].")   AND remote_sample='yes'";
        }
    }
	$sQuery = $sQuery.' '.$sWhere;
        $sQuery = $sQuery.' group by vl.vl_sample_id';
        if (isset($sOrder) && $sOrder != "") {
            $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
            $sQuery = $sQuery.' order by '.$sOrder;
        }
        $_SESSION['rejectedViralLoadResult'] = $sQuery;
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery.' LIMIT '.$sOffset.','. $sLimit;
        }
        
        //echo $sQuery;die;
        $rResult = $db->rawQuery($sQuery);
       // print_r($rResult);
        /* Data set length after filtering */
        
        $aResultFilterTotal =$db->rawQuery("SELECT vl.*,f.*,s.*,fd.facility_name as labName FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id LEFT JOIN r_art_code_details as art ON vl.current_regimen=art.art_id JOIN r_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection where vl.is_sample_rejected='yes' AND vlsm_country_id='".$arr['vl_form']."' $sWhere group by vl.vl_sample_id order by $sOrder");
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $aResultTotal =  $db->rawQuery("select COUNT(vl_sample_id) as total FROM vl_request_form as vl JOIN r_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection where is_sample_rejected='yes' AND vlsm_country_id='".$arr['vl_form']."' $dWhere");
        $iTotal = $aResultTotal[0]['total'];
        /*
         * Output
        */
        $output = array(
            "sEcho" => intval($_POST['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            if(isset($aRow['sample_collection_date']) && trim($aRow['sample_collection_date'])!= '' && $aRow['sample_collection_date']!= '0000-00-00 00:00:00'){
                $xplodDate = explode(" ",$aRow['sample_collection_date']);
                $aRow['sample_collection_date'] = $general->humanDateFormat($xplodDate[0]);
            }else{
                $aRow['sample_collection_date'] = '';
            }
            if($aRow['remote_sample']=='yes'){
                $decrypt = 'remote_sample_code';
                
            }else{
                $decrypt = 'sample_code';
            }
            $patientFname = $general->crypto('decrypt',$aRow['patient_first_name'],$aRow[$decrypt]);
                $patientMname = $general->crypto('decrypt',$aRow['patient_middle_name'],$aRow[$decrypt]);
                $patientLname = $general->crypto('decrypt',$aRow['patient_last_name'],$aRow[$decrypt]);
            $row = array();
            $row[] = $aRow['sample_code'];
            if($sarr['user_type']!='standalone'){
                    $row[] = $aRow['remote_sample_code'];
            }
            $row[] = ($aRow['facility_name']);
            $row[] = $aRow['patient_art_no'];
            $row[] = ($patientFname." ".$patientMname." ".$patientLname);
            $row[] = $aRow['sample_collection_date'];
            $row[] = $aRow['labName'];
            $row[] = $aRow['rejection_reason_name'];
            $output['aaData'][] = $row;
        }
        echo json_encode($output);
?>
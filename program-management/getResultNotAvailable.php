<?php
session_start();
include('../includes/MysqliDb.php');
include('../General.php');
$general=new Deforay_Commons_General();
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
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        
        $aColumns = array('f.facility_name','vl.patient_art_no',"DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')",'fd.facility_name');
        $orderColumns = array('f.facility_name','vl.patient_art_no','vl.sample_collection_date','fd.facility_name');
        
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
	$sQuery="SELECT vl.*,f.*,s.*,fd.facility_name as labName FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id LEFT JOIN r_sample_type as s ON s.sample_id=vl.sample_type LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id LEFT JOIN r_art_code_details as art ON vl.current_regimen=art.art_id where vl.result_status=7 AND (vl.result IS NULL OR vl.result='')";
		
	if(isset($_POST['noResultBatchCode']) && trim($_POST['noResultBatchCode'])!= ''){
	    $sWhere = $sWhere.' AND b.batch_code LIKE "%'.$_POST['noResultBatchCode'].'%"';
	}
	
	if(isset($_POST['noResultSampleTestDate']) && trim($_POST['noResultSampleTestDate'])!= ''){
	    if (trim($start_date) == trim($end_date)) {
					$sWhere = $sWhere.' AND DATE(vl.sample_tested_datetime) = "'.$start_date.'"';
	    }else{
	       $sWhere = $sWhere.' AND DATE(vl.sample_tested_datetime) >= "'.$start_date.'" AND DATE(vl.sample_tested_datetime) <= "'.$end_date.'"';
	    }
  }
	if(isset($_POST['noResultSampleType']) && $_POST['noResultSampleType']!=''){
		$sWhere = $sWhere.' AND s.sample_id = "'.$_POST['noResultSampleType'].'"';
	}
	if(isset($_POST['noResultFacilityName']) && $_POST['noResultFacilityName']!=''){
		$sWhere = $sWhere.' AND f.facility_id IN ('.$_POST['noResultFacilityName'].')';
	}
	if(isset($_POST['noResultGender']) && $_POST['noResultGender']!=''){
		$sWhere = $sWhere.' AND vl.patient_gender = "'.$_POST['noResultGender'].'"';
	}
	if(isset($_POST['noResultPatientPregnant']) && $_POST['noResultPatientPregnant']!=''){
		$sWhere = $sWhere.' AND vl.is_patient_pregnant = "'.$_POST['noResultPatientPregnant'].'"';
	}
	if(isset($_POST['noResultPatientBreastfeeding']) && $_POST['noResultPatientBreastfeeding']!=''){
		$sWhere = $sWhere.' AND vl.is_patient_breastfeeding = "'.$_POST['noResultPatientBreastfeeding'].'"';
	}
	
	if($sWhere!=''){
	    $sWhere = $sWhere.' AND vl.vlsm_country_id="'.$arr['vl_form'].'"';
	}else{
	    $sWhere = $sWhere.' AND vl.vlsm_country_id="'.$arr['vl_form'].'"';
	}
	$sQuery = $sQuery.' '.$sWhere;
        $sQuery = $sQuery.' group by vl.vl_sample_id';
        if (isset($sOrder) && $sOrder != "") {
            $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
            $sQuery = $sQuery.' order by '.$sOrder;
        }
				$_SESSION['resultNotAvailable'] = $sQuery;
        
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery.' LIMIT '.$sOffset.','. $sLimit;
        }
        
        //echo $sQuery;die;
        $rResult = $db->rawQuery($sQuery);
       // print_r($rResult);
        /* Data set length after filtering */
        
        $aResultFilterTotal =$db->rawQuery("SELECT vl.*,f.*,s.*,fd.facility_name as labName FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id LEFT JOIN r_sample_type as s ON s.sample_id=vl.sample_type LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id LEFT JOIN r_art_code_details as art ON vl.current_regimen=art.art_id where vl.result_status=7 AND (vl.result IS NULL OR vl.result='') $sWhere group by vl.vl_sample_id order by $sOrder");
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $aResultTotal =  $db->rawQuery("select COUNT(vl_sample_id) as total FROM vl_request_form as vl where result_status=7 AND vlsm_country_id='".$arr['vl_form']."'");
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
            $row = array();
						$row[] = ucwords($aRow['facility_name']);
						$row[] = $aRow['patient_art_no'];
						$row[] = $aRow['sample_collection_date'];
            $row[] = ucwords($aRow['labName']);
						$output['aaData'][] = $row;
        }
        echo json_encode($output);
?>
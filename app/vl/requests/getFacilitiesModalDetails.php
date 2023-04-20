<?php
  

$tableName="facility_details";
$primaryKey="facility_id";

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        
        $aColumns = array('facility_id','facility_code','facility_name','facility_type_name');
        
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
                    $sOrder .= $aColumns[intval($_POST['iSortCol_' . $i])] . "
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
        
       $sQuery="SELECT * FROM facility_details as f_d LEFT JOIN facility_type as f_t ON f_t.facility_type_id=f_d.facility_type";
        
        if (isset($sWhere) && $sWhere != "") {
            $sWhere=' where '.$sWhere;
            $sWhere= $sWhere.' AND status = "active"';
	    if(isset($_POST['hub']) && trim($_POST['hub'])!= ''){
		$sWhere = $sWhere." AND f_d.facility_hub_name LIKE '%" . $_POST['hub'] . "%' ";
	    }if(isset($_POST['district']) && trim($_POST['district'])!= ''){
		$sWhere = $sWhere." AND f_d.facility_district LIKE '%" . $_POST['district'] . "%' ";
	    }if(isset($_POST['state']) && trim($_POST['state'])!= ''){
		$sWhere = $sWhere." AND f_d.facility_state LIKE '%" . $_POST['state'] . "%' ";
	    }if(isset($_POST['facilityName']) && trim($_POST['facilityName'])!= ''){
		$sWhere = $sWhere." AND f_t.facility_type_id='".$_POST['facilityName']."'";
	    }
            $sQuery = $sQuery.' '.$sWhere;
        }else{
            $sWhere=' where status = "active"';
	    if(isset($_POST['hub']) && trim($_POST['hub'])!= ''){
		$sWhere = $sWhere." AND f_d.facility_hub_name LIKE '%" . $_POST['hub'] . "%' ";
	    }if(isset($_POST['district']) && trim($_POST['district'])!= ''){
		$sWhere = $sWhere." AND f_d.facility_district LIKE '%" . $_POST['district'] . "%' ";
	    }if(isset($_POST['state']) && trim($_POST['state'])!= ''){
		$sWhere = $sWhere." AND f_d.facility_state LIKE '%" . $_POST['state'] . "%' ";
	    }if(isset($_POST['facilityName']) && trim($_POST['facilityName'])!= ''){
		$sWhere = $sWhere." AND f_t.facility_type_id='".$_POST['facilityName']."'";
	    }
            $sQuery = $sQuery.' '.$sWhere;
        }
        
        if (isset($sOrder) && $sOrder != "") {
            $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
            $sQuery = $sQuery.' order by '.$sOrder;
        }
        
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery.' LIMIT '.$sOffset.','. $sLimit;
        }
       //die($sQuery);
      // echo $sQuery;
        $rResult = $db->rawQuery($sQuery);
       // print_r($rResult);
        /* Data set length after filtering */
        
        $aResultFilterTotal =$db->rawQuery("SELECT * FROM facility_details as f_d LEFT JOIN facility_type as f_t ON f_t.facility_type_id=f_d.facility_type $sWhere order by $sOrder");
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
	if($_POST['type']=='all'){
        $aResultTotal =  $db->rawQuery("select COUNT(facility_id) as total FROM facility_details WHERE status = 'active'");
	}else{
	    $aResultTotal =  $db->rawQuery("select COUNT(facility_id) as total FROM facility_details WHERE status = 'active' AND facility_type=2");
	}
       // $aResultTotal = $countResult->fetch_row();
       //print_r($aResultTotal);
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
	    $facilityDetails = $aRow['facility_id']."##".$aRow['facility_name']."##".$aRow['facility_state']."##".$aRow['facility_hub_name']."##".$aRow['contact_person']."##".$aRow['facility_mobile_numbers']."##".$aRow['facility_district']."##".$aRow['facility_emails'];
            $row = [];
	    if($_POST['type']=='all'){
	       $row[] = '<input type="radio" id="facility'.$aRow['facility_id'].'" name="facility" value="'.$facilityDetails.'" onclick="getFacility(this.value);">';
	    }else{
		$row[] = '<input type="radio" id="facility'.$aRow['facility_id'].'" name="facility" value="'.$facilityDetails.'" onclick="getFacilityLab(this.value);">';
	    }
	    $row[] = $aRow['facility_code'];
	    $row[] = ($aRow['facility_name']);
            $row[] = ($aRow['facility_type_name']);
            $output['aaData'][] = $row;
        }
        echo json_encode($output);


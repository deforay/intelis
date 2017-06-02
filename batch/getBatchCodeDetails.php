<?php
session_start();
include('../includes/MysqliDb.php');
include('../General.php');
$tableName="batch_details";
$primaryKey="batch_id";
$configQuery="SELECT value FROM global_config WHERE name ='vl_form'";
$configResult=$db->query($configQuery);
$general=new Deforay_Commons_General();
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        
        $aColumns = array('b.batch_code',"DATE_FORMAT(b.request_created_datetime,'%d-%b-%Y %H:%i:%s')");
        $orderColumns = array('b.batch_code','','','','','','b.request_created_datetime');
		
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
        
	$sQuery="select b.request_created_datetime ,b.batch_code, b.batch_id,count(vl.sample_code) as sample_code from vl_request_form vl right join batch_details b on vl.sample_batch_id = b.batch_id";
        if (isset($sWhere) && $sWhere != "") {
            $sWhere=' where '.$sWhere;
            $sWhere= $sWhere. 'AND vl.vlsm_country_id ="'.$configResult[0]['value'].'"';
        }else{
	   $sWhere=' where vl.vlsm_country_id ="'.$configResult[0]['value'].'"';
	}
	$sQuery = $sQuery.' '.$sWhere;
        $sQuery = $sQuery.' group by b.batch_id';
        if (isset($sOrder) && $sOrder != "") {
            $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
            $sQuery = $sQuery.' order by '.$sOrder;
        }
        
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery.' LIMIT '.$sOffset.','. $sLimit;
        }
       //die($sQuery);
       //echo $sQuery;die;
        $rResult = $db->rawQuery($sQuery);
       // print_r($rResult);
        /* Data set length after filtering */
        
        $aResultFilterTotal =$db->rawQuery("select b.request_created_datetime, b.batch_code, b.batch_id,count(vl.sample_code) as sample_code from vl_request_form vl right join batch_details b on vl.sample_batch_id = b.batch_id  $sWhere group by b.batch_id order by $sOrder");
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $aResultTotal =  $db->rawQuery("select b.request_created_datetime, b.batch_code, b.batch_id,count(vl.sample_code) as sample_code from vl_request_form vl right join batch_details b on vl.sample_batch_id = b.batch_id where vl.vlsm_country_id ='".$configResult[0]['value']."' group by b.batch_id");
       // $aResultTotal = $countResult->fetch_row();
       //print_r($aResultTotal);
        $iTotal = count($aResultTotal);
        /*
         * Output
        */
        $output = array(
            "sEcho" => intval($_POST['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
	$batch = false;
	if(isset($_SESSION['privileges']) && (in_array("editBatch.php", $_SESSION['privileges']))){
	    $batch = true;
	}
	
        foreach ($rResult as $aRow) {
	    $humanDate="";
	    if(trim($aRow['request_created_datetime'])!="" && $aRow['request_created_datetime']!='0000-00-00 00:00:00'){
		$date = $aRow['request_created_datetime'];
		$humanDate =  date("d-M-Y H:i:s",strtotime($date));
	    }
	    //get no. of sample tested.
	    $noOfSampleTested = "select count(vl.sample_code) as no_of_sample_tested from vl_request_form as  vl where vl.sample_batch_id='".$aRow['batch_id']."' and vl.result_status=7";
	    $noOfSampleResultCount = $db->rawQuery($noOfSampleTested);
	    //error_log($noOfSampleTested);
	    //get no. of sample tested low level.
	    $noOfSampleLowTested = "select count(vl.sample_code) as no_of_sample_low_tested from vl_request_form as  vl where vl.sample_batch_id='".$aRow['batch_id']."' AND vl.result < 1000";
	    $noOfSampleLowResultCount = $db->rawQuery($noOfSampleLowTested);
	    //get no. of sample tested high level.
	    $noOfSampleHighTested = "select count(vl.sample_code) as no_of_sample_high_tested from vl_request_form as  vl where vl.sample_batch_id='".$aRow['batch_id']."' AND vl.result > 1000";
	    $noOfSampleHighResultCount = $db->rawQuery($noOfSampleHighTested);
	    //get no. of sample tested high level.
	    $noOfSampleLastDateTested = "select max(vl.sample_testing_date) as last_tested_date from vl_request_form as  vl where vl.sample_batch_id='".$aRow['batch_id']."'";
	    $noOfSampleLastDateTested = $db->rawQuery($noOfSampleLastDateTested);
	    
           $row = array();
	    $printBarcode='<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="Print bar code" onclick="generateBarcode(\''.base64_encode($aRow['batch_id']).'\');"><i class="fa fa-barcode"> Print Barcode</i></a>';
	    $printQrcode='<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="Print qr code" onclick="generateQRcode(\''.base64_encode($aRow['batch_id']).'\');"><i class="fa fa-qrcode"> Print QR code</i></a>';
	    $editPosition ='<a href="editBatchControlsPosition.php?id=' . base64_encode($aRow['batch_id']) . '" class="btn btn-default btn-xs" style="margin-right: 2px;margin-top:6px;" title="Edit Position"><i class="fa fa-sort-numeric-desc"> Edit Position</i></a>';
	    $date = '';
	    if($noOfSampleLastDateTested[0]['last_tested_date']!='0000-00-00 00:00:00' && $noOfSampleLastDateTested[0]['last_tested_date']!=null){
		$exp = explode(" ",$noOfSampleLastDateTested[0]['last_tested_date']);
		$date = $general->humanDateFormat($exp[0]);
	    }
	    $row[] = ucwords($aRow['batch_code']);
	    $row[] = $aRow['sample_code'];
	    $row[] = $noOfSampleResultCount[0]['no_of_sample_tested'];
	    $row[] = $noOfSampleLowResultCount[0]['no_of_sample_low_tested'];
	    $row[] = $noOfSampleHighResultCount[0]['no_of_sample_high_tested'];
	    $row[] = $date;
	    $row[] = $humanDate;
	//    $row[] = '<select class="form-control" name="status" id=' . $aRow['batch_id'] . ' title="Please select status" onchange="updateStatus(this.id,this.value)">
	//		    <option value="pending" ' . ($aRow['batch_status'] == "pending" ? "selected=selected" : "") . '>Pending</option>
	//		    <option value="completed" ' . ($aRow['batch_status'] == "completed" ? "selected=selected" : "") . '>Completed</option>
	//	    </select>';
	    if(isset($_POST['fromSource']) && $_POST['fromSource'] == 'qr'){
		$row[] = $printQrcode;
	    }else{
		if($batch){
		    $row[] = '<a href="editBatch.php?id=' . base64_encode($aRow['batch_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="Edit"><i class="fa fa-pencil"> Edit</i></a>&nbsp;'.$printBarcode.'&nbsp;'.$editPosition;
		}
	    }
            $output['aaData'][] = $row;
        }
        echo json_encode($output);
?>
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../../startup.php');


$formConfigQuery = "SELECT * from global_config where name='vl_form'";
$configResult = $db->query($formConfigQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
    $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
$general = new \Vlsm\Models\General();

$tbResults = $general->getTbResults();

$tableName = "form_tb";
$primaryKey = "tb_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'CONCAT(vl.patient_name, vl.patient_surname)', 'f.facility_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCode = 'remote_sample_code';
} else if ($sarr['sc_user_type'] == 'standalone') {
    $aColumns = array('vl.sample_code', 'b.batch_code', 'vl.patient_id', 'CONCAT(vl.patient_name, vl.patient_surname)', 'f.facility_name',  'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
    $orderColumns = array('vl.sample_code', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
}
if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'print') {
    array_unshift($orderColumns, "vl.tb_id");
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
				 	" . ($_POST['sSortDir_' . $i]) . ", ";
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
                $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
            } else {
                $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
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
$sQuery =      "SELECT tb_id,
							vl.sample_code,
							vl.remote_sample,
							vl.remote_sample_code,
							vl.sample_collection_date,
							vl.sample_tested_datetime,
							vl.patient_id,
							vl.patient_name,
							vl.patient_surname,
							f.facility_name, 
							vl.result,
							vl.sample_received_at_hub_datetime, 
							vl.last_modified_datetime,
							vl.result_approved_datetime,
							vl.result_reviewed_datetime,
							vl.result_dispatched_datetime,
							vl.result_printed_datetime,
							b.batch_code, 
							ts.status_name,
							imp.i_partner_name,
							u_d.user_name as reviewedBy,
							a_u_d.user_name as approvedBy,
							rs.rejection_reason_name,
							testres.test_reason_name as reasonForTesting
							
							FROM form_tb as vl  
							LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                            LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
                            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
							LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by  
							LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
							LEFT JOIN r_tb_test_reasons as testres ON testres.test_reason_id=vl.reason_for_tb_test 
							LEFT JOIN r_tb_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
							LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id=vl.implementing_partner";
$start_date = '';
$end_date = '';
$t_start_date = '';
$t_end_date = '';
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $s_c_date = explode("to", $_POST['sampleCollectionDate']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = $general->dateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = $general->dateFormat(trim($s_c_date[1]));
    }
}

if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
    $s_t_date = explode("to", $_POST['sampleTestDate']);
    //print_r($s_t_date);die;
    if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
        $t_start_date = $general->dateFormat(trim($s_t_date[0]));
    }
    if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
        $t_end_date = $general->dateFormat(trim($s_t_date[1]));
    }
}
if (isset($sWhere) && $sWhere != "") {
    $sWhere = ' where ' . $sWhere;
    if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
        $sWhere = $sWhere . ' AND b.batch_code = "' . $_POST['batchCode'] . '"';
    }
    if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
        if (trim($start_date) == trim($end_date)) {
            $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) = "' . $start_date . '"';
        } else {
            $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
        if (trim($t_start_date) == trim($t_end_date)) {
            $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) = "' . $t_start_date . '"';
        } else {
            $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) >= "' . $t_start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $t_end_date . '"';
        }
    }
    if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
        $sWhere = $sWhere . ' AND f.facility_id IN (' . $_POST['facilityName'] . ')';
    }
} else {
    if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
        $setWhr = 'where';
        $sWhere = ' where ' . $sWhere;
        $sWhere = $sWhere . ' b.batch_code = "' . $_POST['batchCode'] . '"';
    }

    if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
        if (isset($setWhr)) {
            if (trim($start_date) == trim($end_date)) {
                $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) = "' . $start_date . '"';
            } else {
                $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
            }
        } else {
            $setWhr = 'where';
            $sWhere = ' where ' . $sWhere;
            $sWhere = $sWhere . ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }

    if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
        if (isset($setWhr)) {
            if (trim($t_start_date) == trim($t_end_date)) {
                $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) = "' . $t_start_date . '"';
            } else {
                //$sWhere=' where '.$sWhere;
                $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) >= "' . $t_start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $t_end_date . '"';
            }
        } else {
            $setWhr = 'where';
            $sWhere = ' where ' . $sWhere;
            $sWhere = $sWhere . ' DATE(vl.sample_tested_datetime) >= "' . $t_start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $t_end_date . '"';
        }
    }
    if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
        if (isset($setWhr)) {
            $sWhere = $sWhere . ' AND f.facility_id IN (' . $_POST['facilityName'] . ')';
        } else {
            $setWhr = 'where';
            $sWhere = ' where ' . $sWhere;
            $sWhere = $sWhere . ' f.facility_id IN (' . $_POST['facilityName'] . ')';
        }
    }
}
$dWhere = '';
// Only approved results can be printed
if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'print') {
    if (!isset($_POST['status']) || trim($_POST['status']) == '') {
        if (trim($sWhere) != '') {
            $sWhere = $sWhere . " AND ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND result_printed_datetime is NOT NULL AND result_printed_datetime not like ''";
        } else {
            $sWhere = "WHERE ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND result_printed_datetime is NOT NULL AND result_printed_datetime not like ''";
        }
    }
    $sWhere = $sWhere . " AND vl.vlsm_country_id='" . $arr['vl_form'] . "'";
    $dWhere = "WHERE ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND vl.vlsm_country_id='" . $arr['vl_form'] . "' AND result_printed_datetime is NOT NULL AND result_printed_datetime not like ''";
}
if ($_SESSION['instanceType'] == 'remoteuser') {
    //$sWhere = $sWhere." AND request_created_by='".$_SESSION['userId']."'";
    //$dWhere = $dWhere." AND request_created_by='".$_SESSION['userId']."'";
    $userfacilityMapQuery = "SELECT GROUP_CONCAT(DISTINCT facility_id ORDER BY facility_id SEPARATOR ',') as facility_id FROM vl_user_facility_map where user_id='" . $_SESSION['userId'] . "'";
    $userfacilityMapresult = $db->rawQuery($userfacilityMapQuery);
    if ($userfacilityMapresult[0]['facility_id'] != null && $userfacilityMapresult[0]['facility_id'] != '') {
        $sWhere = $sWhere . " AND vl.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ")";
        $dWhere = $dWhere . " AND vl.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ")";
    }
}

$sQuery = $sQuery . ' ' . $sWhere;
$_SESSION['tbPrintedResultsQuery'] = $sQuery;
//echo $_SESSION['vlResultQuery'];die;

if (isset($sOrder) && $sOrder != "") {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' order by ' . $sOrder;
}
$_SESSION['tbPrintedSearchResultQuery'] = $sQuery;
if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
//error_log($sQuery);
// die($sQuery);
$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */

$aResultFilterTotal = $db->rawQuery("SELECT tb_id FROM form_tb as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status 

LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id=vl.implementing_partner 
$sWhere");
$iFilteredTotal = count($aResultFilterTotal);
/* Total data set length */
$aResultTotal =  $db->rawQuery("select COUNT(tb_id) as total FROM form_tb as vl $dWhere");
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
    $row = array();
    if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'print') {
        $row[] = '<input type="checkbox" name="chkPrinted[]" class="checkPrintedRows" id="chkPrinted' . $aRow['tb_id'] . '"  value="' . $aRow['tb_id'] . '" onclick="checkedPrintedRow(this);"  />';
        $print = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="View" onclick="resultPDF(' . $aRow['tb_id'] . ',\'printData\');"><i class="fa fa-print"> Print</i></a>';
    }
    if ($aRow['remote_sample'] == 'yes') {
        $decrypt = 'remote_sample_code';
    } else {
        $decrypt = 'sample_code';
    }

    $patientFname = $general->crypto('decrypt', $aRow['patient_name'], $aRow['patient_id']);
    $patientLname = $general->crypto('decrypt', $aRow['patient_surname'], $aRow['patient_id']);

    $row[] = $aRow['sample_code'];
    if ($sarr['sc_user_type'] != 'standalone') {
        $row[] = $aRow['remote_sample_code'];
    }
    $row[] = $aRow['batch_code'];
    $row[] = $aRow['patient_id'];
    $row[] = $patientFname . " " . $patientLname;
    $row[] = ($aRow['facility_name']);
    $row[] = $tbResults[$aRow['result']];
    if (isset($aRow['last_modified_datetime']) && trim($aRow['last_modified_datetime']) != '' && $aRow['last_modified_datetime'] != '0000-00-00 00:00:00') {
        $xplodDate = explode(" ", $aRow['last_modified_datetime']);
        $aRow['last_modified_datetime'] = $general->humanDateFormat($xplodDate[0]) . " " . $xplodDate[1];
    } else {
        $aRow['last_modified_datetime'] = '';
    }
    $row[] = $aRow['last_modified_datetime'];
    $row[] = ucwords($aRow['status_name']);
    $row[] = $print;
    $output['aaData'][] = $row;
}

echo json_encode($output);

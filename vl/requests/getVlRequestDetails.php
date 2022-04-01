<?php

$general = new \Vlsm\Models\General();
$facilitiesDb = new \Vlsm\Models\Facilities();

$facilityMap = $facilitiesDb->getFacilityMap($_SESSION['userId']);

$gconfig = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();


$tableName = "vl_request_form";
$primaryKey = "vl_sample_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.last_modified_datetime', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
if ($_SESSION['instanceType'] == 'remoteuser') {
     $sampleCode = 'remote_sample_code';
} else if ($sarr['sc_user_type'] == 'standalone') {
     $aColumns = array('vl.sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');
     $orderColumns = array('vl.last_modified_datetime', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
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
$aWhere = '';
//$sQuery="SELECT vl.vl_sample_id,vl.facility_id,vl.patient_name,f.facility_name,f.facility_code,art.art_code,s.sample_name FROM vl_request_form as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id  INNER JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id INNER JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type";
$sQuery = "SELECT SQL_CALC_FOUND_ROWS 
                        vl.vl_sample_id,
                        vl.sample_code,
                        vl.remote_sample_code,
                        vl.patient_art_no,
                        vl.patient_first_name,
                        vl.patient_middle_name,
                        vl.patient_last_name,
                        vl.patient_dob,
                        vl.patient_gender,
                        vl.patient_age_in_years,
                        vl.sample_collection_date,
                        vl.treatment_initiated_date,
                        vl.date_of_initiation_of_current_regimen,
                        vl.test_requested_on,
                        vl.sample_tested_datetime,
                        vl.arv_adherance_percentage,
                        vl.is_sample_rejected,
                        vl.reason_for_sample_rejection,
                        vl.result_value_log,
                        vl.result_value_absolute,
                        vl.result,
                        vl.current_regimen,
                        vl.is_patient_pregnant,
                        vl.is_patient_breastfeeding,
                        vl.request_clinician_name,
                        vl.approver_comments,
                        vl.sample_received_at_hub_datetime,							
                        vl.sample_received_at_vl_lab_datetime,							
                        vl.result_dispatched_datetime,	
                        vl.result_printed_datetime,	
                        vl.last_modified_datetime,
                        vl.result_status,
                        vl.locked,
                        vl.data_sync,
                        s.sample_name,
                        b.batch_code,
                        ts.status_name,
                        f.facility_name,
                        testingLab.facility_name as lab_name,
                        f.facility_code,
                        f.facility_state,
                        f.facility_district,
                        u_d.user_name as reviewedBy,
                        a_u_d.user_name as approvedBy,
                        rs.rejection_reason_name,
                        tr.test_reason_name,
                        r_f_s.funding_source_name,
                        r_i_p.i_partner_name 
                        
                        FROM vl_request_form as vl 
                        
                        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                        LEFT JOIN facility_details as testingLab ON vl.lab_id=testingLab.facility_id 
                        LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type 
                        LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
                        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
                        LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
                        LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
                        LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
                        LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing 
                        LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
                        LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";

$start_date = '';
$end_date = '';
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
     $s_c_date = explode("to", $_POST['sampleCollectionDate']);
     if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
          $start_date = $general->dateFormat(trim($s_c_date[0]));
     }
     if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
          $end_date = $general->dateFormat(trim($s_c_date[1]));
     }
}

if (isset($sWhere) && $sWhere != "") {
     $sWhere = ' where ' . $sWhere;
     //$sQuery = $sQuery.' '.$sWhere;
     if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
          $sWhere = $sWhere . ' AND b.batch_code LIKE "%' . $_POST['batchCode'] . '%"';
     }
     if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
          if (trim($start_date) == trim($end_date)) {
               $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) = "' . $start_date . '"';
          } else {
               $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
          }
     }
     if (isset($_POST['sampleType']) && $_POST['sampleType'] != '') {
          $sWhere = $sWhere . ' AND s.sample_id = "' . $_POST['sampleType'] . '"';
     }
     if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
          $sWhere = $sWhere . ' AND f.facility_id IN (' . $_POST['facilityName'] . ')';
     }
     /* VL lab id filter */
     if (isset($_POST['vlLab']) && trim($_POST['vlLab']) != '') {
          $sWhere = $sWhere . ' AND vl.lab_id IN (' . $_POST['vlLab'] . ')';
     }
     /* Gender filter */
     if (isset($_POST['gender']) && trim($_POST['gender']) != '') {
          if (trim($_POST['gender']) == "not_recorded") {
               $sWhere = $sWhere . ' AND (vl.patient_gender = "not_recorded" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
          } else {
               $sWhere = $sWhere . ' AND vl.patient_gender ="' . $_POST['gender'] . '"';
          }
     }
     /* Show only recorded sample filter */
     if (isset($_POST['showReordSample']) && trim($_POST['showReordSample']) == 'yes') {
          $sWhere = $sWhere . ' AND vl.sample_reordered ="yes"';
     }
     /* Is patient pregnant filter */
     if (isset($_POST['patientPregnant']) && trim($_POST['patientPregnant']) != '') {
          $sWhere = $sWhere . ' AND vl.is_patient_pregnant ="' . $_POST['patientPregnant'] . '"';
     }
     /* Is patient breast feeding filter */
     if (isset($_POST['breastFeeding']) && trim($_POST['breastFeeding']) != '') {
          $sWhere = $sWhere . ' AND vl.is_patient_breastfeeding ="' . $_POST['breastFeeding'] . '"';
     }
     /* Funding src filter */
     if (isset($_POST['fundingSource']) && trim($_POST['fundingSource']) != '') {
          $sWhere = $sWhere . ' AND vl.funding_source ="' . base64_decode($_POST['fundingSource']) . '"';
     }
     /* Implemening partner filter */
     if (isset($_POST['implementingPartner']) && trim($_POST['implementingPartner']) != '') {
          $sWhere = $sWhere . ' AND vl.implementing_partner ="' . base64_decode($_POST['implementingPartner']) . '"';
     }
     if (isset($_POST['district']) && trim($_POST['district']) != '') {
          $sWhere = $sWhere . " AND f.facility_district LIKE '%" . $_POST['district'] . "%' ";
     }
     if (isset($_POST['state']) && trim($_POST['state']) != '') {
          $sWhere = $sWhere . " AND f.facility_state LIKE '%" . $_POST['state'] . "%' ";
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
                    if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
                         $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) = "' . $start_date . '"';
                    } else {
                         $sWhere = ' where ' . $sWhere;
                         $sWhere = $sWhere . ' DATE(vl.sample_collection_date) = "' . $start_date . '"';
                    }
               }
          } else {
               $setWhr = 'where';
               $sWhere = ' where ' . $sWhere;
               $sWhere = $sWhere . ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
          }
     }
     if (isset($_POST['sampleType']) && trim($_POST['sampleType']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND s.sample_id = "' . $_POST['sampleType'] . '"';
          } else {
               $setWhr = 'where';
               $sWhere = ' where ' . $sWhere;
               $sWhere = $sWhere . ' s.sample_id = "' . $_POST['sampleType'] . '"';
          }
     }
     if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' f.facility_id IN (' . $_POST['facilityName'] . ')';
          } else {
               $setWhr = 'where';
               $sWhere = ' where ' . $sWhere;
               $sWhere = $sWhere . ' f.facility_id IN (' . $_POST['facilityName'] . ')';
          }
     }
     if (isset($_POST['vlLab']) && trim($_POST['vlLab']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND vl.lab_id IN (' . $_POST['vlLab'] . ')';
          } else {
               $setWhr = 'where';
               $sWhere = ' where ' . $sWhere;
               $sWhere = $sWhere . ' vl.lab_id IN (' . $_POST['vlLab'] . ')';
          }
     }
     if (isset($_POST['gender']) && trim($_POST['gender']) != '') {
          if (trim($_POST['gender']) == "not_recorded") {
               if (isset($setWhr)) {
                    $sWhere = $sWhere . ' AND (vl.patient_gender = "not_recorded" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
               } else {
                    $setWhr = 'where';
                    $sWhere = ' where ' . $sWhere;
                    $sWhere = $sWhere . ' vl.patient_gender="not_recorded" OR vl.patient_gender="" OR vl.patient_gender IS NULL';
               }
          } else {
               if (isset($setWhr)) {
                    $sWhere = $sWhere . ' AND vl.patient_gender IN ("' . $_POST['gender'] . '")';
               } else {
                    $setWhr = 'where';
                    $sWhere = ' where ' . $sWhere;
                    $sWhere = $sWhere . ' vl.patient_gender IN ("' . $_POST['gender'] . '")';
               }
          }
     }
     if (isset($_POST['showReordSample']) && trim($_POST['showReordSample']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND vl.sample_reordered IN ("' . $_POST['showReordSample'] . '")';
          } else {
               $setWhr = 'where';
               $sWhere = ' where ' . $sWhere;
               $sWhere = $sWhere . ' vl.sample_reordered IN ("' . $_POST['showReordSample'] . '")';
          }
     }

     if (isset($_POST['patientPregnant']) && trim($_POST['patientPregnant']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND vl.is_patient_pregnant IN ("' . $_POST['patientPregnant'] . '")';
          } else {
               $setWhr = 'where';
               $sWhere = ' where ' . $sWhere;
               $sWhere = $sWhere . ' vl.is_patient_pregnant IN ("' . $_POST['patientPregnant'] . '")';
          }
     }

     if (isset($_POST['breastFeeding']) && trim($_POST['breastFeeding']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND vl.is_patient_breastfeeding IN ("' . $_POST['breastFeeding'] . '")';
          } else {
               $setWhr = 'where';
               $sWhere = ' where ' . $sWhere;
               $sWhere = $sWhere . ' vl.is_patient_breastfeeding IN ("' . $_POST['breastFeeding'] . '")';
          }
     }
     if (isset($_POST['fundingSource']) && trim($_POST['fundingSource']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND vl.funding_source IN ("' . base64_decode($_POST['fundingSource']) . '")';
          } else {
               $setWhr = 'where';
               $sWhere = ' where ' . $sWhere;
               $sWhere = $sWhere . ' vl.funding_source IN ("' . base64_decode($_POST['fundingSource']) . '")';
          }
     }
     if (isset($_POST['implementingPartner']) && trim($_POST['implementingPartner']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND vl.implementing_partner IN ("' . base64_decode($_POST['implementingPartner']) . '")';
          } else {
               $setWhr = 'where';
               $sWhere = ' where ' . $sWhere;
               $sWhere = $sWhere . ' vl.implementing_partner IN ("' . base64_decode($_POST['implementingPartner']) . '")';
          }
     }
     if (isset($_POST['district']) && trim($_POST['district']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . " AND f.facility_district LIKE '%" . $_POST['district'] . "%' ";
          } else {
               $setWhr = 'where';
               $sWhere = ' where ' . $sWhere;
               $sWhere = $sWhere . " f.facility_district LIKE '%" . $_POST['district'] . "%' ";
          }
     }
     if (isset($_POST['state']) && trim($_POST['state']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . " AND f.facility_state LIKE '%" . $_POST['state'] . "%' ";
          } else {
               $sWhere = ' where ' . $sWhere;
               $sWhere = $sWhere . " f.facility_state LIKE '%" . $_POST['state'] . "%' ";
          }
     }
}
$whereResult = '';
if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'result') {
     $whereResult = 'vl.result != "" AND ';
} else if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'noresult') {
     $whereResult = '(vl.result IS NULL OR vl.result = "") AND ';
}
if (isset($_POST['srcOfReq']) && trim($_POST['srcOfReq']) != '') {
     $whereResult = ' vl.source_of_request like "' . $_POST['srcOfReq'] . '" AND ';
}
if ($sWhere != '') {
     $sWhere = $sWhere . ' AND ' . $whereResult . 'vl.vlsm_country_id="' . $gconfig['vl_form'] . '"';
} else {
     $sWhere = $sWhere . ' where ' . $whereResult . 'vl.vlsm_country_id="' . $gconfig['vl_form'] . '"';
}
$sFilter = '';
if ($_SESSION['instanceType'] == 'remoteuser') {
     if (!empty($facilityMap)) {
          $sWhere = $sWhere . " AND vl.facility_id IN (" . $facilityMap . ")  ";
          $sFilter = " AND vl.facility_id IN (" . $facilityMap . ") ";
     }
} else {
     $sWhere = $sWhere . ' AND vl.result_status!=9';
     $sFilter = ' AND result_status!=9';
}
$sQuery = $sQuery . ' ' . $sWhere;

if (isset($sOrder) && $sOrder != "") {
     $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . " ORDER BY " . $sOrder;
}
$_SESSION['vlRequestSearchResultQuery'] = $sQuery;
if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

//echo($sQuery);die;

$rResult = $db->rawQuery($sQuery);

/* Data set length after filtering */
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];


// /* Total data set length */
// $aResultTotal =  $db->rawQueryOne("SELECT COUNT(vl_sample_id) as totalCount FROM vl_request_form as vl WHERE vlsm_country_id='" . $gconfig['vl_form'] . "'" . $sFilter);
// $iTotal = $aResultTotal['totalCount'];

/*
          * Output
          */
$output = array(
     "sEcho" => intval($_POST['sEcho']),
     "iTotalRecords" => $iTotal,
     "iTotalDisplayRecords" => $iFilteredTotal,
     "aaData" => array()
);
$editRequest = false;
$syncRequest = false;
if (isset($_SESSION['privileges']) && (in_array("editVlRequest.php", $_SESSION['privileges']))) {
     $editRequest = true;
     $syncRequest = true;
}

foreach ($rResult as $aRow) {

     $vlResult = '';
     $edit = '';
     $sync = '';
     $barcode = '';
     if (isset($aRow['sample_collection_date']) && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
          $xplodDate = explode(" ", $aRow['sample_collection_date']);
          $aRow['sample_collection_date'] = $general->humanDateFormat($xplodDate[0]);
     } else {
          $aRow['sample_collection_date'] = '';
     }
     if (isset($aRow['last_modified_datetime']) && trim($aRow['last_modified_datetime']) != '' && $aRow['last_modified_datetime'] != '0000-00-00 00:00:00') {
          $xplodDate = explode(" ", $aRow['last_modified_datetime']);
          $aRow['last_modified_datetime'] = $general->humanDateFormat($xplodDate[0]) . " " . $xplodDate[1];
     } else {
          $aRow['last_modified_datetime'] = '';
     }

     $patientFname = ucwords($general->crypto('decrypt', $aRow['patient_first_name'], $aRow['patient_art_no']));
     $patientMname = ucwords($general->crypto('decrypt', $aRow['patient_middle_name'], $aRow['patient_art_no']));
     $patientLname = ucwords($general->crypto('decrypt', $aRow['patient_last_name'], $aRow['patient_art_no']));


     $row = array();

     //$row[]='<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['vl_sample_id'] . '"  value="' . $aRow['vl_sample_id'] . '" onclick="toggleTest(this);"  />';
     $row[] = $aRow['sample_code'];
     if ($sarr['sc_user_type'] != 'standalone') {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = $aRow['sample_collection_date'];
     $row[] = $aRow['batch_code'];
     $row[] = $aRow['patient_art_no'];
     // $row[] = ucwords($patientFname);
     $row[] = ucwords($patientFname . " " . $patientMname . " " . $patientLname);
     $row[] = ucwords($aRow['facility_name']);
     $row[] = ucwords($aRow['facility_state']);
     $row[] = ucwords($aRow['facility_district']);
     $row[] = ucwords($aRow['sample_name']);
     $row[] = $aRow['result'];
     $row[] = $aRow['last_modified_datetime'];
     $row[] = ucwords($aRow['status_name']);

     //$printBarcode='<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="View" onclick="printBarcode(\''.base64_encode($aRow['vl_sample_id']).'\');"><i class="fa fa-barcode"> Print Barcode</i></a>';
     //$enterResult='<a href="javascript:void(0);" class="btn btn-success btn-xs" style="margin-right: 2px;" title="Result" onclick="showModal(\'updateVlResult.php?id=' . base64_encode($aRow['vl_sample_id']) . '\',900,520);"> Result</a>';

     if ($editRequest) {
          $edit = '<a href="editVlRequest.php?id=' . base64_encode($aRow['vl_sample_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _("Edit") . '"><i class="fa fa-pencil"> ' . _("Edit") . '</i></a>';
          if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes') {
               if (isset($_SESSION['privileges']) && !in_array("edit-locked-vl-samples", $_SESSION['privileges'])) {
                    $edit = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _("Locked") . '" disabled><i class="fa fa-lock"> ' . _("Locked") . '</i></a>';
               }
          }
     }

     if (isset($gconfig['bar_code_printing']) && $gconfig['bar_code_printing'] != "off") {
          $fac = ucwords($aRow['facility_name']) . " | " . $aRow['sample_collection_date'];
          $barcode = '<br><a href="javascript:void(0)" onclick="printBarcodeLabel(\'' . $aRow[$sampleCode] . '\',\'' . $fac . '\')" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _("Barcode") . '"><i class="fa fa-barcode"> </i> ' . _("Barcode") . ' </a>';
     }

     if ($syncRequest && $_SESSION['instanceType'] == 'vluser' && ($aRow['result_status'] == 7 || $aRow['result_status'] == 4)) {
          if ($aRow['data_sync'] == 0) {
               $sync = '<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="' . _("Sync this sample") . '" onclick="forceResultSync(\'' . ($aRow['sample_code']) . '\')"> ' . _("Sync") . '</a>';
          }
     } else {
          $sync = "";
     }

     $actions = "";
     if ($editRequest) {
          $actions .= $edit;
     }
     if ($syncRequest) {
          $actions .= $sync;
     }
     $row[] = $actions . $barcode;

     $output['aaData'][] = $row;
}
echo json_encode($output);

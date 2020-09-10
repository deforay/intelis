<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../../startup.php');  
include_once(APPLICATION_PATH.'/includes/MysqliDb.php');
include_once(APPLICATION_PATH.'/models/General.php');
$formConfigQuery ="SELECT * FROM global_config";
$configResult=$db->query($formConfigQuery);
$gconfig = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
     $gconfig[$configResult[$i]['name']] = $configResult[$i]['value'];
}
//system config
$systemConfigQuery ="SELECT * from system_config";
$systemConfigResult=$db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
     $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}

$general=new \Vlsm\Models\General($db);
$tableName="vl_request_form";
$primaryKey="vl_sample_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$aColumns = array('vl.sample_code','vl.remote_sample_code',"DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')",'b.batch_code','vl.patient_art_no','vl.patient_first_name','f.facility_name','f.facility_state','f.facility_district','s.sample_name','vl.result','ts.status_name');
$orderColumns = array('vl.sample_code','vl.remote_sample_code','vl.sample_collection_date','b.batch_code','vl.patient_art_no','vl.patient_first_name','f.facility_name','f.facility_state','f.facility_district','s.sample_name','vl.result','ts.status_name');
if($sarr['user_type']=='standalone') {
$aColumns = array('vl.sample_code',"DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')",'b.batch_code','vl.patient_art_no','vl.patient_first_name','f.facility_name','f.facility_state','f.facility_district','s.sample_name','vl.result','ts.status_name');
$orderColumns = array('vl.sample_code','vl.sample_collection_date','b.batch_code','vl.patient_art_no','vl.patient_first_name','f.facility_name','f.facility_state','f.facility_district','s.sample_name','vl.result','ts.status_name');
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
          $sQuery="SELECT * FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN r_art_code_details as art ON vl.current_regimen=art.art_id LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

          $start_date = '';
          $end_date = '';
          if(isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate'])!= ''){
               $s_c_date = explode("to", $_POST['sampleCollectionDate']);
               if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                    $start_date = $general->dateFormat(trim($s_c_date[0]));
               }
               if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                    $end_date = $general->dateFormat(trim($s_c_date[1]));
               }
          }

          if (isset($sWhere) && $sWhere != "") {
               $sWhere=' where '.$sWhere;
               if(isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate'])!= ''){
                    if (trim($start_date) == trim($end_date)) {
                         $sWhere = $sWhere.' AND DATE(vl.sample_collection_date) = "'.$start_date.'"';
                    }else{
                         $sWhere = $sWhere.' AND DATE(vl.sample_collection_date) >= "'.$start_date.'" AND DATE(vl.sample_collection_date) <= "'.$end_date.'"';
                    }
               }
               if(isset($_POST['formField']) && trim($_POST['formField'])!='')
               {
                    $sWhereSub = '';
                    $sWhereSubC = " AND (";
                         $searchArray = explode(",", $_POST['formField']);
                         foreach ($searchArray as $search) {
                         if ($sWhereSub == "") {
                         $sWhereSub .= $sWhereSubC;
                         $sWhereSub .= "(";
                              } else {
                              $sWhereSub .= " OR (";
                                   }
                                   $sWhereSub .= $search . " ='' OR ". $search." IS NULL";
                                   $sWhereSub .= ")";
                                   }
                                   $sWhereSub .= ")";
                                   $sWhere =$sWhere .$sWhereSub;
                                   }
                                   }else{
                                   if(isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate'])!= ''){
                                   $setWhr = 'where';
                                   $sWhere=' where '.$sWhere;
                                   $sWhere = $sWhere.' DATE(vl.sample_collection_date) >= "'.$start_date.'" AND DATE(vl.sample_collection_date) <= "'.$end_date.'"';
                                   }
                                   if(isset($_POST['formField']) && trim($_POST['formField'])!='')
                                   {
                                   if(isset($setWhr))
                                   {
                                   $sWhereSubC = " AND (";
                                        }else{
                                        $sWhereSubC = " where (";
                                             }
                                             $sWhereSub = '';
                                             $searchArray = explode(",", $_POST['formField']);
                                             foreach ($searchArray as $search) {
                                             if ($sWhereSub == "") {
                                             $sWhereSub .= $sWhereSubC;
                                             $sWhereSub .= "(";
                                                  } else {
                                                  $sWhereSub .= " OR (";
                                                       }
                                                       $sWhereSub .= $search . " ='' OR ". $search." IS NULL";
                                                       $sWhereSub .= ")";
                                                       }
                                                       $sWhereSub .= ")";
                                                       $sWhere .=$sWhereSub;
                                                       }
                                                       }

                                                       if($sWhere!=''){
                                                       $sWhere = $sWhere.' AND vl.vlsm_country_id="'.$gconfig['vl_form'].'"';
                                                       }else{
                                                       $sWhere = $sWhere.' where  vl.vlsm_country_id="'.$gconfig['vl_form'].'"';
                                                       }
                                                       $dWhere = '';
                                                       if($sarr['user_type']=='remoteuser'){

                                                       $userfacilityMapQuery = "SELECT GROUP_CONCAT(DISTINCT facility_id ORDER BY facility_id SEPARATOR ',') as facility_id FROM vl_user_facility_map where user_id='".$_SESSION['userId']."'";
                                                       $userfacilityMapresult = $db->rawQuery($userfacilityMapQuery);
                                                       if($userfacilityMapresult[0]['facility_id']!=null && $userfacilityMapresult[0]['facility_id']!=''){
                                                       $sWhere = $sWhere." AND vl.facility_id IN (".$userfacilityMapresult[0]['facility_id'].")   AND remote_sample='yes'";
                                                       $dWhere = $dWhere." AND vl.facility_id IN (".$userfacilityMapresult[0]['facility_id'].")   AND remote_sample='yes' ";
                                                       }
                                                       }

                                                       $sQuery = $sQuery.' '.$sWhere;
                                                       //echo $sQuery;die;
                                                       $_SESSION['vlIncompleteForm'] = $sQuery;
                                                       if (isset($sOrder) && $sOrder != "") {
                                                       $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
                                                       $sQuery = $sQuery.' order by '.$sOrder;
                                                       }

                                                       if (isset($sLimit) && isset($sOffset)) {
                                                       $sQuery = $sQuery.' LIMIT '.$sOffset.','. $sLimit;
                                                       }
                                                       //echo $sQuery;die;
                                                       $rResult = $db->rawQuery($sQuery);
                                                       /* Data set length after filtering */
                                                       $aResultFilterTotal =$db->rawQuery("SELECT vl.vl_sample_id,vl.facility_id,vl.patient_first_name,vl.result,f.facility_name,f.facility_code,vl.patient_art_no,s.sample_name,b.batch_code,vl.sample_batch_id,ts.status_name FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id $sWhere ORDER BY vl.last_modified_datetime DESC, $sOrder");
                                                       $iFilteredTotal = count($aResultFilterTotal);

                                                       /* Total data set length */
                                                       $aResultTotal =  $db->rawQuery("select COUNT(vl_sample_id) as total FROM vl_request_form as vl where vlsm_country_id='".$gconfig['vl_form']."' $dWhere");
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
                                                       if(isset($aRow['sample_collection_date']) && trim($aRow['sample_collection_date'])!= '' && $aRow['sample_collection_date']!= '0000-00-00 00:00:00'){
                                                       $xplodDate = explode(" ",$aRow['sample_collection_date']);
                                                       $aRow['sample_collection_date'] = $general->humanDateFormat($xplodDate[0]);
                                                       }else{
                                                       $aRow['sample_collection_date'] = '';
                                                       }


                                                       $patientFname = ucwords($general->crypto('decrypt',$aRow['patient_first_name'],$aRow['patient_art_no']));
                                                       $patientMname = ucwords($general->crypto('decrypt',$aRow['patient_middle_name'],$aRow['patient_art_no']));
                                                       $patientLname = ucwords($general->crypto('decrypt',$aRow['patient_last_name'],$aRow['patient_art_no']));
                                                       $row = array();
                                                       $row[] = $aRow['sample_code'];
                                                       if($sarr['user_type']!='standalone'){
                                                        $row[] = $aRow['remote_sample_code'];
                                                }
                                                       $row[] = $aRow['sample_collection_date'];
                                                       $row[] = $aRow['batch_code'];
                                                       $row[] = $aRow['patient_art_no'];
                                                       $row[] = ucwords($patientFname." ".$patientMname." ".$patientLname);
                                                       $row[] = ucwords($aRow['facility_name']);
                                                       $row[] = ucwords($aRow['facility_state']);
                                                       $row[] = ucwords($aRow['facility_district']);
                                                       $row[] = ucwords($aRow['sample_name']);
                                                       $row[] = $aRow['result'];
                                                       $row[] = ucwords($aRow['status_name']);
                                                       $output['aaData'][] = $row;
                                                       }

                                                       echo json_encode($output);

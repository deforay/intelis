<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../../startup.php');  


$formConfigQuery ="SELECT * from global_config where name='vl_form'";
$configResult=$db->query($formConfigQuery);
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
$general=new \Vlsm\Models\General($db);
$covid19Results = $general->getCovid19Results();

$tableName="form_covid19";
$primaryKey="covid19_id";
/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$aColumns = array('vl.sample_code','vl.remote_sample_code','b.batch_code','vl.patient_id','CONCAT(vl.patient_name, vl.patient_surname)','f.facility_name','vl.result','ts.status_name','funding_source_name','i_partner_name');
$orderColumns = array('vl.sample_code','vl.remote_sample_code','b.batch_code','vl.patient_id','vl.patient_name','f.facility_name','vl.result','ts.status_name','funding_source_name','i_partner_name');
$sampleCode = 'sample_code';
if($sarr['user_type']=='remoteuser'){
     $sampleCode = 'remote_sample_code';
}else if($sarr['user_type']=='standalone') {
     $aColumns = array('vl.sample_code','b.batch_code','vl.patient_id','CONCAT(vl.patient_name, vl.patient_surname)','f.facility_name','vl.result','ts.status_name','funding_source_name','i_partner_name');
     $orderColumns = array('vl.sample_code','b.batch_code','vl.patient_id','vl.patient_name','f.facility_name','vl.result','ts.status_name','funding_source_name','i_partner_name');
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
          $sQuery="SELECT 
                        vl.covid19_id,
                        vl.sample_code,
                        vl.remote_sample_code,
                        vl.patient_id,
                        vl.patient_name,
                        vl.patient_surname,
                        vl.patient_dob,
                        vl.patient_gender,
                        vl.patient_age,
                        vl.patient_province,
                        vl.patient_district,
                        vl.patient_nationality,
                        vl.patient_city,
                        vl.sample_collection_date,
                        vl.type_of_test_requested,
                        vl.date_of_symptom_onset,
                        vl.sample_condition,
                        vl.contact_with_confirmed_case,
                        vl.has_recent_travel_history,
                        vl.travel_country_names,
                        vl.travel_return_date,
                        vl.sample_tested_datetime,
                        vl.sample_received_at_vl_lab_datetime,
                        vl.is_sample_rejected,
                        vl.result,
                        vl.is_result_authorised,
                        vl.approver_comments,
                        vl.request_created_datetime,
                        vl.result_printed_datetime,
                        vl.testing_point,
                        rtr.test_reason_name,
                        b.batch_code,
                        ts.status_name,
                        rst.sample_name,
                        f.facility_name,
                        l_f.facility_name as labName,
                        f.facility_code,
                        f.facility_state,
                        f.facility_district,
                        u_d.user_name as reviewedBy,
                        a_u_d.user_name as approvedBy,
                        lt_u_d.user_name as labTechnician,
                        rs.rejection_reason_name,
                        r_f_s.funding_source_name,
                        c.iso_name as nationality,
                        r_i_p.i_partner_name 
                        
                        FROM form_covid19 as vl 
                        
                        LEFT JOIN countries as c ON vl.patient_nationality=c.id
                        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                        LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
                        LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
                        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
                        LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
                        LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
                        LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.lab_technician 
                        LEFT JOIN r_covid19_test_reasons as rtr ON rtr.test_reason_id=vl.reason_for_covid19_test 
                        LEFT JOIN r_covid19_sample_type as rst ON rst.sample_id=vl.specimen_type 
                        LEFT JOIN r_covid19_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
                        LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
                        LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";
          //echo $sQuery;die;
          $start_date = '';
          $end_date = '';
          if(isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate'])!= ''){
               $s_c_date = explode("to", $_POST['sampleCollectionDate']);
               //print_r($s_c_date);die;
               if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                    $start_date = $general->dateFormat(trim($s_c_date[0]));
               }
               if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                    $end_date = $general->dateFormat(trim($s_c_date[1]));
               }
          }
          $sTestDate = '';
          $eTestDate = '';
          if(isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate'])!= ''){
               $s_t_date = explode("to", $_POST['sampleTestDate']);
               if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
                    $sTestDate = $general->dateFormat(trim($s_t_date[0]));
               }
               if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
                    $eTestDate = $general->dateFormat(trim($s_t_date[1]));
               }
          }
          $sPrintDate = '';
          $ePrintDate = '';
          if(isset($_POST['printDate']) && trim($_POST['printDate'])!= ''){
               $s_p_date = explode("to", $_POST['printDate']);
               if (isset($s_p_date[0]) && trim($s_p_date[0]) != "") {
                    $sPrintDate = $general->dateFormat(trim($s_p_date[0]));
               }
               if (isset($s_p_date[1]) && trim($s_p_date[1]) != "") {
                    $ePrintDate = $general->dateFormat(trim($s_p_date[1]));
               }
          }


          if (isset($sWhere) && $sWhere != "") {
               $sWhere=' where '.$sWhere;
               //$sQuery = $sQuery.' '.$sWhere;
               if(isset($_POST['batchCode']) && trim($_POST['batchCode'])!= ''){
                    $sWhere = $sWhere.' AND b.batch_code = "'.$_POST['batchCode'].'"';
               }
               if(isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate'])!= ''){
                    if (trim($start_date) == trim($end_date)) {
                         $sWhere = $sWhere.' AND DATE(vl.sample_collection_date) = "'.$start_date.'"';
                    }else{
                         $sWhere = $sWhere.' AND DATE(vl.sample_collection_date) >= "'.$start_date.'" AND DATE(vl.sample_collection_date) <= "'.$end_date.'"';
                    }
               }
               if(isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate'])!= ''){
                    if (trim($sTestDate) == trim($eTestDate)) {
                         $sWhere = $sWhere.' AND DATE(vl.sample_tested_datetime) = "'.$sTestDate.'"';
                    }else{
                         $sWhere = $sWhere.' AND DATE(vl.sample_tested_datetime) >= "'.$sTestDate.'" AND DATE(vl.sample_tested_datetime) <= "'.$eTestDate.'"';
                    }
               }
               if(isset($_POST['printDate']) && trim($_POST['printDate'])!= ''){
                    if (trim($sPrintDate) == trim($eTestDate)) {
                         $sWhere = $sWhere.' AND DATE(vl.result_printed_datetime) = "'.$sPrintDate.'"';
                    }else{
                         $sWhere = $sWhere.' AND DATE(vl.result_printed_datetime) >= "'.$sPrintDate.'" AND DATE(vl.result_printed_datetime) <= "'.$ePrintDate.'"';
                    }
               }
               if(isset($_POST['vLoad']) && trim($_POST['vLoad'])!= ''){
                    
                    $sWhere = $sWhere.' AND vl.result like "'.$_POST['vLoad'].'"';
               }
               if(isset($_POST['status']) && trim($_POST['status'])!= ''){
                    $sWhere = $sWhere.' AND vl.result_status ='.$_POST['status'];
               }
               if(isset($_POST['fundingSource']) && trim($_POST['fundingSource'])!= ''){
                    $sWhere = $sWhere.' AND vl.funding_source ="'.base64_decode($_POST['fundingSource']).'"';
               }
               if(isset($_POST['implementingPartner']) && trim($_POST['implementingPartner'])!= ''){
                    $sWhere = $sWhere.' AND vl.implementing_partner ="'.base64_decode($_POST['implementingPartner']).'"';
               }
          }else{
               if(isset($_POST['batchCode']) && trim($_POST['batchCode'])!= ''){
                    $setWhr = 'where';
                    $sWhere=' where '.$sWhere;
                    $sWhere = $sWhere.' b.batch_code = "'.$_POST['batchCode'].'"';
               }
               if(isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate'])!= ''){
                    if(isset($setWhr)){
                         if (trim($start_date) == trim($end_date)) {
                              $sWhere = $sWhere.' AND DATE(vl.sample_collection_date) = "'.$start_date.'"';
                         }else{
                              $sWhere = $sWhere.' AND DATE(vl.sample_collection_date) >= "'.$start_date.'" AND DATE(vl.sample_collection_date) <= "'.$end_date.'"';
                         }
                    }else{
                         $setWhr = 'where';
                         $sWhere=' where '.$sWhere;
                         $sWhere = $sWhere.' DATE(vl.sample_collection_date) >= "'.$start_date.'" AND DATE(vl.sample_collection_date) <= "'.$end_date.'"';
                    }
               }
               if(isset($_POST['facilityName']) && trim($_POST['facilityName'])!= ''){
                    if(isset($setWhr)){
                         $sWhere = $sWhere." AND vl.facility_id IN (".$_POST['facilityName'].")";
                    }else{
                         $setWhr = 'where';
                         $sWhere=' where '.$sWhere;
                         $sWhere = $sWhere." vl.facility_id IN (".$_POST['facilityName'].")";
                    }
               }
               if(isset($_POST['vlLab']) && trim($_POST['vlLab'])!= ''){
                    if(isset($setWhr)){
                         $sWhere = $sWhere.' AND vl.lab_id = "'.$_POST['vlLab'].'"';
                    }else{
                         $setWhr = 'where';
                         $sWhere=' where '.$sWhere;
                         $sWhere = $sWhere.' vl.lab_id = "'.$_POST['vlLab'].'"';
                    }
               }
               if(isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate'])!= ''){
                    if(isset($setWhr)){
                         $sWhere = $sWhere.' AND DATE(vl.sample_tested_datetime) >= "'.$sTestDate.'" AND DATE(vl.sample_tested_datetime) <= "'.$eTestDate.'"';
                    }else{
                         $setWhr = 'where';
                         $sWhere=' where '.$sWhere;
                         $sWhere = $sWhere.' DATE(vl.sample_tested_datetime) >= "'.$sTestDate.'" AND DATE(vl.sample_tested_datetime) <= "'.$eTestDate.'"';
                    }
               }
               if(isset($_POST['printDate']) && trim($_POST['printDate'])!= ''){
                    if(isset($setWhr)){
                         $sWhere = $sWhere.' AND DATE(vl.result_printed_datetime) >= "'.$sPrintDate.'" AND DATE(vl.result_printed_datetime) <= "'.$ePrintDate.'"';
                    }else{
                         $setWhr = 'where';
                         $sWhere=' where '.$sWhere;
                         $sWhere = $sWhere.' DATE(vl.result_printed_datetime) >= "'.$sPrintDate.'" AND DATE(vl.result_printed_datetime) <= "'.$ePrintDate.'"';
                    }
               }
               if(isset($_POST['vLoad']) && trim($_POST['vLoad'])!= ''){
                    
                    if(isset($setWhr)){
                         $sWhere = $sWhere.' AND vl.result like "'.$_POST['vLoad'].'"';
                    }else{
                         $setWhr = 'where';
                         $sWhere=' where '.$sWhere;
                         $sWhere = $sWhere.' vl.result like "'.$_POST['vLoad'].'"';
                    }
               }
               if(isset($_POST['status']) && trim($_POST['status'])!= ''){
                    if(isset($setWhr)){
                         $sWhere = $sWhere.' AND vl.result_status ='.$_POST['status'];
                    }else{
                         $setWhr = 'where';
                         $sWhere=' where '.$sWhere;
                         $sWhere = $sWhere.' vl.result_status ='.$_POST['status'];
                    }
               }
               
               if(isset($_POST['fundingSource']) && trim($_POST['fundingSource'])!= ''){
                    if(isset($setWhr)){
                         $sWhere = $sWhere.' AND vl.funding_source ="'.base64_decode($_POST['fundingSource']).'"';
                    }else{
                         $setWhr = 'where';
                         $sWhere=' where '.$sWhere;
                         $sWhere = $sWhere.' vl.funding_source ="'.base64_decode($_POST['fundingSource']).'"';
                    }
               }
               if(isset($_POST['implementingPartner']) && trim($_POST['implementingPartner'])!= ''){
                    if(isset($setWhr)){
                         $sWhere = $sWhere.' AND vl.implementing_partner ="'.base64_decode($_POST['implementingPartner']).'"';
                    }else{
                         $setWhr = 'where';
                         $sWhere=' where '.$sWhere;
                         $sWhere = $sWhere.' vl.implementing_partner ="'.base64_decode($_POST['implementingPartner']).'"';
                    }
               }
          }
          if($sWhere!=''){
               $sWhere = $sWhere.' AND vl.result_status!=9';
          }else{
               $sWhere = $sWhere.' where vl.result_status!=9';
          }
          $cWhere = '';
          if($sarr['user_type']=='remoteuser'){
               //$sWhere = $sWhere." AND request_created_by='".$_SESSION['userId']."'";
               //$cWhere = " AND request_created_by='".$_SESSION['userId']."'";
               $userfacilityMapQuery = "SELECT GROUP_CONCAT(DISTINCT facility_id ORDER BY facility_id SEPARATOR ',') as facility_id FROM vl_user_facility_map where user_id='".$_SESSION['userId']."'";
               $userfacilityMapresult = $db->rawQuery($userfacilityMapQuery);
               if($userfacilityMapresult[0]['facility_id']!=null && $userfacilityMapresult[0]['facility_id']!=''){
                    $sWhere = $sWhere." AND vl.facility_id IN (".$userfacilityMapresult[0]['facility_id'].")   AND remote_sample='yes'";
                    $cWhere = " AND vl.facility_id IN (".$userfacilityMapresult[0]['facility_id'].")   AND remote_sample='yes'";

               }
          }
          $sQuery = $sQuery.' '.$sWhere;
          //echo $sQuery;die;
          
          
          if (isset($sOrder) && $sOrder != "") {
               $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
               $sQuery = $sQuery.' order by '.$sOrder;
          }
          
          $_SESSION['covid19ResultQuery']=$sQuery;
          
          if (isset($sLimit) && isset($sOffset)) {
               $sQuery = $sQuery.' LIMIT '.$sOffset.','. $sLimit;
          }
          // die($sQuery);
          $rResult = $db->rawQuery($sQuery);
          /* Data set length after filtering */

          $aResultFilterTotal =$db->rawQuery("SELECT vl.covid19_id 
          
          FROM form_covid19 as vl 
                        
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
          LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
          
          LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
          
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
          LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
          LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
          LEFT JOIN r_covid19_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
          
          LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
          LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner 
          
          $sWhere");
          
          $iFilteredTotal = count($aResultFilterTotal);

          /* Total data set length */
          $aResultTotal =  $db->rawQuery("select COUNT(covid19_id) as total FROM form_covid19 as vl where result_status!=9 $cWhere");
          // $aResultTotal = $countResult->fetch_row();
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

               $patientFname = ucwords($general->crypto('decrypt',$aRow['patient_name'],$aRow['patient_id']));
               $patientLname = ucwords($general->crypto('decrypt',$aRow['patient_surname'],$aRow['patient_id']));

               $row = array();
               $row[] = $aRow['sample_code'];
                    if($sarr['user_type']!='standalone'){
                         $row[] = $aRow['remote_sample_code'];
                    }
               $row[] = $aRow['batch_code'];
               $row[] = $aRow['patient_id'];
               $row[] = ucwords($patientFname." ".$patientLname);
               $row[] = ucwords($aRow['facility_name']);
               $row[] = $covid19Results[$aRow['result']];
               $row[] = ucwords($aRow['status_name']);
               $row[] = (isset($aRow['funding_source_name']) && trim($aRow['funding_source_name'])!= '')?ucwords($aRow['funding_source_name']):'';
               $row[] = (isset($aRow['i_partner_name']) && trim($aRow['i_partner_name'])!= '')?ucwords($aRow['i_partner_name']):'';
               if($aRow['is_result_authorised'] == 'yes'){
                    $row[] = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="View" onclick="convertSearchResultToPdf('.$aRow['covid19_id'].');"><i class="fa fa-file-text"></i> Result PDF</a>';
               }else{
                    $row[] = '<a href="javascript:void(0);" class="btn btn-default btn-xs disabled" style="margin-right: 2px;" title="View"><i class="fa fa-ban"></i> Not Authorized</a>';
               }

               $output['aaData'][] = $row;
          }

          echo json_encode($output);

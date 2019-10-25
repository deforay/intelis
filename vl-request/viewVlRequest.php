<?php
//print_r($result);die;
ob_start();
require_once('../startup.php'); include_once(APPLICATION_PATH.'/header.php');

include_once(APPLICATION_PATH.'/models/General.php');
$general=new General($db);
$tableName1="activity_log";
$id=base64_decode($_GET['id']);
$fQuery="SELECT vl.sample_code,vl.patient_first_name,vl.patient_art_no,vl.test_urgency,vl.patient_dob,vl.patient_gender,vl.patient_mobile_number,vl.patient_location,vl.sample_collection_date,vl.treatment_initiation,vl.date_of_initiation_of_current_regimen,vl.is_patient_pregnant,vl.is_patient_breastfeeding,vl.arv_adherance_percentage,vl.number_of_enhanced_sessions,vl.reason_for_vl_testing,vl.last_vl_date_routine,vl.last_vl_result_routine,vl.last_vl_sample_type_routine,vl.last_vl_date_failure_ac,vl.last_vl_result_failure_ac,vl.last_vl_sample_type_failure_ac,vl.last_vl_date_failure,vl.last_vl_result_failure,vl.last_vl_sample_type_failure,vl.request_clinician_name,vl.request_clinician_phone_number,vl.sample_tested_datetime,vl.vl_focal_person,vl.vl_focal_person_phone_number,vl.sample_received_at_vl_lab_datetime,vl.result_dispatched_datetime,vl.is_sample_rejected,vl.sample_rejection_facility,vl.reason_for_sample_rejection,vl.patient_other_id,vl.patient_age_in_years,vl.patient_age_in_months,vl.treatment_initiated_date,vl.patient_anc_no,vl.consent_to_receive_sms,vl.line_of_treatment,vl.lab_name,vl.lab_contact_person,vl.lab_phone_number,vl.sample_tested_datetime,vl.test_methods,vl.result_value_log,vl.result_value_absolute,vl.result_value_text,vl.result,vl.approver_comments,vl.result_reviewed_by,vl.result_reviewed_date,vl.result_status,ts.status_name,r_a_c_d.art_code,f.facility_name,f.facility_code,f.facility_emails,f.facility_state,f.facility_hub_name,r_s_t.sample_name,r_s_t_rm.sample_name as snrm,r_s_t_tfac.sample_name as sntfac,r_s_t_stf.sample_name as snstf,r_s_t_stdf.sample_name as stdf,r_s_t_mis.sample_name as mis from vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as r_s_t ON r_s_t.sample_id=vl.sample_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN r_vl_sample_type as r_s_t_rm ON r_s_t_rm.sample_id=vl.last_vl_sample_type_routine LEFT JOIN r_vl_sample_type as r_s_t_tfac ON r_s_t_tfac.sample_id=vl.last_vl_sample_type_failure_ac LEFT JOIN r_vl_sample_type as r_s_t_stf ON r_s_t_stf.sample_id=vl.last_vl_sample_type_failure LEFT JOIN r_vl_sample_type as r_s_t_stdf ON r_s_t_stdf.sample_id=vl.switch_to_tdf_sample_type LEFT JOIN r_vl_sample_type as r_s_t_mis ON r_s_t_mis.sample_id=vl.missing_sample_type LEFT JOIN r_art_code_details as r_a_c_d ON r_a_c_d.art_id=vl.current_regimen where vl_sample_id=$id";
//echo $fQuery;die;
$result=$db->query($fQuery);

//rejection facility and reason
$rejectionfQuery="SELECT * FROM facility_details where facility_id='".$result[0]['sample_rejection_facility']."'";
$rejectionfResult = $db->rawQuery($rejectionfQuery);

$rejectionrQuery="SELECT * FROM r_sample_rejection_reasons where rejection_reason_id='".$result[0]['reason_for_sample_rejection']."'";
$rejectionrResult = $db->rawQuery($rejectionrQuery);

if(isset($result[0]['patient_dob']) && trim($result[0]['patient_dob'])!='' && $result[0]['patient_dob']!='0000-00-00'){
 $result[0]['patient_dob']=$general->humanDateFormat($result[0]['patient_dob']);
}else{
 $result[0]['patient_dob']='';
}

if(isset($result[0]['sample_collection_date']) && trim($result[0]['sample_collection_date'])!='' && $result[0]['sample_collection_date']!='0000-00-00 00:00:00'){
 $expStr=explode(" ",$result[0]['sample_collection_date']);
 $result[0]['sample_collection_date']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
}else{
 $result[0]['sample_collection_date']='';
}

if(isset($result[0]['treatment_initiated_date']) && trim($result[0]['treatment_initiated_date'])!='' && trim($result[0]['treatment_initiated_date'])!='0000-00-00'){
 $result[0]['treatment_initiated_date']=$general->humanDateFormat($result[0]['treatment_initiated_date']);
}else{
 $result[0]['treatment_initiated_date']='';
}

if(isset($result[0]['date_of_initiation_of_current_regimen']) && trim($result[0]['date_of_initiation_of_current_regimen'])!='' && trim($result[0]['date_of_initiation_of_current_regimen'])!='0000-00-00'){
 $result[0]['date_of_initiation_of_current_regimen']=$general->humanDateFormat($result[0]['date_of_initiation_of_current_regimen']);
}else{
 $result[0]['date_of_initiation_of_current_regimen']='';
}

if(isset($result[0]['last_vl_date_routine']) && trim($result[0]['last_vl_date_routine'])!='' && trim($result[0]['last_vl_date_routine'])!='0000-00-00'){
 $result[0]['last_vl_date_routine']=$general->humanDateFormat($result[0]['last_vl_date_routine']);
}else{
 $result[0]['last_vl_date_routine']='';
}

if(isset($result[0]['last_vl_date_failure_ac']) && trim($result[0]['last_vl_date_failure_ac'])!='' && trim($result[0]['last_vl_date_failure_ac'])!='0000-00-00'){
 $result[0]['last_vl_date_failure_ac']=$general->humanDateFormat($result[0]['last_vl_date_failure_ac']);
}else{
 $result[0]['last_vl_date_failure_ac']='';
}

if(isset($result[0]['last_vl_date_failure']) && trim($result[0]['last_vl_date_failure'])!='' && trim($result[0]['last_vl_date_failure'])!='0000-00-00'){
 $result[0]['last_vl_date_failure']=$general->humanDateFormat($result[0]['last_vl_date_failure']);
}else{
 $result[0]['last_vl_date_failure']='';
}

if(isset($result[0]['sample_tested_datetime']) && trim($result[0]['sample_tested_datetime'])!='' && trim($result[0]['sample_tested_datetime'])!='0000-00-00 00:00:00'){
 $expStr=explode(" ",$result[0]['sample_tested_datetime']);
 $result[0]['sample_tested_datetime']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
}else{
 $result[0]['sample_tested_datetime']='';
}

if(isset($result[0]['sample_received_at_vl_lab_datetime']) && trim($result[0]['sample_received_at_vl_lab_datetime'])!='' && trim($result[0]['sample_received_at_vl_lab_datetime'])!='0000-00-00 00:00:00'){
 $expStr=explode(" ",$result[0]['sample_received_at_vl_lab_datetime']);
 $result[0]['sample_received_at_vl_lab_datetime']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
}else{
 $result[0]['sample_received_at_vl_lab_datetime']='';
}

if(isset($result[0]['sample_tested_datetime']) && trim($result[0]['sample_tested_datetime'])!='' && trim($result[0]['sample_tested_datetime'])!='0000-00-00 00:00:00'){
 $expStr=explode(" ",$result[0]['sample_tested_datetime']);
 $result[0]['sample_tested_datetime']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
}else{
 $result[0]['sample_tested_datetime']='';
}

if(isset($result[0]['result_dispatched_datetime']) && trim($result[0]['result_dispatched_datetime'])!='' && trim($result[0]['result_dispatched_datetime'])!='0000-00-00 00:00:00'){
 $expStr=explode(" ",$result[0]['result_dispatched_datetime']);
 $result[0]['result_dispatched_datetime']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
}else{
 $result[0]['result_dispatched_datetime']='';
}

if(isset($result[0]['result_reviewed_datetime']) && trim($result[0]['result_reviewed_datetime'])!='' && trim($result[0]['result_reviewed_datetime'])!='0000-00-00 00:00:00'){
 $expStr=explode(" ",$result[0]['result_reviewed_datetime']);
 $result[0]['result_reviewed_datetime']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
}else{
 $result[0]['result_reviewed_datetime']='';
}
//Add event log
$eventType = 'view-vl-request';
$action = ucwords($_SESSION['userName']).' viewed a request data with the sample code '.$result[0]['sample_code'];
$resource = 'vl-request';
$general->activityLog($eventType,$action,$resource);

// $data=array(
// 'event_type'=>$eventType,
// 'action'=>$action,
// 'resource'=>$resource,
// 'date_time'=>$general->getDateTime()
// );
// $db->insert($tableName1,$data);
?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
   <style>
   #toogleResultDiv{
     display:none;
   }
   </style>
   <link rel="stylesheet" media="all" type="text/css" href="http://code.jquery.com/ui/1.11.0/themes/smoothness/jquery-ui.css" />
    <section class="content-header">
      <h1>View VL Request</h1>
      <ol class="breadcrumb">
        <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">View VL Request</li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">
      <!-- SELECT2 EXAMPLE -->
      <div class="box box-default">
        <!--<div class="box-header with-border">
          <div class="pull-right" style="font-size:15px;"></div>
        </div>-->
        <!-- /.box-header -->
        <div class="box-body">
          <!-- form start -->
            <div class="box-body">
              <div class="row">
                   <div class="col-md-12"><h4><a id="vlrfa" href="javascript:void(0);" onclick="formToggler('-');">VL Request Form Details <i class="fa fa-minus"></i></a></h4></div>
               </div>
             <div id="toogleFormDiv">
              <div class="box box-default">
            <div class="box-header with-border">
              <h3 class="box-title">Clinic Information</h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
             <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="facilityName" class="col-lg-4 control-label">Facility Name </label>
                        <div class="col-lg-7" style="font-style:italic;">
                            <?php echo ucwords($result[0]['facility_name']); ?>
                        </div>
                    </div>
                  </div>
                   <div class="col-md-6">
                    <div class="form-group">
                        <label for="facilityCode" class="col-lg-4 control-label">Facility Code </label>
                        <div class="col-lg-7" style="font-style:italic;">
                            <?php echo $result[0]['facility_code']; ?>
                        </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                  
                   <div class="col-md-6">
                    <div class="form-group">
                        <label for="state" class="col-lg-4 control-label">State/Province</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['facility_state']); ?>
                        </div>
                    </div>
                  </div>
                   
                   <div class="col-md-6">
                    <div class="form-group">
                        <label for="hubName" class="col-lg-4 control-label">Linked Hub Name (If Applicable)</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['facility_hub_name']); ?>
                        </div>
                    </div>
                  </div> 
                </div>
                <div class="row">
                 <div class="col-md-6">
                    <div class="form-group">
                        <label for="hubName" class="col-lg-4 control-label">Urgency</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['test_urgency']); ?>
                        </div>
                    </div>
                  </div> 
                </div>
              </div>
            </div>
            <!-- /.box-footer-->
              
         <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">Patient Details</h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
             <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="artNo" class="col-lg-4 control-label">Unique ART No. </label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['patient_art_no']; ?>
                        </div>
                    </div>
                  </div>
                 <div class="col-md-6">
                    <div class="form-group">
                        <label for="sampleCode" class="col-lg-4 control-label">Sample Code </label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['sample_code']; ?>
                        </div>
                    </div>
                  </div>
                  
                   
                </div>
                <div class="row">
                 <div class="col-md-6">
                    <div class="form-group">
                        <label for="otrId" class="col-lg-4 control-label">Other Id</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['patient_other_id']; ?>
                        </div>
                    </div>
                   </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="patientName" class="col-lg-4 control-label">Patient's Name </label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['patient_first_name']); ?>
                        </div>
                    </div>
                  </div>
                </div>
                
                <div class="row">
                     <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-lg-4 control-label">Date of Birth</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['patient_dob']; ?>
                        </div>
                    </div>
                  </div>
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="ageInYrs" class="col-lg-4 control-label">Age in years</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['patient_age_in_years']; ?>
                        <br><p class="help-block"><small>If DOB Unkown</small></p>
                        </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="ageInMtns" class="col-lg-4 control-label">Age in months</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['patient_age_in_months']; ?>
                        <br><p class="help-block"><small>If age < 1 year </small></p>
                        </div>
                    </div>
                  </div>
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="gender" class="col-lg-4 control-label">Gender</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['patient_gender']); ?>
                        </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                 <div class="col-md-6">
                    <div class="form-group">
                        <label for="ArvAdherence" class="col-lg-4 control-label">Patient consent to receive SMS? </label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['consent_to_receive_sms']); ?>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="patientPhoneNumber" class="col-lg-4 control-label">Phone Number</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['patient_mobile_number']; ?>
                        </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                   <div class="col-md-6">
                    <div class="form-group">
                        <label for="location" class="col-lg-4 control-label">Location/District Code</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['patient_location']); ?>
                        </div>
                    </div>
                  </div>
                </div>
                 
                <div class="row">
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="requestClinician" class="col-lg-4 control-label">Request Clinician</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['request_clinician_name']; ?>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="clinicianPhone" class="col-lg-4 control-label">Phone No.</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['request_clinician_phone_number']; ?>
                        </div>
                    </div>
                  </div>                       
                </div>
                <div class="row">
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="requestDate" class="col-lg-4 control-label">Request Date</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['sample_tested_datetime']; ?>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="vlFocalPerson" class="col-lg-4 control-label">VL Focal Person</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo ucwords($result[0]['vl_focal_person']); ?>
                        </div>
                    </div>
                  </div>                       
                </div>
                <div class="row">
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="vlPhoneNumber" class="col-lg-4 control-label">VL Focal Person Phone Number</label>
                        <div class="col-lg-7" style="font-style:italic;">
                         <?php echo $result[0]['vl_focal_person_phone_number']; ?>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="emailHf" class="col-lg-4 control-label">Email for HF</label>
                        <div class="col-lg-7" style="font-style:italic;">
                         <?php echo $result[0]['facility_emails']; ?>
                        </div>
                    </div>
                  </div>                       
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="rejection" class="col-lg-4 control-label">Rejected by Clinic </label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo ucfirst($result[0]['is_sample_rejected']); ?>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="rejectionFacility" class="col-lg-4 control-label">Rejection Clinic</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo ucfirst($rejectionfResult[0]['facility_name']); ?>
                        </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="rejectionReason" class="col-lg-4 control-label">Rejection Reason </label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo ucfirst($rejectionrResult[0]['rejection_reason_name']); ?>
                        </div>
                    </div>
                  </div>                                    
                </div>
            </div>
            
            <!-- /.box-footer-->
          </div>
               
               <div class="box box-danger ">
            <div class="box-header with-border">
              <h3 class="box-title">Sample Information </h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <div class="row">
                    <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-lg-4 control-label">Sample Collected On</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['sample_collection_date']; ?>
                        </div>
                    </div>
                  </div>    
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="sampleType" class="col-lg-4 control-label">Sample Type </label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['sample_name']); ?>
                        </div>
                    </div>
                  </div>                       
                </div>
            </div>
            <!-- /.box-footer-->
          </div>
                
                <div class="box box-warning">
            <div class="box-header with-border">
              <h3 class="box-title">Treatment Information</h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
             <div class="row">
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="treatPeriod" class="col-lg-4 control-label">How long has this patient been on treatment ?</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['treatment_initiation']; ?>
                        </div>
                    </div>
                  </div>    
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="treatmentInitiatiatedOn" class="col-lg-4 control-label">Treatment Initiated On</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['treatment_initiated_date']; ?>
                        </div>
                    </div>
                  </div>                       
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="currentRegimen" class="col-lg-4 control-label">Current Regimen</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['art_code']; ?>
                        </div>
                    </div>
                  </div>    
                  <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-lg-4 control-label">Current Regimen Initiated On</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['date_of_initiation_of_current_regimen']; ?>
                        </div>
                    </div>
                  </div>                       
                </div>
                <div class="row">
                    <div class="col-md-12">
                    <div class="form-group">
                        <label for="treatmentDetails" class="col-lg-2 control-label">Which line of treatment is Patient on ?</label>
                        <div class="col-lg-10" style="font-style:italic;">
                           <?php echo ucwords($result[0]['line_of_treatment']); ?>
                        </div>
                    </div>
                  </div>    
                </div>
                <div class="row">
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="pregYes" class="col-lg-4 control-label">Is Patient Pregnant ?</label>
                        <div class="col-lg-7" style="font-style:italic;">                        
                            <?php echo ucfirst($result[0]['is_patient_pregnant']); ?>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="arcNo" class="col-lg-4 control-label">If Pregnant, ARC No.</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['patient_anc_no']; ?>
                        </div>
                    </div>
                  </div>                       
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="breastfeeding" class="col-lg-4 control-label">Is Patient Breastfeeding?</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucfirst($result[0]['is_patient_breastfeeding']); ?>
                        </div>
                    </div>
                  </div>
                </div>
            </div>
            <!-- /.box-footer-->
          </div>
          <div class="box box-success">
            <div class="box-header with-border">
              <h3 class="box-title">Indication for viral load testing</h3>
              <small>(Please tick one):(To be completed by clinician)</small>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
             
             <div class="row">                
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="col-lg-12">
                            <label class="control-label">
                               <?php if($result[0]['reason_for_vl_testing']=='routine'){ ?>
                                 <strong>Routine Monitoring</strong>
                               <?php } elseif($result[0]['reason_for_vl_testing']=='failure'){ ?>
                                 <strong>Repeat VL test after suspected treatment failure adherence counseling</strong>
                               <?php } elseif($result[0]['reason_for_vl_testing']=='suspect'){ ?>
                                 <strong>Suspect Treatment Failure</strong>
                               <?php } elseif($result[0]['reason_for_vl_testing']=='switch'){ ?>
                                 <strong>Switch to TDF</strong>
                               <?php } elseif($result[0]['reason_for_vl_testing']=='missing'){ ?>
                                 <strong>Missing</strong>
                               <?php } ?>
                            </label>						
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if($result[0]['reason_for_vl_testing']=='routine'){ ?>
                  <div class="row">
                     <div class="col-md-4">
                      <div class="form-group">
                          <label class="col-lg-4 control-label">Last VL Date</label>
                          <div class="col-lg-7" style="font-style:italic;">
                             <?php echo $result[0]['last_vl_date_routine']; ?>
                          </div>
                      </div>
                    </div>
                     <div class="col-md-4">
                      <div class="form-group">
                          <label for="rmTestingVlValue" class="col-lg-4 control-label">VL Value</label>
                          <div class="col-lg-7" style="font-style:italic;">
                            <?php echo $result[0]['last_vl_result_routine']; ?>
                          </div>
                      </div>
                    </div>
                     <div class="col-md-4">
                      <div class="form-group">
                          <label for="rmTestingSampleType" class="col-lg-4 control-label">Sample Type</label>
                          <div class="col-lg-7" style="font-style:italic;">
                             <?php echo ucwords($result[0]['snrm']); ?>
                          </div>
                      </div>
                    </div>                   
                  </div>
                <?php } elseif($result[0]['reason_for_vl_testing']=='failure'){ ?>
                    <div class="row">
                      <div class="col-md-4">
                       <div class="form-group">
                           <label class="col-lg-4 control-label">Last VL Date</label>
                           <div class="col-lg-7" style="font-style:italic;">
                              <?php echo $result[0]['last_vl_date_failure_ac']; ?>
                           </div>
                       </div>
                     </div>
                      <div class="col-md-4">
                       <div class="form-group">
                           <label for="repeatTestingVlValue" class="col-lg-4 control-label">VL Value</label>
                           <div class="col-lg-7" style="font-style:italic;">
                              <?php echo $result[0]['last_vl_result_failure_ac']; ?>
                           </div>
                       </div>
                     </div>
                      <div class="col-md-4">
                       <div class="form-group">
                           <label for="repeatTestingSampleType" class="col-lg-4 control-label">Sample Type</label>
                           <div class="col-lg-7" style="font-style:italic;">
                              <?php echo ucwords($result[0]['sntfac']); ?>
                           </div>
                       </div>
                     </div>                   
                   </div>
                <?php } elseif($result[0]['reason_for_vl_testing']=='suspect'){ ?>
                   <div class="row">
                   <div class="col-md-4">
                    <div class="form-group">
                        <label for="suspendTreatmentLastVLDate" class="col-lg-4 control-label">Last VL Date</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['last_vl_date_failure']; ?>
                        </div>
                    </div>
                  </div>
                   <div class="col-md-4">
                    <div class="form-group">
                        <label for="suspendTreatmentVlValue" class="col-lg-4 control-label">VL Value</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['last_vl_result_failure']; ?>
                        </div>
                    </div>
                  </div>
                   <div class="col-md-4">
                    <div class="form-group">
                        <label for="suspendTreatmentSampleType" class="col-lg-4 control-label">Sample Type</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['snstf']); ?>
                        </div>
                    </div>
                  </div>                   
                </div>
                <?php } ?>
                <div class="row">
              <div class="col-md-6">
                    <div class="form-group">
                        <label for="ArvAdherence" class="col-lg-4 control-label">ARV Adherence </label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['arv_adherance_percentage']); ?>
                        </div>
                    </div>
                  </div>
              <div class="col-md-6">
                    <div class="form-group">
                        <label for="enhanceSession" class="col-lg-4 control-label">Enhance Session </label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['number_of_enhanced_sessions']); ?>
                        </div>
                    </div>
                  </div>
             </div>
            </div>
            <!-- /.box-footer-->
          </div>
               
                
                
             </div>
             
             <div class="row">
                <div class="col-md-12"><h4><a id="lra" href="javascript:void(0);" onclick="resultToggler('+');">Lab/Result Details <i class="fa fa-plus"></i></a></h4></div>
             </div>
             
            <div id="toogleResultDiv" class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">Lab Details</h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <div class="row">
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="labName" class="col-lg-4 control-label">Lab Name </label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['lab_name']); ?>
                        </div>
                    </div>
                   </div>
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="labContactPerson" class="col-lg-4 control-label">Lab Contact Person </label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo ucwords($result[0]['lab_contact_person']); ?>
                        </div>
                    </div>
                   </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="labPhoneNo" class="col-lg-4 control-label">Phone Number </label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['lab_phone_number']; ?>
                        </div>
                    </div>
                   </div>
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="" class="col-lg-4 control-label">Date Sample Received at Testing Lab</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['sample_received_at_vl_lab_datetime']; ?>
                        </div>
                    </div>
                  </div>
                </div>
                
                <div class="row">
                 <div class="col-md-6">
                    <div class="form-group">
                        <label for="" class="col-lg-4 control-label">Sample Testing Date</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['sample_tested_datetime']; ?>
                        </div>
                    </div>
                  </div>
                 <div class="col-md-6">
                    <div class="form-group">
                        <label for="" class="col-lg-4 control-label">Date Results Dispatched</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['result_dispatched_datetime']; ?>
                        </div>
                    </div>
                  </div>
                </div>
                
                <div class="row">
                 <!--<div class="col-md-6">
                    <div class="form-group">
                        <label for="reviewedBy" class="col-lg-4 control-label">Reviewed By</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          < ?php echo ucwords($result[0]['result_reviewed_by']); ?>
                        </div>
                    </div>
                  </div>-->
                 <div class="col-md-6">
                    <div class="form-group">
                        <label for="" class="col-lg-4 control-label">Reviewed Date</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo $result[0]['result_reviewed_datetime']; ?>
                        </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                 <div class="col-md-6">
                    <div class="form-group">
                        <label for="" class="col-lg-4 control-label">Test Methods</label>
                        <div class="col-lg-7" style="font-style:italic;">
                           <?php echo ucwords($result[0]['test_methods']); ?>
                        </div>
                    </div>
                  </div>
                </div>
                
                 <div class="row">
                   <div class="col-md-12"><h4>Result Details</h4></div>
                 </div>
                 
                 <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="logValue" class="col-lg-4 control-label">Log Value</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['result_value_log']; ?>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="absoluteValue" class="col-lg-4 control-label">Absolute Value</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['result_value_absolute']; ?>
                        </div>
                    </div>
                  </div>
                </div>
                 <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="textValue" class="col-lg-4 control-label">Text Value</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo $result[0]['result_value_text']; ?>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="result" class="col-lg-4 control-label">Result</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo ucfirst($result[0]['result']); ?>
                        </div>
                    </div>
                  </div>
                </div>
                 <br>
                 <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="comments" class="col-lg-4 control-label">Comments</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo ucfirst($result[0]['approver_comments']); ?>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="status" class="col-lg-4 control-label">Status</label>
                        <div class="col-lg-7" style="font-style:italic;">
                          <?php echo ucwords($result[0]['status_name']); ?>
                        </div>
                    </div>
                  </div>
                 </div>
            </div>
          </div>
        </div>
        <!-- /.box-body -->
        <!-- /.row -->
        </div>
      </div>
      <!-- /.box -->
    </section>
    <!-- /.content -->
  </div>
  <script type="text/javascript">
    function resultToggler(symbol) {
      if(symbol == "+"){
          $("#toogleResultDiv").slideToggle();
          $("#lra").html('Lab/Result Details <i class="fa fa-minus"></i>');
          $("#lra").attr("onclick", "resultToggler('-')");
      }else{
        $("#toogleResultDiv").slideToggle();
        $("#lra").html('Lab/Result Details <i class="fa fa-plus"></i>');
        $("#lra").attr("onclick", "resultToggler('+')");
      }
    }
    
    function formToggler(symbol){
      if(symbol == "-"){
          $("#toogleFormDiv").slideToggle();
          $("#vlrfa").html('VL Request Form Details <i class="fa fa-plus"></i>');
          $("#vlrfa").attr("onclick", "formToggler('+')");
      }else{
        $("#toogleFormDiv").slideToggle();
        $("#vlrfa").html('VL Request Form Details <i class="fa fa-minus"></i>');
        $("#vlrfa").attr("onclick", "formToggler('-')");
      }
    }
  </script>
 <?php
 include(APPLICATION_PATH.'/footer.php');
 ?>

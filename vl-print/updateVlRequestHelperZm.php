<?php
session_start();
ob_start();
include('../includes/MysqliDb.php');
include('../General.php');
$general=new Deforay_Commons_General();
$tableName="vl_request_form";
$tableName1="activity_log";
$tableName2="log_result_updates";
try {
     if(isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab'])!=""){
          $sampleTestingDateLab = explode(" ",$_POST['sampleTestingDateAtLab']);
          $_POST['sampleTestingDateAtLab']=$general->dateFormat($sampleTestingDateLab[0])." ".$sampleTestingDateLab[1];  
     }
     if(!isset($_POST['noResult'])){
          $_POST['noResult'] = '';
     }
     $_POST['result'] = '';
     if($_POST['vlResult']!=''){
          $_POST['result'] = $_POST['vlResult'];
     }else if($_POST['vlLog']!=''){
          $_POST['result'] = $_POST['vlLog'];
     }
     //check vl result textbox changes
     $viralLoadData = array('result_value_absolute'=>$_POST['vlResult'],'result_value_log'=>$_POST['vlLog']);
     $db = $db->where('vl_sample_id',$_POST['treamentId']);
     $vloadResultUpdate = $db->update($tableName,$viralLoadData);
     
     if($_POST['testingPlatform']!=''){
          $platForm = explode("##",$_POST['testingPlatform']);
          $_POST['testingPlatform'] = $platForm[0];
          }
       $vldata=array(
          'serial_no'=>$_POST['serialNo'],
          'sample_code'=>$_POST['serialNo'],
          'lab_code'=>$_POST['labNo'],
          'lab_id'=>$_POST['labId'],
          'vl_test_platform'=>$_POST['testingPlatform'],
          'sample_tested_datetime'=>$_POST['sampleTestingDateAtLab'],
          'result_value_absolute'=>$_POST['vlResult'],
          'result'=>$_POST['result'],
          'result_value_log'=>$_POST['vlLog'],
          'approver_comments'=>$_POST['labComments'],
          'result_approved_by'=>$_POST['approvedBy'],
          'result_reviewed_by'=>$_POST['reviewedBy'],
          'is_sample_rejected'=>$_POST['noResult'],
          'last_modified_datetime'=>$general->getDateTime()
        );
          if(isset($_POST['specimenType']) && trim($_POST['specimenType'])!= ''){
               $vldata['sample_type']=$_POST['specimenType'];
          }else{
               $vldata['sample_type'] = null;
          }
          if(isset($_POST['status']) && trim($_POST['status'])!= ''){
               $vldata['result_status']=$_POST['status'];
          }
          //print_r($vldata);die;
          if($vloadResultUpdate){
            $vldata['manual_result_entry']='yes';
            $vldata['import_machine_file_name']='';
          }
          $db=$db->where('vl_sample_id',$_POST['treamentId']);
          $db->update($tableName,$vldata);
          $_SESSION['alertMsg']="VL result updated successfully";
          //Add update result log
          $data=array(
          'user_id'=>$_SESSION['userId'],
          'vl_sample_id'=>$_POST['treamentId'],
          'updated_on'=>$general->getDateTime()
          );
          $db->insert($tableName2,$data);
          header("location:vlResultApproval.php");
    
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
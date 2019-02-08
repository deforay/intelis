<?php
session_start();
ob_start();
include('../includes/MysqliDb.php');
include('../General.php');
$general=new General($db);
$tableName="vl_request_form";
$tableName1="activity_log";
$tableName2="log_result_updates";
try {
     //Set sample received date
    if(isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate'])!=""){
        $sampleReceivedDate = explode(" ",$_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate']=$general->dateFormat($sampleReceivedDate[0])." ".$sampleReceivedDate[1];
    }else{
       $_POST['sampleReceivedDate'] = NULL;
    }
    //Set sample rejection reason
    if(isset($_POST['status']) && trim($_POST['status']) != ''){
        if($_POST['status'] == 4){
            if(trim($_POST['rejectionReason']) == "other" && trim($_POST['newRejectionReason']!= '')){
                $data=array(
                'rejection_reason_name'=>$_POST['newRejectionReason'],
                'rejection_reason_status'=>'active'
                );
                $id=$db->insert('r_sample_rejection_reasons',$data);
                $_POST['rejectionReason'] = $id;
            }
        }else{
            $_POST['rejectionReason'] = NULL;
        }
    }
     //Set result prinetd date time
     if(isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab'])!=""){
          $sampleTestingDateLab = explode(" ",$_POST['sampleTestingDateAtLab']);
          $_POST['sampleTestingDateAtLab']=$general->dateFormat($sampleTestingDateLab[0])." ".$sampleTestingDateLab[1];  
     }else{
         $_POST['sampleTestingDateAtLab'] = NULL;
     }
    //Set sample testing date
    if(isset($_POST['dateOfCompletionOfViralLoad']) && trim($_POST['dateOfCompletionOfViralLoad'])!=""){
      $dateofCompletionofViralLoad = explode(" ",$_POST['dateOfCompletionOfViralLoad']);
      $_POST['dateOfCompletionOfViralLoad'] = $general->dateFormat($dateofCompletionofViralLoad[0])." ".$dateofCompletionofViralLoad[1];  
    }else{
      $_POST['dateOfCompletionOfViralLoad'] = NULL;
    }
    //if(!isset($_POST['sampleCode']) || trim($_POST['sampleCode'])== ''){
    //    $_POST['sampleCode'] = NULL;
    //}
    $testingPlatform = '';
    if(isset($_POST['testingPlatform']) && trim($_POST['testingPlatform'])!=''){
        $platForm = explode("##",$_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
    }


    $textResult =  null;
    if(isset($_POST['vlTND']) && $_POST['vlTND'] =='yes' && $_POST['rejectionReason'] == NULL){
        $textResult = $_POST['vlResult'] = 'Target not Detected';
        $_POST['vlLog'] = '';
    }
    if(isset($_POST['vlLt20']) && $_POST['vlLt20'] =='yes' && $_POST['rejectionReason'] == NULL){
        $textResult = $_POST['vlResult'] = '< 20';
        $_POST['vlLog'] = '';
    }
    if(isset($_POST['vlLt40']) && $_POST['vlLt40'] =='yes' && $_POST['rejectionReason'] == NULL){
        $textResult = $_POST['vlResult'] = '< 40';
        $_POST['vlLog'] = '';
    }

    //echo "<pre>";var_dump($_POST);die;
    
        $vldata=array(
          'sample_received_at_vl_lab_datetime'=>$_POST['sampleReceivedDate'],
          //'sample_code'=>$_POST['sampleCode'],
          //'serial_no'=>$_POST['sampleCode'],
          'sample_tested_datetime'=>$_POST['dateOfCompletionOfViralLoad'],
          'vl_test_platform'=>$testingPlatform,
          'result_value_log'=>$_POST['vlLog'],
          'result'=>$_POST['vlResult'],
          'result_value_text'=>$textResult,
          //'result_printed_datetime'=>$_POST['sampleTestingDateAtLab'],
          'last_modified_datetime'=>$general->getDateTime(),
          'lab_id'=>(isset($_POST['labId']) && $_POST['labId']!='' ? $_POST['labId'] :  NULL),
          'data_sync'=>0
        );
        if(isset($_POST['status']) && trim($_POST['status'])!= ''){
            $vldata['result_status'] = $_POST['status'];
            //if(isset($_POST['rejectionReason'])){
                $vldata['reason_for_sample_rejection'] = $_POST['rejectionReason'];
            //}
        }
        $db=$db->where('vl_sample_id',$_POST['vlSampleId']);
        $db->update($tableName,$vldata);
        $_SESSION['alertMsg']="VL result updated successfully";
         //Add event log
        $eventType = 'update-vl-result-drc';
        $action = ucwords($_SESSION['userName']).' updated a result data with the patient code '.$_POST['dubPatientArtNo'];
        $resource = 'vl-result-drc';

        $general->activityLog($eventType,$action,$resource);
        
        //  $data=array(
        // 'event_type'=>$eventType,
        // 'action'=>$action,
        // 'resource'=>$resource,
        // 'date_time'=>$general->getDateTime()
        // );
        // $db->insert($tableName1,$data);
        //Add update result log
        $data=array(
        'user_id'=>$_SESSION['userId'],
        'vl_sample_id'=>$_POST['vlSampleId'],
        'updated_on'=>$general->getDateTime()
        );
        $db->insert($tableName2,$data);
        header("location:vlTestResult.php");

} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
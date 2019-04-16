<?php
session_start();
ob_start();
include('../includes/MysqliDb.php');
include_once(APPLICATION_PATH.'/General.php');
$general=new General($db);
$tableName="vl_request_form";
$tableName2="log_result_updates";
try {
    $testingPlatform = '';
    if(isset($_POST['testingPlatform']) && trim($_POST['testingPlatform'])!=''){
        $platForm = explode("##",$_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
    }
    if(isset($_POST['sampleReceivedOn']) && trim($_POST['sampleReceivedOn'])!=""){
        $sampleReceivedDateLab = explode(" ",$_POST['sampleReceivedOn']);
        $_POST['sampleReceivedOn']=$general->dateFormat($sampleReceivedDateLab[0])." ".$sampleReceivedDateLab[1];  
    }else{
        $_POST['sampleReceivedOn'] = NULL;
    }
    if(isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab'])!=""){
        $sampleTestingDateAtLab = explode(" ",$_POST['sampleTestingDateAtLab']);
        $_POST['sampleTestingDateAtLab']=$general->dateFormat($sampleTestingDateAtLab[0])." ".$sampleTestingDateAtLab[1];  
    }else{
        $_POST['sampleTestingDateAtLab'] = NULL;
    }
    if(isset($_POST['resultDispatchedOn']) && trim($_POST['resultDispatchedOn'])!=""){
        $resultDispatchedOn = explode(" ",$_POST['resultDispatchedOn']);
        $_POST['resultDispatchedOn']=$general->dateFormat($resultDispatchedOn[0])." ".$resultDispatchedOn[1];  
    }else{
        $_POST['resultDispatchedOn'] = NULL;
    }
    
    if(isset($_POST['newRejectionReason']) && trim($_POST['newRejectionReason'])!=""){
         $rejectionReasonQuery ="SELECT rejection_reason_id FROM r_sample_rejection_reasons where rejection_reason_name='".$_POST['newRejectionReason']."' OR rejection_reason_name='".strtolower($_POST['newRejectionReason'])."' OR rejection_reason_name='".ucfirst(strtolower($_POST['newRejectionReason']))."'";
         $rejectionResult = $db->rawQuery($rejectionReasonQuery);
        if(!isset($rejectionResult[0]['rejection_reason_id'])){
            $data=array(
            'rejection_reason_name'=>$_POST['newRejectionReason'],
            'rejection_type'=>'general',
            'rejection_reason_status'=>'active'
            );
            $id=$db->insert('r_sample_rejection_reasons',$data);
            $_POST['rejectionReason'] = $id;
        }else{
            $_POST['rejectionReason'] = $rejectionResult[0]['rejection_reason_id'];
        }
    }
    
    $isRejection = false;
    if(isset($_POST['noResult']) && $_POST['noResult'] =='yes'){
        $isRejection = true;
        $_POST['vlResult'] = '';
        $_POST['vlLog'] = '';
    }
    
    if(isset($_POST['tnd']) && $_POST['tnd'] =='yes' && $isRejection == false){
        $_POST['vlResult'] = 'Target Not Detected';
        $_POST['vlLog'] = '';
    }
    if(isset($_POST['ldl']) && $_POST['ldl'] =='yes' && $isRejection == false){
        $_POST['vlResult'] = 'Low Detection Level';
        $_POST['vlLog'] = '';
    }
    if(isset($_POST['hdl']) && $_POST['hdl'] =='yes' && $isRejection == false){
        $_POST['vlResult'] = 'High Detection Level';
        $_POST['vlLog'] = '';
    }
    
    if(isset($_POST['vlResult']) && trim($_POST['vlResult']) != ''){
        $_POST['result'] = $_POST['vlResult'];
    }else if($_POST['vlLog']!=''){
        $_POST['result'] = $_POST['vlLog'];
    }
    $reasonForChanges = '';
    $allChange = '';
    if(isset($_POST['reasonForResultChangesHistory']) && $_POST['reasonForResultChangesHistory'] !=''){
        $allChange = $_POST['reasonForResultChangesHistory'];
    }
    if(isset($_POST['reasonForResultChanges']) && trim($_POST['reasonForResultChanges'])!=''){
        $reasonForChanges = $_SESSION['userName'].'##'.$_POST['reasonForResultChanges'].'##'.$general->getDateTime();
    }
    if(trim($allChange)!= '' && trim($reasonForChanges)!= ''){
       $allChange = $reasonForChanges.'vlsm'.$allChange;
    }else if(trim($reasonForChanges)!= ''){
       $allChange =  $reasonForChanges;
    }
    $vldata=array(
          'lab_id'=>(isset($_POST['labId']) && $_POST['labId']!='') ? $_POST['labId'] :  NULL,
          'lab_technician'=>(isset($_POST['labTechnician']) && $_POST['labTechnician']!='')? $_POST['labTechnician'] :  NULL,
          'vl_test_platform'=>$testingPlatform,
          'test_methods'=>(isset($_POST['testMethods']) && $_POST['testMethods']!='') ? $_POST['testMethods'] :  NULL,
          'sample_received_at_vl_lab_datetime'=>$_POST['sampleReceivedOn'],
          'sample_tested_datetime'=>$_POST['sampleTestingDateAtLab'],
          'result_dispatched_datetime'=>$_POST['resultDispatchedOn'],
          'vl_focal_person'=>(isset($_POST['vlFocalPerson']) && $_POST['vlFocalPerson']!='') ? $_POST['vlFocalPerson'] :  NULL,
          'is_sample_rejected'=>(isset($_POST['noResult']) && $_POST['noResult']!='') ? $_POST['noResult'] :  NULL,
          'reason_for_sample_rejection'=>(isset($_POST['rejectionReason']) && $_POST['rejectionReason']!='') ? $_POST['rejectionReason'] :  NULL,
          'result_value_absolute'=>(isset($_POST['vlResult']) && $_POST['vlResult']!='' && ($_POST['vlResult']!='Target Not Detected' && $_POST['vlResult']!='Low Detection Level' && $_POST['vlResult']!='High Detection Level')) ? $_POST['vlResult'] :  NULL,
          'result_value_absolute_decimal'=>(isset($_POST['vlResult']) && $_POST['vlResult']!='' && ($_POST['vlResult']!='Target Not Detected' && $_POST['vlResult']!='Low Detection Level' && $_POST['vlResult']!='High Detection Level')) ? number_format((float)$_POST['vlResult'], 2, '.', '') :  NULL,
          'result'=>(isset($_POST['result']) && $_POST['result']!='') ? $_POST['result'] :  NULL,
          'result_value_log'=>(isset($_POST['vlLog']) && $_POST['vlLog']!='')? $_POST['vlLog'] :  NULL,
          'result_approved_by'=>(isset($_POST['approvedBy']) && $_POST['approvedBy']!='') ? $_POST['approvedBy'] :  NULL,
          'approver_comments'=>(isset($_POST['labComments']) && trim($_POST['labComments'])!='') ? trim($_POST['labComments']) :  NULL,
          'result_status'=>(isset($_POST['status']) && $_POST['status']!='') ? $_POST['status'] :  NULL,
          'reason_for_vl_result_changes'=>$allChange,
          'last_modified_by'=>$_SESSION['userId'],
          'last_modified_datetime'=>$general->getDateTime(),
          'data_sync'=>0
        );
        $db=$db->where('vl_sample_id',$_POST['vlSampleId']);
        $id=$db->update($tableName,$vldata);
        if($id>0){
             $_SESSION['alertMsg']="VL request updated successfully";
             //Log result updates
             $data=array(
              'user_id'=>$_SESSION['userId'],
              'vl_sample_id'=>$_POST['vlSampleId'],
              'updated_on'=>$general->getDateTime()
            );
            $db->insert($tableName2,$data);
            header("location:vlTestResult.php");
        }else{
             $_SESSION['alertMsg']="Please try again later";
        }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
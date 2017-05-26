<?php
session_start();
ob_start();
include('../includes/MysqliDb.php');
include('../General.php');
$general=new Deforay_Commons_General();
$tableName="vl_request_form";
$tableName1="activity_log";
$vlTestReasonTable="r_vl_test_reasons";
try {
    if(isset($_POST['dob']) && trim($_POST['dob'])!=""){
        $_POST['dob']=$general->dateFormat($_POST['dob']);  
    }else{
        $_POST['dob'] = NULL;
    }
    
    if(isset($_POST['collectionDate']) && trim($_POST['collectionDate'])!=""){
        $sampleDate = explode(" ",$_POST['collectionDate']);
        $_POST['collectionDate']=$general->dateFormat($sampleDate[0])." ".$sampleDate[1];
    }else{
        $_POST['collectionDate'] = NULL;
    }
    if(isset($_POST['failedTestDate']) && trim($_POST['failedTestDate'])!=""){
        $failedtestDate = explode(" ",$_POST['failedTestDate']);
        $_POST['failedTestDate']=$general->dateFormat($failedtestDate[0])." ".$failedtestDate[1];
    }else{
        $_POST['failedTestDate'] = NULL;
    }
    
    if(isset($_POST['regStartDate']) && trim($_POST['regStartDate'])!=""){
        $_POST['regStartDate']=$general->dateFormat($_POST['regStartDate']);  
    }else{
        $_POST['regStartDate'] = NULL;
    }
    
    if(isset($_POST['receivedDate']) && trim($_POST['receivedDate'])!=""){
        $sampleReceivedDate = explode(" ",$_POST['receivedDate']);
        $_POST['receivedDate']=$general->dateFormat($sampleReceivedDate[0])." ".$sampleReceivedDate[1];
    }else{
        $_POST['receivedDate'] = NULL;
    }
    if(isset($_POST['testDate']) && trim($_POST['testDate'])!=""){
        $sampletestDate = explode(" ",$_POST['testDate']);
        $_POST['testDate']=$general->dateFormat($sampletestDate[0])." ".$sampletestDate[1];
    }else{
        $_POST['testDate'] = NULL;
    }
    if(isset($_POST['cdDate']) && trim($_POST['cdDate'])!=""){
        $_POST['cdDate']=$general->dateFormat($_POST['cdDate']);
    }else{
        $_POST['cdDate'] = NULL;
    }
    if(isset($_POST['qcDate']) && trim($_POST['qcDate'])!=""){
        $_POST['qcDate']=$general->dateFormat($_POST['qcDate']);
    }else{
        $_POST['qcDate'] = NULL;
    }
    if(isset($_POST['reportDate']) && trim($_POST['reportDate'])!=""){
        $_POST['reportDate']=$general->dateFormat($_POST['reportDate']);
    }else{
        $_POST['reportDate'] = NULL;
    }
    if(isset($_POST['clinicDate']) && trim($_POST['clinicDate'])!=""){
        $_POST['clinicDate']=$general->dateFormat($_POST['clinicDate']);
    }else{
        $_POST['clinicDate'] = NULL;
    }
    
    if($_POST['testingTech']!=''){
        $platForm = explode("##",$_POST['testingTech']);
        $_POST['testingTech'] = $platForm[0];
    }
    if($_POST['failedTestingTech']!=''){
        $platForm = explode("##",$_POST['failedTestingTech']);
        $_POST['failedTestingTech'] = $platForm[0];
    }
    if(isset($_POST['newArtRegimen']) && trim($_POST['newArtRegimen'])!=""){
          $data=array(
            'art_code'=>$_POST['newArtRegimen'],
            'parent_art'=>'5',
            'nation_identifier'=>'png'
          );
          $result=$db->insert('r_art_code_details',$data);
          $_POST['currentRegimen'] = $_POST['newArtRegimen'];
    }
    $vldata=array(
        'sample_code'=>(isset($_POST['sampleCode']) && $_POST['sampleCode']!='' ? $_POST['sampleCode'] :  NULL),
        'serial_no'=>(isset($_POST['sampleCode']) && $_POST['sampleCode']!='' ? $_POST['sampleCode'] :  NULL),
        'ward'=>(isset($_POST['wardData']) && $_POST['wardData']!='' ? $_POST['wardData'] :  NULL),
        'facility_id'=>(isset($_POST['clinicName']) && $_POST['clinicName']!='' ? $_POST['clinicName'] :  NULL),
        'lab_contact_person'=>(isset($_POST['officerName']) && $_POST['officerName']!='' ? $_POST['officerName'] :  NULL),
        'lab_phone_number'=>(isset($_POST['telephone']) && $_POST['telephone']!='' ? $_POST['telephone'] :  NULL),
        'patient_first_name'=>(isset($_POST['patientFname']) && $_POST['patientFname']!='' ? $_POST['patientFname'] :  NULL),
        'patient_last_name'=>(isset($_POST['surName']) && $_POST['surName']!='' ? $_POST['surName'] :  NULL),
        'patient_gender'=>(isset($_POST['gender']) && $_POST['gender']!='' ? $_POST['gender'] :  NULL),
        'patient_dob'=>$_POST['dob'],
        'current_regimen'=>(isset($_POST['currentRegimen']) && $_POST['currentRegimen']!='' ? $_POST['currentRegimen'] :  NULL),
        'date_of_initiation_of_current_regimen'=>$_POST['regStartDate'],
        'art_cd_cells'=>(isset($_POST['cdCells']) && $_POST['cdCells']!='' ? $_POST['cdCells'] :  NULL),
        'art_cd_date'=>$_POST['cdDate'],
        'who_clinical_stage'=>(isset($_POST['clinicalStage']) && $_POST['clinicalStage']!='' ? $_POST['clinicalStage'] :  NULL),
        'reason_testing_png'=>(isset($_POST['reasonForTest']) && $_POST['reasonForTest']!='' ? $_POST['reasonForTest'] :  NULL),
        'sample_to_transport'=>(isset($_POST['typeOfSample']) && $_POST['typeOfSample']!='' ? $_POST['typeOfSample'] :  NULL),
        'whole_blood_ml'=>(isset($_POST['wholeBloodOne']) && $_POST['wholeBloodOne']!='' ? $_POST['wholeBloodOne'] :  NULL),
        'whole_blood_vial'=>(isset($_POST['wholeBloodTwo']) && $_POST['wholeBloodTwo']!='' ? $_POST['wholeBloodTwo'] :  NULL),
        'plasma_ml'=>(isset($_POST['plasmaOne']) && $_POST['plasmaOne']!='' ? $_POST['plasmaOne'] :  NULL),
        'plasma_vial'=>(isset($_POST['plasmaTwo']) && $_POST['plasmaTwo']!='' ? $_POST['plasmaTwo'] :  NULL),
        'plasma_process_time'=>(isset($_POST['processTime']) && $_POST['processTime']!='' ? $_POST['processTime'] :  NULL),
        'plasma_process_tech'=>(isset($_POST['processTech']) && $_POST['processTech']!='' ? $_POST['processTech'] :  NULL),
        'is_sample_rejected'=>(isset($_POST['sampleQuality']) && $_POST['sampleQuality']!='' ? $_POST['sampleQuality'] :  NULL),
        'reason_for_sample_rejection'=>(isset($_POST['rejectionReason']) && $_POST['rejectionReason']!='' ? $_POST['rejectionReason'] :  NULL),
        'batch_quality'=>(isset($_POST['batchQuality']) && $_POST['batchQuality']!='' ? $_POST['batchQuality'] :  NULL),
        'sample_test_quality'=>(isset($_POST['testQuality']) && $_POST['testQuality']!='' ? $_POST['testQuality'] :  NULL),
        'sample_batch_id'=>(isset($_POST['batchNo']) && $_POST['batchNo']!='' ? $_POST['batchNo'] :  NULL),
        'failed_test_date'=>$_POST['failedTestDate'],
        'failed_test_tech'=>(isset($_POST['failedTestingTech']) && $_POST['failedTestingTech']!='' ? $_POST['failedTestingTech'] :  NULL),
        'failed_vl_result'=>(isset($_POST['failedvlResult']) && $_POST['failedvlResult']!='' ? $_POST['failedvlResult'] :  NULL),
        'failed_batch_quality'=>(isset($_POST['failedbatchQuality']) && $_POST['failedbatchQuality']!='' ? $_POST['failedbatchQuality'] :  NULL),
        'failed_sample_test_quality'=>(isset($_POST['failedtestQuality']) && $_POST['failedtestQuality']!='' ? $_POST['failedtestQuality'] :  NULL),
        'failed_batch_id'=>(isset($_POST['failedbatchNo']) && $_POST['failedbatchNo']!='' ? $_POST['failedbatchNo'] :  NULL),
        'sample_collection_date'=>$_POST['collectionDate'],
        'sample_collected_by'=>(isset($_POST['collectedBy']) && $_POST['collectedBy']!='' ? $_POST['collectedBy'] :  NULL),
        'lab_id'=>(isset($_POST['laboratoryId']) && $_POST['laboratoryId']!='' ? $_POST['laboratoryId'] :  NULL),
        'sample_type'=>(isset($_POST['sampleType']) && $_POST['sampleType']!='' ? $_POST['sampleType'] :  NULL),
        'sample_received_at_vl_lab_datetime'=>$_POST['receivedDate'],
        'tech_name_png'=>(isset($_POST['techName']) && $_POST['techName']!='' ? $_POST['techName'] :  NULL),
        'sample_tested_datetime'=>(isset($_POST['testDate']) && $_POST['testDate']!='' ? $_POST['testDate'] :  NULL),
        'last_viral_load_result'=>(isset($_POST['vlResult']) && $_POST['vlResult']!='' ? $_POST['vlResult'] :  NULL),
        'vl_test_platform'=>(isset($_POST['testingTech']) && $_POST['testingTech']!='' ? $_POST['testingTech'] :  NULL),
        'result'=>(isset($_POST['finalViralResult']) && $_POST['finalViralResult']!='' ? $_POST['finalViralResult'] :  NULL),
        'qc_tech_name'=>(isset($_POST['qcTechName']) && $_POST['qcTechName']!='' ? $_POST['qcTechName'] :  NULL),
        'qc_tech_sign'=>(isset($_POST['qcTechSign']) && $_POST['qcTechSign']!='' ? $_POST['qcTechSign'] :  NULL),
        'qc_date'=>$_POST['qcDate'],
        'clinic_date'=>$_POST['clinicDate'],
        'report_date'=>$_POST['reportDate'],
        'last_modified_datetime'=>$general->getDateTime(),
        );
    $db=$db->where('vl_sample_id',$_POST['vlSampleId']);
    $id=$db->update($tableName,$vldata);
    
        if($id>0){
          $_SESSION['alertMsg']="VL request updated successfully";
          //Add event log
          $eventType = 'update-vl-request-zam';
          $action = ucwords($_SESSION['userName']).' updated a request data with the sample code '.$_POST['sampleCode'];
          $resource = 'vl-request-zam';
          $data=array(
          'event_type'=>$eventType,
          'action'=>$action,
          'resource'=>$resource,
          'date_time'=>$general->getDateTime()
          );
          $db->insert($tableName1,$data);
             header("location:vlRequest.php");
        }else{
            $_SESSION['alertMsg']="Please try again later";
        }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
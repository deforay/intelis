<?php
ob_start();
session_start();
include('../includes/MysqliDb.php');
//include('../header.php');
include('../General.php');
$general=new Deforay_Commons_General();
$tableName1="batch_details";
$tableName2="vl_request_form";
try {
        if(isset($_POST['batchCode']) && trim($_POST['batchCode'])!=""){
                $data=array(
                            'machine'=>$_POST['machine'],
                            'batch_code'=>$_POST['batchCode'],
                            'batch_code_key'=>$_POST['batchCodeKey'],
                            'request_created_datetime'=>$general->getDateTime()
                            );
                $db->insert($tableName1,$data);
                $lastId = $db->getInsertId();
                if($lastId > 0){  
                    for($j=0;$j<count($_POST['sampleCode']);$j++){
                        $vlSampleId = $_POST['sampleCode'][$j];
                        $value = array('sample_batch_id'=>$lastId);
                        $db=$db->where('vl_sample_id',$vlSampleId);
                        $db->update($tableName2,$value); 
                    }
                    header("location:addBatchControlsPosition.php?id=".base64_encode($lastId));
                }
        }else{
                header("location:batchcode.php");
        }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
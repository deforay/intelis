<?php
ob_start();
session_start();
include('../includes/MysqliDb.php');
//include('../header.php');
$tableName1="batch_details";
$tableName2="vl_request_form";
try {
        if(isset($_POST['batchCode']) && trim($_POST['batchCode'])!=""){
                $id = intval($_POST['batchId']);
                $data=array(
                            'batch_code'=>$_POST['batchCode'],
                            'machine'=>$_POST['machine']
                        );
                $db=$db->where('batch_id',$id);
                $db->update($tableName1,$data);
                if($id > 0){
                    $value = array('sample_batch_id'=>NULL);
                    $db=$db->where('sample_batch_id',$id);
                    $db->update($tableName2,$value);
                    $xplodResultSample = array();
                    if(isset($_POST['resultSample']) && trim($_POST['resultSample'])!=""){
                        $xplodResultSample = explode(",",$_POST['resultSample']);
                    }
                    $sample = array();
                    //Mergeing disabled samples into existing samples
                    if(isset($_POST['sampleCode']) && count($_POST['sampleCode'])>0){
                        if(count($xplodResultSample)>0){
                          $sample = array_unique(array_merge($_POST['sampleCode'],$xplodResultSample));
                        }else{
                           $sample = $_POST['sampleCode'];     
                        }
                    }elseif(count($xplodResultSample)>0){
                        $sample = $xplodResultSample;
                    }
                    
                    for($j=0;$j<count($sample);$j++){
                        $value = array('sample_batch_id'=>$id);
                        $db=$db->where('vl_sample_id',$sample[$j]);
                        $db->update($tableName2,$value);
                    }
                    //Update batch controls position, If samples has changed
                     $displaySampleOrderArray = array();
                     $batchQuery="SELECT * from batch_details as b_d INNER JOIN import_config as i_c ON i_c.config_id=b_d.machine where batch_id=$id";
                     $batchInfo=$db->query($batchQuery);
                     if(isset($batchInfo) && count($batchInfo)>0){
                        if(isset($batchInfo[0]['label_order']) && trim($batchInfo[0]['label_order'])!= ''){
                                //Get display sample only
                                $samplesQuery="SELECT vl_sample_id,sample_code from vl_request_form where sample_batch_id=$id";
                                $samplesInfo=$db->query($samplesQuery);
                                foreach($samplesInfo as $sample){
                                   $displaySampleOrderArray[] = $sample['vl_sample_id'];
                                }
                                //Set label order
                                $jsonToArray = json_decode($batchInfo[0]['label_order'],true);
                                $displaySampleArray = array();
                                for($j=0;$j<count($jsonToArray);$j++){
                                       $xplodJsonToArray = explode("_",$jsonToArray[$j]);
                                        if(count($xplodJsonToArray)>1 && $xplodJsonToArray[0] == "s"){
                                                if(in_array($xplodJsonToArray[1],$displaySampleOrderArray)){
                                                   $displayOrder[] = $jsonToArray[$j];
                                                   $displaySampleArray[] = $xplodJsonToArray[1];
                                                }
                                        }else{
                                                $displayOrder[] = $jsonToArray[$j];
                                        } 
                                }
                               $remainSampleNewArray = array_values(array_diff($displaySampleOrderArray,$displaySampleArray));
                               //For new samples
                                for($ns=0;$ns<count($remainSampleNewArray);$ns++){
                                    $displayOrder[] = 's_'.$remainSampleNewArray[$ns];
                                }
                                $orderArray = array();
                                for($o=0;$o<count($displayOrder);$o++){
                                   $orderArray[$o] = $displayOrder[$o];
                                }
                                $labelOrder = json_encode($orderArray,JSON_FORCE_OBJECT);
                                //Update label order
                                $data=array('label_order'=>$labelOrder);
                                $db=$db->where('batch_id',$id);
                                $db->update($tableName1,$data);
                        }
                     }
                    $_SESSION['alertMsg']="Batch code updated successfully";
                }
        }
        header("location:batchcode.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
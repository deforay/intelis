<?php
ob_start();
#require_once('../startup.php');  


$general=new \Vlsm\Models\General();
$tableName="vl_request_form";
try {
    $lock = $general->getGlobalConfig('lock_approved_vl_samples');
    $sQuery = "SELECT vl.vl_sample_id FROM temp_sample_import as tsr JOIN vl_request_form as vl ON vl.sample_code=tsr.sample_code JOIN r_vl_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection where vl.result_status=4";

    $sResult = $db->query($sQuery);

    if(count($sResult)>0)
    {
        foreach($sResult as $sample)
        {
            $status=array(
                'result_status'=>7,
                'reason_for_sample_rejection'=>NULL,
                'data_sync'=>0
            );
            if ($lock == 'yes') {
                $status['locked'] = 'yes';
            }
            $db=$db->where('vl_sample_id',$sample['vl_sample_id']);
            $db->update('vl_request_form',$status);
        }
    }
    $status=array(
        'result_status'=>7,
    );
    
    //update all temp sample as accepted
    $result = $db->update('temp_sample_import',$status);
}
catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;
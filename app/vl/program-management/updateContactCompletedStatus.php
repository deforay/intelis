<?php

use App\Services\CommonService;


  


$general=new CommonService();
$tableName="form_vl";
try {
    $id = $_POST['id'];
        $status=array(
            'contact_complete_status'=>$_POST['value']
        );
        $db=$db->where('vl_sample_id',$id);
        $db->update($tableName,$status);
        $result = $id;
}
catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;
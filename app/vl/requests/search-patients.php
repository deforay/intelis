<?php


use App\Services\CommonService;

$general=new CommonService();
$artNo=$_POST['artPatientNo'];

$count = 0;
$pQuery="SELECT * FROM form_vl where (patient_art_no like '%".$artNo."%' OR patient_first_name like '%".$artNo."%' OR patient_middle_name like '%".$artNo."%' OR patient_last_name like '%".$artNo."%') ORDER BY sample_tested_datetime DESC, sample_collection_date DESC LIMIT 25";
$pResult = $db->rawQuery($pQuery);
$count = count($pResult);
echo $count;

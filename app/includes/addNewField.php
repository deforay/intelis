<?php

use App\Models\General;
use App\Utilities\DateUtils;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$general = new General();
$tableName = "rejection_type";
$value = trim($_POST['value']);
$data = 0;
if ($value != '') {
    $rej = "SELECT * from rejection_type where rejection_type = '".$value."' ";
    $rejInfo = $db->query($rej);
    
    if(count($rejInfo)==0){
        $data = array(
			'rejection_type' => $value,
			'updated_datetime' => DateUtils::getCurrentDateTime(),
		);

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();
    }
}

if ($data > 0) {
    $data = '1';
} else { 
    $data = '0';
}
echo $data;

<?php

use App\Models\General;
use App\Utilities\DateUtils;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

  

$general = new General();

$tableName = "r_vl_art_regimen";
$primaryKey = "art_id";
// echo "<pre>";print_r($_POST);die;
try {
	if (isset($_POST['artCode']) && trim($_POST['artCode']) != "") {


		$data = array(
			'art_code'          => $_POST['artCode'],
			'parent_art'        => (isset($_POST['parentArtCode']) && $_POST['parentArtCode'] != "")?$_POST['parentArtCode']:0,
			'headings'          => $_POST['category'],
			'art_status'        => $_POST['artStatus'],
			'updated_datetime'  => DateUtils::getCurrentDateTime()
		);
		if(isset($_POST['artCodeId']) && $_POST['artCodeId'] != ""){
			$db = $db->where($primaryKey, base64_decode($_POST['artCodeId']));
        	$lastId = $db->update($tableName, $data);
		} else{
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
        if($lastId > 0){
            $_SESSION['alertMsg'] = _("Art Code details saved successfully");
            $general->activityLog('Add art code details', $_SESSION['userName'] . ' added new art code for ' . $_POST['artCode'], 'vl-reference');
        }
	}
	header("location:vl-art-code-details.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}

<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

  

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

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
			'updated_datetime'  => DateUtility::getCurrentDateTime()
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
	header("Location:vl-art-code-details.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}

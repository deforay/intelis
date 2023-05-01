<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

  


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);



$tableName = "r_tb_sample_type";
$sampleId = base64_decode($_POST['sampleId']);

try {
	if (isset($_POST['sampleName']) && trim($_POST['sampleName']) != "") {


		$data = array(
			'sample_name' => $_POST['sampleName'],
			'status' => $_POST['sampleStatus'],
			'updated_datetime' => DateUtility::getCurrentDateTime(),
		);

        $db = $db->where('sample_id', $sampleId);
        $db->update($tableName, $data);

		$_SESSION['alertMsg'] = "Sample details updated successfully";
		$general->activityLog('update-sample-type', $_SESSION['userName'] . ' updated new reference sample type' . $_POST['sampleName'], 'reference-tb-sample type');
	}
	header("Location:tb-sample-type.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}

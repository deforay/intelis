<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}



/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "r_funding_sources";
$primaryKey = "funding_source_id";

try {
	if (isset($_POST['fundingSrcName']) && trim((string) $_POST['fundingSrcName']) != "") {

		$data = array(
			'funding_source_name' 	=> $_POST['fundingSrcName'],
			'funding_source_status' => $_POST['fundingStatus'],
			'updated_datetime'		=> DateUtility::getCurrentDateTime()
		);
		if (isset($_POST['fundingId']) && $_POST['fundingId'] != "") {
			$db = $db->where($primaryKey, base64_decode((string) $_POST['fundingId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Funding Source saved successfully");
			$general->activityLog('Funding Source', $_SESSION['userName'] . ' added new Funding Source for ' . $_POST['fundingSrcName'], 'common-reference');
		}
	}
	header("Location:funding-sources.php");
} catch (Exception $exc) {
	throw new SystemException($exc->getMessage(), 500);
}

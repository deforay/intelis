<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "r_implementation_partners";
$primaryKey = "i_partner_id";

try {
	if (isset($_POST['partnerName']) && trim((string) $_POST['partnerName']) != "") {

		$data = array(
			'i_partner_name' 	=> $_POST['partnerName'],
			'i_partner_status' 	=> $_POST['partnerStatus'],
			'updated_datetime'	=> DateUtility::getCurrentDateTime()
		);
		if (isset($_POST['partnerId']) && $_POST['partnerId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['partnerId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Implementation Partners saved successfully");
			$general->activityLog('Implementation Partners', $_SESSION['userName'] . ' added new Implementation Partner for ' . $_POST['partnerName'], 'common-reference');
		}
	}
	header("Location:implementation-partners.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}

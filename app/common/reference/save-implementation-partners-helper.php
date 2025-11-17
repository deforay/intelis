<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "r_implementation_partners";
$primaryKey = "i_partner_id";

try {
	if (isset($_POST['partnerName']) && trim((string) $_POST['partnerName']) !== "") {

		$data = [
			'i_partner_name' => $_POST['partnerName'],
			'i_partner_status' => $_POST['partnerStatus'],
			'updated_datetime' => DateUtility::getCurrentDateTime()
		];
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
} catch (Throwable $e) {
	throw new SystemException($e->getMessage(), 500, $e);
}
_invalidateFileCacheByTags(['r_implementation_partners']);
header("Location:implementation-partners.php");

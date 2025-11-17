<?php

use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "r_funding_sources";
$primaryKey = "funding_source_id";

try {
	if (isset($_POST['fundingSrcName']) && trim((string) $_POST['fundingSrcName']) !== "") {

		$data = [
			'funding_source_name' => $_POST['fundingSrcName'],
			'funding_source_status' => $_POST['fundingStatus'],
			'updated_datetime' => DateUtility::getCurrentDateTime()
		];
		if (isset($_POST['fundingId']) && $_POST['fundingId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['fundingId']));
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
} catch (Throwable $e) {
	throw new SystemException($e->getMessage(), 500, $e);
}
_invalidateFileCacheByTags(['r_funding_sources']);
header("Location:funding-sources.php");

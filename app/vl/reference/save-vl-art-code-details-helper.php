<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\SecurityService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "r_vl_art_regimen";
$primaryKey = "art_id";
// echo "<pre>";print_r($_POST);die;
try {
	if (isset($_POST['artCode']) && trim((string) $_POST['artCode']) !== "") {


		$data = ['art_code'          => $_POST['artCode'], 'parent_art'        => (isset($_POST['parentArtCode']) && $_POST['parentArtCode'] != "") ? $_POST['parentArtCode'] : 0, 'headings'          => $_POST['category'], 'art_status'        => $_POST['artStatus'], 'updated_datetime'  => DateUtility::getCurrentDateTime()];
		if (isset($_POST['artCodeId']) && $_POST['artCodeId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['artCodeId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Art Code details saved successfully");
			$general->activityLog('Add art code details', $_SESSION['userName'] . ' added new art code for ' . $_POST['artCode'], 'vl-reference');
		}
	}
	MiscUtility::redirect("/vl/reference/vl-art-code-details.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}

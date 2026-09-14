<?php

// app/eid/requests/addSamplesFromManifest.php

use const COUNTRY\DRC;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = $general ?? ContainerRegistry::get(CommonService::class);
$formId = (int) $general->getGlobalConfig('vl_form');

$columns = [
	"Sample Collection Date",
	"Batch Code",
	"Facility Name",
	"Child's ID",
];
if ($formId != DRC) {
	$columns[] = "Child's Name";
}
$columns[] = "Mother's ID";
if ($formId != DRC) {
	$columns[] = "Mother's Name";
}
$columns = array_merge($columns, [
	"Province/State",
	"District/County",
	"Result",
	"Last Modified On",
	"Status",
]);

$manifestPage = [
	'module'     => 'eid',
	'testType'   => 'eid',
	'title'      => _translate("EID | Add Samples from Manifest"),
	'breadcrumb' => _translate("EID Test Request"),
	'columns'    => $columns,
];

require APPLICATION_PATH . '/specimen-referral-manifest/_add-samples-from-manifest-body.php';

<?php

// app/vl/requests/addSamplesFromManifest.php

$manifestPage = [
	'module'     => 'vl',
	'testType'   => 'vl',
	'title'      => _translate("VL | Add Samples from Manifest"),
	'breadcrumb' => _translate("Test Request"),
	'columns'    => [
		"Sample Collection Date",
		"Batch Code",
		"Unique ART No",
		"Patient's Name",
		"Facility Name",
		"Province/State",
		"District/County",
		"Sample Type",
		"Result",
		"Last Modified Date",
		"Status",
	],
];

require APPLICATION_PATH . '/specimen-referral-manifest/_add-samples-from-manifest-body.php';

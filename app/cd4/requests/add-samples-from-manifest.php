<?php

// app/cd4/requests/add-samples-from-manifest.php

$manifestPage = [
	'module'     => 'cd4',
	'testType'   => 'cd4',
	'title'      => _translate("CD4 | Add Samples from Manifest"),
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

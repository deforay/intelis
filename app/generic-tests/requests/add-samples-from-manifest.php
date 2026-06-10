<?php

// app/generic-tests/requests/add-samples-from-manifest.php

$manifestPage = [
	'module'     => 'generic-tests',
	'testType'   => 'generic-tests',
	'title'      => _translate("Add Samples from Manifest"),
	'breadcrumb' => _translate("Test Request"),
	'columns'    => [
		"Sample Collection Date",
		"Batch Code",
		"Patient ID",
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

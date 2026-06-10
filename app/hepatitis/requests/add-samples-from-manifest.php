<?php

// app/hepatitis/requests/add-samples-from-manifest.php

$manifestPage = [
	'module'        => 'hepatitis',
	'testType'      => 'hepatitis',
	'title'         => _translate("Hepatitis | Add Samples from Manifest"),
	'breadcrumb'    => _translate("Hepatitis Test Request"),
	// Hepatitis ships a hyphenated grid helper name (unlike the other modules).
	'gridHelperUrl' => '/hepatitis/requests/get-manifest-in-grid-helper.php',
	'columns'       => [
		"Sample Collection Date",
		"Batch Code",
		"Facility Name",
		"Patient ID",
		"Patient Name",
		"Province/State",
		"District/County",
		"HCV VL Result",
		"HBV VL Result",
		"Last Modified On",
		"Status",
	],
];

require APPLICATION_PATH . '/specimen-referral-manifest/_add-samples-from-manifest-body.php';

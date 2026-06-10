<?php

// app/eid/requests/addSamplesFromManifest.php

$manifestPage = [
	'module'     => 'eid',
	'testType'   => 'eid',
	'title'      => _translate("EID | Add Samples from Manifest"),
	'breadcrumb' => _translate("EID Test Request"),
	'columns'    => [
		"Sample Collection Date",
		"Batch Code",
		"Facility Name",
		"Child's ID",
		"Child's Name",
		"Mother's ID",
		"Mother's Name",
		"Province/State",
		"District/County",
		"Result",
		"Last Modified On",
		"Status",
	],
];

require APPLICATION_PATH . '/specimen-referral-manifest/_add-samples-from-manifest-body.php';

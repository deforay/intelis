<?php

// app/tb/requests/addSamplesFromManifest.php

$manifestPage = [
	'module'     => 'tb',
	'testType'   => 'tb',
	'title'      => _translate("TB | Add Samples from Manifest"),
	'breadcrumb' => _translate("TB Test Request"),
	'columns'    => [
		"Sample Collection Date",
		"Batch Code",
		"Facility Name",
		"Patient ID",
		"Patient Name",
		"Province/State",
		"District/County",
		"Result",
		"Last Modified On",
		"Status",
	],
];

require APPLICATION_PATH . '/specimen-referral-manifest/_add-samples-from-manifest-body.php';

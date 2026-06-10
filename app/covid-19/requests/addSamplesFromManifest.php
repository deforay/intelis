<?php

// app/covid-19/requests/addSamplesFromManifest.php

$manifestPage = [
	'module'     => 'covid-19',
	'testType'   => 'covid19',
	'title'      => _translate("Covid-19 | Add Samples from Manifest"),
	'breadcrumb' => _translate("Covid-19 Test Request"),
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

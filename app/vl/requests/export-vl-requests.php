<?php

// vl/requests/export-vl-requests.php
use App\Services\VlExportService;
use App\Utilities\ExportJobUtility;
use App\Registries\ContainerRegistry;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, 'vlRequestQuery')) {
	return;
}

// A background job has no request to time out, and the worker sets its own
// memory limit; a direct request keeps these.
if (!ExportJobUtility::inBackground()) {
	ini_set('memory_limit', '512M');
	set_time_limit(300);
	ini_set('max_execution_time', 300);
}

/** @var VlExportService $vlExportService */
$vlExportService = ContainerRegistry::get(VlExportService::class);

// The filters the export was made with, written above the headings.
$filters = '';
foreach ($_POST as $name => $value) {
	if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
		$filters .= str_replace("_", " ", $name) . " : " . $value . "  ";
	}
}

$filename = $vlExportService->export(
	(string) ($_SESSION['vlRequestQuery'] ?? ''),
	'InteLIS-VL-REQUESTS',
	($_POST['patientInfo'] ?? '') === 'yes',
	[[html_entity_decode($filters)], ['']],
	($_POST['withAlphaNum'] ?? '') === 'yes'
);

echo _downloadToken($filename);

<?php

// cd4/requests/export-cd4-requests.php
use App\Services\Cd4ExportService;
use App\Utilities\ExportJobUtility;
use App\Registries\ContainerRegistry;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, 'cd4RequestQuery')) {
	return;
}

// A background job has no request to time out, and the worker sets its own
// memory limit; a direct request keeps these.
if (!ExportJobUtility::inBackground()) {
	ini_set('memory_limit', '512M');
	set_time_limit(300);
	ini_set('max_execution_time', 300);
}

if (empty($_SESSION['cd4RequestQuery'])) {
	return;
}

/** @var Cd4ExportService $cd4ExportService */
$cd4ExportService = ContainerRegistry::get(Cd4ExportService::class);

// The filters the export was made with, written above the headings.
$filters = '';
foreach ($_POST as $name => $value) {
	if (in_array($name, ['async', 'patientInfo', 'withDetails', 'withAlphaNum'], true) || !is_scalar($value)) {
		continue;
	}
	if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
		$filters .= str_replace("_", " ", $name) . " : " . $value . "  ";
	}
}

$filename = $cd4ExportService->export(
	(string) $_SESSION['cd4RequestQuery'],
	'InteLIS-CD4-REQUESTS',
	($_POST['patientInfo'] ?? '') === 'yes',
	[[html_entity_decode($filters)], ['']],
	($_POST['withAlphaNum'] ?? '') === 'yes',
	($_POST['withDetails'] ?? '') === 'yes'
);

echo _downloadToken($filename);

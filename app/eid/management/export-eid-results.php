<?php

// eid/management/export-eid-results.php
use App\Services\EidExportService;
use App\Utilities\ExportJobUtility;
use App\Registries\ContainerRegistry;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, 'eidExportResultQuery')) {
	return;
}

// A background job has no request to time out, and the worker sets its own
// memory limit; a direct request keeps these.
if (!ExportJobUtility::inBackground()) {
	ini_set('memory_limit', '512M');
	set_time_limit(300);
	ini_set('max_execution_time', 300);
}

if (empty($_SESSION['eidExportResultQuery'])) {
	return;
}

/** @var EidExportService $eidExportService */
$eidExportService = ContainerRegistry::get(EidExportService::class);

$filename = $eidExportService->export(
	(string) $_SESSION['eidExportResultQuery'],
	'InteLIS-EID-Data',
	($_POST['patientInfo'] ?? '') === 'yes'
);

echo _downloadToken($filename);

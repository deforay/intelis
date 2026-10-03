<?php

// vl/program-management/export-vl-results.php
use App\Services\VlExportService;
use App\Utilities\ExportJobUtility;
use App\Registries\ContainerRegistry;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, 'vlResultQuery')) {
	return;
}

// A background job has no request to time out, and the worker sets its own
// memory limit; a direct request keeps these.
if (!ExportJobUtility::inBackground()) {
	ini_set('memory_limit', '512M');
	set_time_limit(300);
	ini_set('max_execution_time', 300);
}

if (empty($_SESSION['vlResultQuery'])) {
	return;
}

/** @var VlExportService $vlExportService */
$vlExportService = ContainerRegistry::get(VlExportService::class);

$filename = $vlExportService->export(
	(string) $_SESSION['vlResultQuery'],
	'InteLIS-VIRAL-LOAD-Data',
	($_POST['patientInfo'] ?? '') === 'yes'
);

echo _downloadToken($filename);

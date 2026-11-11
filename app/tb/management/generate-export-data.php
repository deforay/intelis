<?php

// tb/management/generate-export-data.php
use App\Services\TbExportService;
use App\Utilities\ExportJobUtility;
use App\Registries\ContainerRegistry;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, 'tbResultQuery')) {
	return;
}

// A background job has no request to time out, and the worker sets its own
// memory limit; a direct request keeps these.
if (!ExportJobUtility::inBackground()) {
	ini_set('memory_limit', '512M');
	set_time_limit(300);
	ini_set('max_execution_time', 300);
}

if (empty($_SESSION['tbResultQuery'])) {
	return;
}

/** @var TbExportService $tbExportService */
$tbExportService = ContainerRegistry::get(TbExportService::class);

$filename = $tbExportService->export(
	(string) $_SESSION['tbResultQuery'],
	'InteLIS-TB-Export-Data',
	($_POST['patientInfo'] ?? '') === 'yes',
	[],
	($_POST['withAlphaNum'] ?? '') === 'yes',
	($_POST['withDetails'] ?? '') === 'yes'
);

echo _downloadToken($filename);

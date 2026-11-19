<?php

use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, [])) {
    return;
}

$testType = 'vl';
$targetKind = 'suppressed';
$reportPage = '/vl/program-management/vlSuppressedTargetReport.php';
$reportTitle = _translate("Viral Load Suppression - Monthly Target Report");

require APPLICATION_PATH . '/reports/_testing-target-export.php';

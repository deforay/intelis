<?php

use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, [])) {
    return;
}

$testType = 'cd4';
$reportPage = '/cd4/management/cd4-testing-target-report.php';
$reportTitle = _translate("CD4 Testing Target Report");

require APPLICATION_PATH . '/reports/_testing-target-export.php';

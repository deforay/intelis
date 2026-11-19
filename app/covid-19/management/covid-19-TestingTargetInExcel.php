<?php

use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, [])) {
    return;
}

$testType = 'covid19';
$reportPage = '/covid-19/management/covid19TestingTargetReport.php';
$reportTitle = _translate("COVID-19 Testing Target Report");

require APPLICATION_PATH . '/reports/_testing-target-export.php';

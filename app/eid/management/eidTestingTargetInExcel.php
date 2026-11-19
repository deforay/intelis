<?php

use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, [])) {
    return;
}

$testType = 'eid';
$reportPage = '/eid/management/eidTestingTargetReport.php';
$reportTitle = _translate("EARLY INFANT DIAGNOSIS - Testing Target Report");

require APPLICATION_PATH . '/reports/_testing-target-export.php';

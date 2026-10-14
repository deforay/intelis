<?php

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\ExportJobUtility;
use App\Utilities\SampleTestingReportUtility;

// Export of the Sample Testing tab. Every clinic report page shares its
// columns and file through SampleTestingReportUtility; this script only
// names the test type.
if (ExportJobUtility::queueRequested(__FILE__, SampleTestingReportUtility::sessionKey('tb'))) {
    return;
}

// A background job has no request to time out; a direct request keeps its limits.
if (!ExportJobUtility::inBackground()) {
    ini_set('memory_limit', '512M');
    set_time_limit(300);
}

$file = SampleTestingReportUtility::export('tb', ContainerRegistry::get(DatabaseService::class));
if ($file !== null) {
    echo _downloadToken($file);
}

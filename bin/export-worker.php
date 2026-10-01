#!/usr/bin/env php
<?php

// Runs one background Excel export queued by ExportJobUtility::start().
// Launched detached by the web request; not meant to be run by hand.

if (PHP_SAPI !== 'cli') {
    exit(0);
}

require_once __DIR__ . "/../bootstrap.php";

use App\Utilities\ExportJobUtility;

ini_set('memory_limit', '1G');
set_time_limit(0);

$jobId = (string) ($argv[1] ?? '');

exit(ExportJobUtility::run($jobId));

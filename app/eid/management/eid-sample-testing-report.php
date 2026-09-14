<?php

use App\Utilities\LoggerUtility;
use App\Services\CommonService;
use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\SampleTestingReportUtility;

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$filters = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
    $report = SampleTestingReportUtility::fetch('eid', $filters, $db, $general);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    $report = ['rows' => [], 'startDate' => date('Y-m-d', strtotime('-7 days')), 'endDate' => date('Y-m-d')];
}

require APPLICATION_PATH . '/reports/_sample-testing-report.php';

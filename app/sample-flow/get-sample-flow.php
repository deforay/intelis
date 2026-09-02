<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\SampleFlowService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    // AJAX requests bypass the access control layer, so the page's own
    // privilege is checked here.
    _requirePrivilege('/sample-flow/sample-flow.php');

    /** @var SampleFlowService $sampleFlow */
    $sampleFlow = ContainerRegistry::get(SampleFlowService::class);

    // Filters are normalized by the service: the test key must be a registry
    // key, so it can never reach a query as a table name. Lab scoping happens
    // inside every query.
    $filters = $sampleFlow->resolveFilters($_POST);

    $section = (string) ($_POST['section'] ?? '');
    $output = match ($section) {
        'flow' => ['flow' => $sampleFlow->getFlow($filters)],
        'breakdown' => ['rows' => $sampleFlow->getBreakdown(
            $filters,
            (string) ($_POST['stage'] ?? ''),
            (string) ($_POST['groupBy'] ?? '')
        )],
        default => throw new \App\Exceptions\SystemException('Invalid sample flow section'),
    };

    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => _translate('Unable to load the sample flow right now')]);
}

<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\LabPerformanceIndicatorsService;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    // AJAX requests bypass the access control layer, so the page's own
    // privilege is checked here.
    _requirePrivilege('/reports/interface-machine-activity.php');

    /** @var LabPerformanceIndicatorsService $indicators */
    $indicators = ContainerRegistry::get(LabPerformanceIndicatorsService::class);

    // Filter validation and lab scoping both live in the service.
    $filters = $indicators->resolveFilters($_POST);

    echo JsonUtility::encodeUtf8Json(['rows' => $indicators->getByInstrument($filters)]);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => _translate('Unable to load the instrument figures')]);
}

<?php

use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Utilities\PatientTimelineUtility;

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
    // AJAX requests bypass the access control layer. Each test type is only
    // read when the user can open that module's clinic report, so a user who
    // can open none of them gets nothing.
    if (PatientTimelineUtility::visibleTypes() === []) {
        throw new SystemException(_translate('You do not have permission to perform this action.'), 403);
    }

    $output = match ($_POST['action'] ?? '') {
        'search' => ['patients' => PatientTimelineUtility::search((string) ($_POST['q'] ?? ''), $db, $general)],
        'timeline' => PatientTimelineUtility::timeline((string) ($_POST['patientId'] ?? ''), $db, $general),
        default => throw new SystemException('Invalid patient timeline action'),
    };
    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => _translate('Unable to load the patient history right now')]);
}

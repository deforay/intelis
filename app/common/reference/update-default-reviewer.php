<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {

    // Sanitized values from $request object
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $tableName = TestsService::getTestTableName($_POST['testType']);

    $data = [
        'result_reviewed_by' => $_POST['defaultReviewer']
    ];

    $db->where("(result_reviewed_by IS NULL OR result_reviewed_by = '')");
    if ($_POST['testType'] == 'cd4') {
        $db->where('cd4_result', null, 'IS NOT');
    } else {
        $db->where('result', null, 'IS NOT');
    }
    $db->update($tableName, $data);
} catch (Throwable $exc) {
    LoggerUtility::logError($exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}

<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\TestAttemptService;

try {
    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var TestAttemptService $attempts */
    $attempts = ContainerRegistry::get(TestAttemptService::class);

    // Sanitized values from $request object
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    /* Status definition */
    $status = RECEIVED_AT_TESTING_LAB;
    if ($general->isSTSInstance() && $_SESSION['accessType'] == 'collection-site') {
        $status = RECEIVED_AT_CLINIC;
    }

    // Previously filed its history under test_type 'vl'; the test type now comes from the
    // shared registry, so it cannot drift again.
    echo $attempts->resetForRetest(
        'hepatitis',
        TestAttemptService::sampleIdsFromRequest($_POST, 'hepatitisId'),
        $status
    );
} catch (Throwable $e) {
    throw new SystemException($e->getMessage(), $e->getCode(), $e);
}

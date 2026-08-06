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

    // Retains the failing attempt before clearing the result. See TestAttemptService.
    echo $attempts->resetForRetest(
        'vl',
        TestAttemptService::sampleIdsFromRequest($_POST, 'vlId'),
        $status
    );
} catch (Throwable $e) {
    throw new SystemException($e->getMessage(), $e->getCode(), $e);
}

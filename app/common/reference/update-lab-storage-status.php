<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;
use App\Repositories\Reference\ReferenceDataRepository;

/** @var ReferenceDataRepository $referenceData */
$referenceData = ContainerRegistry::get(ReferenceDataRepository::class);

$result = "";
try {
    // Sanitized values from $request object
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $ids = explode(",", (string) ($_POST['id'] ?? ''));
    $referenceData->updateStatus('lab-storage', 'common', $ids, (string) ($_POST['status'] ?? ''));
    $result = end($ids);
} catch (Throwable $exc) {
    LoggerUtility::logError($exc->getMessage());
    throw $exc;
}
echo $result;

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
    $referenceData->updateStatus('vl-result', 'vl', $ids, (string) ($_POST['status'] ?? ''));
    // The result-entry forms cache the VL result list per instrument; a
    // deactivated result must leave that cache now, not on expiry.
    _invalidateFileCacheByTags(['r_vl_results']);
    $result = end($ids);
} catch (Throwable $exc) {
    LoggerUtility::logError($exc->getMessage());
    throw $exc;
}
echo $result;

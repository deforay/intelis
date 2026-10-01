<?php

use App\Registries\AppRegistry;
use App\Utilities\ExportJobUtility;
use Psr\Http\Message\ServerRequestInterface;

// Progress of a background export (see ExportJobUtility). Polled from every page
// so an export started on one page still downloads after the user moves on.
// Authorizes itself: a job is only ever reported to the user who started it.

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$userId = (string) ($_SESSION['userId'] ?? '');

header('Content-Type: application/json');
header('Cache-Control: no-store');

$status = $userId === '' ? null : ExportJobUtility::status(
    (string) ($_GET['id'] ?? ''),
    $userId,
    ($_GET['claim'] ?? '') === '1',
    ($_GET['again'] ?? '') === '1'
);

if ($status === null) {
    http_response_code(404);
    echo json_encode(['status' => 'missing']);
    return;
}

echo json_encode($status);

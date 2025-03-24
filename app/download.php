<?php

use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Exceptions\SystemException;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

// Retrieve the file name from the GET request and decode it if encoded
$fileName = $_GET['f'] ?? null;

if ($fileName !== null && MiscUtility::isBase64($fileName)) {
    $fileName = base64_decode($fileName);
}

// Check if the file exists in the given path or the temporary path
if (!empty($fileName)) {
    $fileName = urldecode($fileName);
    if (file_exists($fileName)) {
        // $fileName is already set
    } elseif (file_exists(TEMP_PATH . DIRECTORY_SEPARATOR . $fileName)) {
        // The file exists in the temporary path
        $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . $fileName;
    } else {
        // File does not exist in any path
        $fileName = null;
    }
} else {
    // No file name provided
    $fileName = null;
}

if (empty($fileName)) {
    $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
    header("Location:" . urlencode((string) $redirect));
    exit;
}

$allowedMimeTypes = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'text/csv',
    'text/plain'
];

$file = realpath($fileName);
$webRootPath = realpath(WEB_ROOT);

if (
    $file === false ||
    !str_starts_with($file, $webRootPath) ||
    !MiscUtility::fileExists($file)
) {
    http_response_code(404);
    throw new SystemException('File does not exist. Cannot download this file', 404);
}

$mimeType = MiscUtility::getMimeType($file, $allowedMimeTypes);

if (!$mimeType) {
    http_response_code(400);
    throw new SystemException('Invalid file. Cannot download this file', 400);
}

// Sanitize filename
$filename = basename($file);
$filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $filename);

// Check if the file should be forced to download or can be viewed inline
$forceDownload = ($mimeType === 'text/plain' || $mimeType === 'text/csv' ||
    (isset($_GET['d']) && $_GET['d'] === 'a'));

// Serve the file
_serveSecureFile($file, $filename, $mimeType, $forceDownload);

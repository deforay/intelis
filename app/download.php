<?php

use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Utilities\DownloadTokenUtility;
use App\Middlewares\App\AclMiddleware;
use Psr\Http\Message\ServerRequestInterface;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

// Retrieve the file name from the GET request and decode it if encoded
$fileName = $_GET['f'] ?? null;

// This endpoint is excluded from AclMiddleware and authorizes itself, because
// there are two ways to be entitled to a file here and the middleware can only
// express one of them:
//
//   1. A signed grant, minted by the endpoint that produced the file at the
//      moment it decided the user could have it. Self-contained and does not
//      depend on the browser sending a Referer -- which is what used to fail
//      for reopened tabs, restored sessions and privacy-minded browsers, and
//      only ever for non-superadmins.
//   2. A legacy unsigned file name, which still has to clear exactly the same
//      Referer check the middleware applied before. Producers are being moved
//      over to grants; until they all are, this keeps the old contract intact
//      rather than leaving unsigned requests unguarded.
//
// $file is the absolute path to serve; $authorized says whether we may.
$file = null;
$authorized = false;

$grantFailure = null;

if (!empty($fileName) && DownloadTokenUtility::looksLikeToken((string) $fileName)) {
    $file = DownloadTokenUtility::resolve((string) $fileName, $grantFailure);

    if ($file === null) {
        LoggerUtility::logWarning('Download grant rejected', [
            'reason' => $grantFailure,
            'user' => $_SESSION['userName'] ?? null,
            'ip' => CommonService::getClientIpAddress($request),
        ]);

        // Expiry is the overwhelmingly common case and the only one the user can
        // act on, so say that rather than leaking which check actually failed.
        throw new SystemException(
            _translate('This download link has expired. Please generate the file again.'),
            403
        );
    }

    $authorized = true;
} else {
    if ($fileName !== null && MiscUtility::isBase64($fileName)) {
        $fileName = base64_decode((string) $fileName);
    }

    // Trusted base directories that a relative `f` value may resolve against
    // (first existing match wins). These hold app-generated downloads. As files
    // move out of public/temporary into var/, add the specific var/ subdirectory
    // here -- keep it to named dirs, never the whole var/ tree, which also holds
    // logs and cache that must not be downloadable.
    $resolveRoots = [
        TEMP_PATH,      // public/temporary (legacy public files)
        VAR_TEMP_PATH,  // var/temporary (manifests + future non-public files)
    ];

    // Resolve the requested file: an absolute path as-is, otherwise the first
    // trusted root it exists under.
    if (!empty($fileName)) {
        $fileName = urldecode((string) $fileName);
        if (!file_exists($fileName)) {
            $resolved = null;
            foreach ($resolveRoots as $root) {
                $candidate = $root . DIRECTORY_SEPARATOR . $fileName;
                if (file_exists($candidate)) {
                    $resolved = $candidate;
                    break;
                }
            }
            $fileName = $resolved;
        }
    } else {
        // No file name provided
        $fileName = null;
    }

    if ($fileName === null || $fileName === '' || $fileName === '0') {
        $redirect = empty($_SERVER['HTTP_REFERER']) ? '/' : $_SERVER['HTTP_REFERER'];
        header("Location:" . urlencode((string) $redirect));
        exit;
    }

    $file = realpath($fileName);
    $authorized = _isAllowed('/download.php') || AclMiddleware::refererAccessAllowed($request);
}

if (!$authorized) {
    $userName = $_SESSION['userName'] ?? _translate('Guest');
    throw new SystemException(
        _translate("Sorry") . " {$userName}. " .
            _translate('You do not have permission to access this page or resource.'),
        403
    );
}

$allowedMimeTypes = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'application/json',
    'text/csv',
    'text/plain'
];

// A download may live under the public web root (legacy temp files) or the
// non-public var/temporary dir (manifests, and progressively other sensitive
// files). The realpath must resolve within one of these trusted roots.
$allowedRoots = array_values(array_filter([realpath(WEB_ROOT), realpath(VAR_TEMP_PATH)]));
$withinAllowedRoot = false;
if ($file !== false && $file !== null) {
    foreach ($allowedRoots as $root) {
        if (str_starts_with($file, $root)) {
            $withinAllowedRoot = true;
            break;
        }
    }
}

$fileExists = is_string($file) && $file !== '' && MiscUtility::fileExists($file);
if (
    $file === false ||
    $file === null ||
    !$withinAllowedRoot ||
    $fileExists === false
) {
    LoggerUtility::logError('File download failed due to missing or invalid path', [
        'requested_file' => $fileName,
        'resolved_path' => $file ?: 'NOT FOUND',
        'allowed_roots' => $allowedRoots,
        'file_exists' => $fileExists ? 'yes' : 'no',
    ]);

    http_response_code(404);
    throw new SystemException(_translate('File does not exist. Cannot download this file'), 404);
}

$mimeType = MiscUtility::getMimeType($file, $allowedMimeTypes);

if (!$mimeType) {
    http_response_code(400);
    throw new SystemException(_translate('Invalid file. Cannot download this file'), 400);
}

// Sanitize filename
$filename = basename($file);
$filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $filename);

// Check if the file should be forced to download or can be viewed inline
$forceDownload = $mimeType === 'text/plain' || $mimeType === 'text/csv' || (isset($_GET['d']) && $_GET['d'] === 'a');

// Serve the file
_serveSecureFile($file, $filename, $mimeType, $forceDownload);

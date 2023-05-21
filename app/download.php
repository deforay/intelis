<?php

use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

$webRootPath = realpath(WEB_ROOT);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (!isset($_GET['f']) || !is_file(base64_decode($_GET['f']))) {
    $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
    header("Location:" . urlencode($redirect));
    exit;
}

$allowedMimeTypes = [
    'application/pdf' => true,
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => true,
    'application/vnd.ms-excel' => true,
    'text/csv' => true,
    'text/plain' => true
];

// Sanitize values before using them below
$_GET = array_map('htmlspecialchars', $_GET);

$file = realpath(urldecode(base64_decode($_GET['f'])));

if ($file === false) {
    http_response_code(404);
    throw new SystemException('Cannot download this file');
}

if (!$general->startsWith($file, $webRootPath) || !$general->fileExists($file)) {
    http_response_code(403);
    throw new SystemException('Cannot download this file');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo === false) {
    http_response_code(500);
    throw new SystemException('Cannot download this file');
}

$mime = finfo_file($finfo, $file);
finfo_close($finfo);

if (!isset($allowedMimeTypes[$mime])) {
    http_response_code(403);
    throw new SystemException('Cannot download this file');
}

$filename = basename($file);
$filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $filename);

if ($mime === 'text/plain' || $mime === 'text/csv') {
    $disposition = 'attachment';
} else {
    $disposition = (isset($_GET['d']) && $_GET['d'] === 'a') ? 'attachment' : 'inline';
}

header('Content-Description: File Transfer');
header('Content-Type: ' . (($mime !== false) ? $mime : 'application/octet-stream'));
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; script-src \'self\'; style-src \'self\'');
header('Content-Disposition: ' . $disposition . '; filename=' . $filename);
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($file));
readfile($file);

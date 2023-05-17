<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

$webRootPath = realpath(WEB_ROOT);


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (!isset($_GET['f']) || !is_file(base64_decode($_GET['f']))) {
    $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
    header("Location:" . $redirect);
}


$allowedMimeTypes = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'text/csv',
    'text/plain'
    // 'application/msword',
    // 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

$file = realpath(urldecode(base64_decode($_GET['f'])));

if ($file === false) {
    http_response_code(404);
    exit(0);
}

$mime = mime_content_type($file);

// Checking if the file path is inside the VLSM public folder (to avoid path injection)
// Checking if the file even exists
// Checking if file is in allowed types
if (!$general->startsWith($file, $webRootPath) || !in_array($mime, $allowedMimeTypes) || !$general->fileExists($file)) {
    http_response_code(403);
    exit(0);
}

$disposition = (isset($_GET['d']) && $_GET['d'] = 'a') ? 'attachment' : 'inline';

header('Content-Description: File Transfer');
header('Content-Type: ' . (($mime !== false) ? $mime : 'application/octet-stream'));
header('Content-Disposition: ' . $disposition . '; filename=' . basename($file));
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($file));
readfile($file);

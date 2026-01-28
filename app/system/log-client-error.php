<?php

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Utilities\LoggerUtility;
use App\Services\CommonService;

// Rate limit: max 10 errors per minute per session
$now = time();
$errorCount = $_SESSION['_client_error_count'] ?? 0;
$lastErrorTime = $_SESSION['_client_error_time'] ?? 0;

// Reset counter if more than 60 seconds have passed
if (($now - $lastErrorTime) > 60) {
    $errorCount = 0;
}

if ($errorCount >= 10) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many error reports']);
    exit;
}

$_SESSION['_client_error_count'] = $errorCount + 1;
$_SESSION['_client_error_time'] = $now;

// Get JSON body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (empty($data) || empty($data['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid error data']);
    exit;
}

// Sanitize and limit input sizes
$message = substr($data['message'] ?? 'Unknown error', 0, 1000);
$source = substr($data['source'] ?? '', 0, 500);
$line = (int) ($data['line'] ?? 0);
$column = (int) ($data['column'] ?? 0);
$stack = substr($data['stack'] ?? '', 0, 5000);
$url = substr($data['url'] ?? '', 0, 500);
$type = substr($data['type'] ?? 'error', 0, 50);
$userAgent = substr($data['userAgent'] ?? '', 0, 500);

// Log the client error
LoggerUtility::logError("Client-side JS error: $message", [
    'type' => $type,
    'source' => $source,
    'line' => $line,
    'column' => $column,
    'stack' => $stack,
    'page_url' => $url,
    'user_agent' => $userAgent,
    'ip_address' => CommonService::getClientIpAddress(),
    'user_id' => $_SESSION['userId'] ?? null,
    'user_name' => $_SESSION['userName'] ?? null,
]);

echo json_encode(['status' => 'logged']);

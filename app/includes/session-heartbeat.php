<?php

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Only allow POST requests (CSRF protection applies to POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

// Rate limit: max 1 heartbeat per 2 minutes per session
$now = time();
$lastHeartbeat = $_SESSION['_heartbeat_time'] ?? 0;
if (($now - $lastHeartbeat) < 120) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}
$_SESSION['_heartbeat_time'] = $now;

// Session is already touched by the middleware (sliding session)
// Just return success if we got here (means session is valid)
echo json_encode(['status' => 'ok']);

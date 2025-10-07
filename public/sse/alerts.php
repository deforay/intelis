<?php
// Server-Sent Events (SSE) stream for system alerts
// - requires user to be logged in
// - admin users get both 'admin' and 'auth' alerts, others only 'auth'
// - uses SQLite backend for alert storage
// - supports Last-Event-ID header or ?last_id= for resuming
// - heartbeats every 20s if no events
// - max connection life ~5min (clients should reconnect as needed)

declare(strict_types=1);

require_once dirname(__DIR__) . '/../bootstrap.php';

use App\Services\SystemService;

// --- auth / audience ---
$isLoggedIn = !empty($_SESSION['userId'] ?? null);
$isAdmin    = $isLoggedIn && !empty($_SESSION['roleId']) && (int)$_SESSION['roleId'] === 1;

if (!$isLoggedIn) {
    http_response_code(403);
    exit;
}
session_write_close();

// --- SSE headers ---
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // disable buffering on nginx, if present

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');

// Drain any active output buffers before starting SSE
while (ob_get_level() > 0) {
    @ob_end_flush();
}
ob_implicit_flush(true);

// Helper: safe flush (only ob_flush if a buffer exists)
$flush_now = static function (): void {
    if (function_exists('ob_flush') && ob_get_level() > 0) {
        @ob_flush();
    }
    flush();
};

// Send an initial padding comment to coax proxies/browsers to start streaming
echo ':' . str_repeat(' ', 2048) . "\n\n";
$flush_now();

// --- resume id (from Last-Event-ID header or ?last_id=) ---
$lastId = isset($_SERVER['HTTP_LAST_EVENT_ID'])
    ? (int)$_SERVER['HTTP_LAST_EVENT_ID']
    : (int)($_GET['last_id'] ?? 0);

// normalize lastId if it’s ahead of SQLite (e.g., after switching backends)
try {
    $maxId = SystemService::systemAlertSqliteMaxId();
    if ($lastId > $maxId) {
        $lastId = 0; // reset so client can catch up
    }
} catch (\Throwable $ignore) {
    // if maxId lookup fails, continue anyway
}

// client reconnect backoff (ms)
echo "retry: 8000\n\n";

// optional one-time ping for debugging/handshake
echo "event: system\n";
echo 'data: ' . json_encode(['message' => 'sse connected', 'lastId' => $lastId], JSON_UNESCAPED_SLASHES) . "\n\n";
$flush_now();

// audience
$audiences = $isAdmin ? ['admin', 'auth'] : ['auth'];

// --- timing / heartbeats ---
$started   = time();
$maxLife   = 300; // ~5 min per connection; client should auto-reconnect
$heartbeat = 20;  // seconds
$lastBeat  = time();

// Keep this SSE alive; let our loop decide when to end
@set_time_limit(0);          // unlimited PHP execution time
ignore_user_abort(false);    // stop if client disconnects

while (true) {
    if (connection_aborted()) {
        break;
    }

    $gotAny = false;

    try {
        $rows = SystemService::systemAlertSqliteReadSinceId($lastId, $audiences);
        foreach ($rows as $r) {
            $json = json_encode($r, JSON_UNESCAPED_SLASHES);

            // named event (type-based listener)
            echo "id: {$r['id']}\n";
            echo "event: {$r['type']}\n";
            echo "data: {$json}\n\n";

            $lastId = (int)$r['id'];
            $gotAny = true;
        }
    } catch (\Throwable $e) {
        // if SQLite read fails, emit a diagnostic event and continue heartbeating
        echo "event: system\n";
        echo 'data: ' . json_encode(['message' => 'alerts store unavailable'], JSON_UNESCAPED_SLASHES) . "\n\n";
        $gotAny = true;
    }

    if ($gotAny) {
        $lastBeat = time();
        $flush_now();
    } else {
        if (time() - $lastBeat >= $heartbeat) {
            // comment line = heartbeat (ignored by EventSource but keeps connection warm)
            echo ': heartbeat ' . time() . "\n\n";
            $lastBeat = time();
            $flush_now();
        }
        usleep(2_000_000); // 2s
    }

    if ((time() - $started) > $maxLife) {
        break;
    }
}

exit(0);

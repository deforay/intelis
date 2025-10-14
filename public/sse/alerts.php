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
        // DO NOT replay from 0; jump to the tip to avoid blasting history
        $lastId = (int)$maxId;
    }
} catch (\Throwable $ignore) {
    // if maxId lookup fails, continue anyway
}


// Backoff on reconnects (client will honor this)
echo "retry: 15000\n\n"; // 15s


// optional one-time ping for debugging/handshake
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$firstConnect = ($lastId === 0);

if ($debug && $firstConnect) {
    echo "event: system\n";
    echo 'data: {"message":"sse connected","lastId":' . $lastId . "}\n\n";
    $flush_now();
}


// audience
$audiences = $isAdmin ? ['admin', 'auth'] : ['auth'];

// --- timing / heartbeats ---
$started   = time();
$maxLife   = 300; // ~5 min per connection; client should auto-reconnect
$heartbeat = 60;  // seconds
$lastBeat  = time();

// throttle limits to prevent runaway usage
$EVENT_LIMIT_PER_CONN = 500;        // max events this connection may send
$BYTE_LIMIT_PER_CONN  = 512 * 1024; // ~512 KB per connection
$eventsSent = 0;
$bytesSent  = 0;

// Keep this SSE alive; let our loop decide when to end
@set_time_limit(0);          // unlimited PHP execution time
ignore_user_abort(false);    // stop if client disconnects

$unavailableNotified = false;
$errorBackoffSec = 5;           // first backoff
$maxErrorBackoffSec = 60;       // cap backoff

while (true) {
    if (connection_aborted()) break;

    $gotAny = false;

    try {
        $rows = SystemService::systemAlertSqliteReadSinceId($lastId, $audiences);

        // recovery: if we were in error mode, reset flags
        if ($unavailableNotified) {
            $unavailableNotified = false;
            $errorBackoffSec = 5;
        }

        if (count($rows) > 200) $rows = array_slice($rows, 0, 200);
        if (!empty($rows)) $gotAny = true;

        foreach ($rows as $r) {
            $payload = [
                'id'      => (int)$r['id'],
                'type'    => (string)$r['type'],
                'level'   => (string)($r['level'] ?? 'info'),
                'message' => (string)$r['message'],
            ];
            $json  = json_encode($payload, JSON_UNESCAPED_SLASHES);
            echo "id: {$payload['id']}\n";
            echo "event: {$payload['type']}\n";
            echo "data: {$json}\n\n";

            $lastId = $payload['id'];
            $eventsSent++;
            $bytesSent += strlen($json) + 32;
            if ($eventsSent >= $EVENT_LIMIT_PER_CONN || $bytesSent >= $BYTE_LIMIT_PER_CONN) {
                $flush_now();
                break;
            }
        }
    } catch (\Throwable $e) {
        if (!$unavailableNotified) {
            echo "event: system\n";
            echo 'data: {"message":"alerts store unavailable"}' . "\n\n";  // send ONCE
            $unavailableNotified = true;
            $lastBeat = time();   // reset heartbeat timer so we don't immediately spam
            $flush_now();
        }
        // Back off while unhealthy, send only tiny heartbeats
        sleep($errorBackoffSec);
        if ($errorBackoffSec < $maxErrorBackoffSec) {
            $errorBackoffSec = min($maxErrorBackoffSec, $errorBackoffSec * 2);
        }
        // Send a heartbeat to keep the pipe warm
        echo ":\n\n";
        $flush_now();
        continue;
    }

    if ($gotAny) {
        $lastBeat = time();
        $flush_now();
        usleep(200000); // ok to pace under load
    } else {
        if (time() - $lastBeat >= $heartbeat) {
            echo ":\n\n";          // comment heartbeat (tiny)
            $lastBeat = time();
            $flush_now();
        }
        usleep(2_000_000);
    }

    if ((time() - $started) > $maxLife) break;
}

exit(0);

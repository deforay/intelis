<?php
// Server-Sent Events (SSE) stream for system alerts
// - requires user to be logged in
// - admin users get both 'admin' and 'auth' alerts, others only 'auth'
// - uses SQLite backend for alert storage
// - supports Last-Event-ID header or ?last_id= for resuming
// - optional heartbeats via ?hb=SECONDS (0 disables; default 0)
// - max connection life ~5min (clients should reconnect as needed)
// - wire stays silent unless there are real events (or explicit heartbeats)

declare(strict_types=1);

require_once dirname(__DIR__) . '/../bootstrap.php';

use App\Services\SystemService;
use App\Utilities\LoggerUtility;

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
} catch (\Throwable $e) {
    // Log once during bootstrap; don't emit to SSE
    LoggerUtility::log('error', 'SSE init failed to read maxId: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
}

// Backoff on reconnects (client will honor this)
echo "retry: 15000\n\n"; // 15s

// optional one-time ping for debugging only (?debug=1)
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
$maxLife   = 300;                          // ~5 min per connection
$heartbeat = (int)($_GET['hb'] ?? 0);      // 0 = no heartbeats unless requested
$lastBeat  = time();

// throttle limits to prevent runaway usage
$EVENT_LIMIT_PER_CONN = 500;        // max events this connection may send
$BYTE_LIMIT_PER_CONN  = 512 * 1024; // ~512 KB per connection
$eventsSent = 0;
$bytesSent  = 0;

// Keep this SSE alive; let our loop decide when to end
@set_time_limit(0);          // unlimited PHP execution time
ignore_user_abort(false);    // stop if client disconnects

$errorBackoffSec    = 5;     // first backoff
$maxErrorBackoffSec = 60;    // cap backoff

while (true) {
    if (connection_aborted()) {
        LoggerUtility::log('info', 'SSE client disconnected', ['lastId' => $lastId]);
        break;
    }

    $gotAny = false;

    try {
        $rows = SystemService::systemAlertSqliteReadSinceId($lastId, $audiences);

        // soft-cap batch size
        if (count($rows) > 200) {
            $rows = array_slice($rows, 0, 200);
        }

        if (!empty($rows)) {
            $gotAny = true;
        }

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
            $bytesSent += strlen($json) + 32; // rough framing overhead

            if ($eventsSent >= $EVENT_LIMIT_PER_CONN || $bytesSent >= $BYTE_LIMIT_PER_CONN) {
                LoggerUtility::log('info', 'SSE connection limit hit', [
                    'eventsSent' => $eventsSent,
                    'bytesSent'  => $bytesSent,
                ]);
                $flush_now();
                break;
            }
        }

        // reset error backoff after a successful cycle
        $errorBackoffSec = 5;
    } catch (\Throwable $e) {
        // log, back off silently (no SSE noise)
        LoggerUtility::log('error', 'SSE alerts read failed: ' . $e->getMessage(), [
            'lastId'     => $lastId,
            'backoffSec' => $errorBackoffSec,
            'file'       => $e->getFile(),
            'line'       => $e->getLine(),
        ]);

        sleep($errorBackoffSec);
        if ($errorBackoffSec < $maxErrorBackoffSec) {
            $errorBackoffSec = min($maxErrorBackoffSec, $errorBackoffSec * 2);
        }
        continue;
    }

    if ($gotAny) {
        $lastBeat = time();
        $flush_now();
        usleep(200_000); // 200ms pacing under load (optional)
    } else {
        if ($heartbeat > 0 && (time() - $lastBeat) >= $heartbeat) {
            echo ":\n\n";   // tiny comment heartbeat
            $lastBeat = time();
            $flush_now();
        }
        usleep(2_000_000); // 2s idle sleep
    }

    if ((time() - $started) > $maxLife) {
        LoggerUtility::log('info', 'SSE connection ended (maxLife reached)', [
            'eventsSent' => $eventsSent,
            'bytesSent'  => $bytesSent,
            'lastId'     => $lastId,
        ]);
        break;
    }
}

exit(0);

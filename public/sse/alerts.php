<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/../bootstrap.php';

use App\Services\SystemService;

// --- auth ---
$isLoggedIn = !empty($_SESSION['userId'] ?? null);
$isAdmin    = $isLoggedIn && !empty($_SESSION['roleId']) && (int)$_SESSION['roleId'] === 1;
if (!$isLoggedIn) {
    http_response_code(403);
    exit;
}
session_write_close();

// --- if store missing, do nothing (completely silent) ---
if (!SystemService::isAlertStoreAvailable(false)) { // no logging
    http_response_code(204); // No Content
    exit;
}

// --- SSE headers ---
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    @ob_end_flush();
}
ob_implicit_flush(true);

$flush_now = static function (): void {
    if (function_exists('ob_flush') && ob_get_level() > 0) {
        @ob_flush();
    }
    flush();
};

// coax streaming + default retry
echo ':' . str_repeat(' ', 2048) . "\n\n";
echo "retry: 15000\n\n";
$flush_now();

// --- params ---
$lastId    = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : (int)($_GET['last_id'] ?? 0);
$heartbeat = (int)($_GET['hb'] ?? 0); // 0 = silent
$debug     = ($_GET['debug'] ?? '') === '1';
$firstConnect = ($lastId === 0);

// normalize lastId to tip if ahead
try {
    $maxId = SystemService::systemAlertSqliteMaxId();
    if ($lastId > $maxId) {
        $lastId = (int)$maxId;
    }
} catch (\Throwable $e) {
    // store should be available; if this happens, just end quietly
    exit(0);
}

// optional debug hello
if ($debug && $firstConnect) {
    echo "event: system\n";
    echo 'data: {"message":"sse connected","lastId":' . $lastId . "}\n\n";
    $flush_now();
}

// --- audience / loop controls ---
$audiences = $isAdmin ? ['admin', 'auth'] : ['auth'];
$started   = time();
$maxLife   = 300;
$lastBeat  = time();

$EVENT_LIMIT_PER_CONN = 500;
$BYTE_LIMIT_PER_CONN  = 512 * 1024;
$eventsSent = 0;
$bytesSent  = 0;

@set_time_limit(0);
ignore_user_abort(false);

while (true) {
    if (connection_aborted()) {
        break;
    }

    $gotAny = false;

    try {
        $rows = SystemService::systemAlertSqliteReadSinceId($lastId, $audiences);
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
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

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
        // If store fails mid-connection, just end quietly (client will reconnect)
        exit(0);
    }

    if ($gotAny) {
        $lastBeat = time();
        $flush_now();
        usleep(200000);
    } else {
        if ($heartbeat > 0 && (time() - $lastBeat) >= $heartbeat) {
            echo ":\n\n";
            $lastBeat = time();
            $flush_now();
        }
        usleep(2000000);
    }

    if ((time() - $started) > $maxLife) {
        break;
    }
}

exit(0);


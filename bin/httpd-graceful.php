#!/usr/bin/env php
<?php
// Best-effort Apache graceful reload for post-install/update.
// Works on Linux, macOS, and WSL. Never fails Composer runs.

if (php_sapi_name() !== 'cli') exit(0);

function tryCmd(string $cmd): bool
{
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $desc, $pipes);
    if (!is_resource($proc)) return false;

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    if ($code === 0) {
        if ($stdout) echo trim($stdout) . "\n";
        return true;
    } else {
        if ($stderr) echo trim($stderr) . "\n";
        return false;
    }
}

$timestamp = date('Y-m-d H:i:s');
echo "🔁 [$timestamp] Reloading Apache gracefully... ";

$cmds = PHP_OS_FAMILY === 'Darwin'
    ? ['apachectl -k graceful', 'apachectl graceful', 'brew services restart httpd']
    : ['apache2ctl -k graceful', 'apache2ctl graceful', 'systemctl reload apache2', 'service apache2 reload', 'systemctl reload httpd', 'service httpd reload'];

$ok = false;
foreach ($cmds as $cmd) {
    if (tryCmd($cmd)) {
        $ok = true;
        echo "✅ done ($cmd)\n";
        break;
    }
}

if (!$ok) {
    echo "⚠️  could not reload Apache (no supported command found)\n";
}

exit(0);

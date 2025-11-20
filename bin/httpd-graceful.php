#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') {
    exit(0);
}

use Symfony\Component\Process\Process;

// --- helpers ---
function run(string $cmd): array
{
    $process = Process::fromShellCommandline($cmd);
    $process->setTimeout(null);
    $process->run();

    $out = $process->getOutput();
    $err = $process->getErrorOutput();

    return [
        $process->getExitCode() ?? 1,
        $out !== '' ? $out : $err,
        $err,
    ];
}
function ok(string $cmd): bool
{
    return run($cmd)[0] === 0;
}
function is_root(): bool
{
    return function_exists('posix_geteuid') ? posix_geteuid() === 0 : false;
}

$ts = date('Y-m-d H:i:s');
echo "🔁 [$ts] Reloading Apache gracefully... ";

// 1) Preflight: config must be valid, else skip
$testCmds = PHP_OS_FAMILY === 'Darwin'
    ? ['apachectl -t']
    : ['apache2ctl -t', 'apachectl -t'];

$cfgOk = false;
foreach ($testCmds as $t) {
    if (ok($t)) {
        $cfgOk = true;
        break;
    }
}
if (!$cfgOk) {
    echo "⚠️  config test failed; fix 'apachectl -t' first. Skipping reload.\n";
    exit(0);
}

// 2) Build attempts based on OS + privilege
$cmds = (PHP_OS_FAMILY === 'Darwin')
    ? ['apachectl -k graceful', 'apachectl graceful', 'brew services restart httpd']
    : (is_root()
        // root: systemctl/service are allowed
        ? [
            'apache2ctl -k graceful',
            'apache2ctl graceful',
            'systemctl --no-pager --quiet reload apache2',
            'service apache2 reload',
            'systemctl --no-pager --quiet reload httpd',
            'service httpd reload',
        ]
        // non-root: avoid noisy systemctl/service calls that will require auth
        : [
            'apache2ctl -k graceful',
            'apache2ctl graceful',
        ]
    );

// 3) Try in order
foreach ($cmds as $c) {
    if (ok($c)) {
        echo "✅ done ($c)\n";
        exit(0);
    }
}

// 4) Last resort: if you want, you can allow sudo-no-prompt. Commented by default.
// $sudoCmds = ['sudo -n systemctl reload apache2', 'sudo -n systemctl reload httpd'];
// foreach ($sudoCmds as $c) { if (ok($c)) { echo "✅ done ($c)\n"; exit(0); } }

echo "⚠️  could not reload Apache (insufficient privileges or no supported command)\n";
exit(0);

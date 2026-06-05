#!/usr/bin/env php
<?php

/**
 * Switch this instance between LIS mode and STS mode (developer convenience).
 *
 * Flips the instance type used by CommonService::isLISInstance() /
 * isSTSInstance() — i.e. the `sc_user_type` system_config row — then clears
 * the application cache so the new mode is picked up. Config changes only
 * fully take effect after a `composer post-update`, which this script offers
 * to run for you.
 *
 * Intended for DEV machines only. It refuses to run in production unless you
 * pass --force.
 *
 * Usage:
 *   php bin/dev/switch-mode.php             toggle between lismode <-> stsmode
 *   php bin/dev/switch-mode.php lis         force LIS mode
 *   php bin/dev/switch-mode.php sts         force STS mode
 *   php bin/dev/switch-mode.php sts -y      non-interactive (assume "yes")
 *   php bin/dev/switch-mode.php sts --no-post-update   skip composer post-update
 *   php bin/dev/switch-mode.php sts --force            allow in production
 */

declare(strict_types=1);

use App\Services\CommonService;
use App\Services\ConfigService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Utilities\LoggerUtility;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

$io = null;

try {
    require_once __DIR__ . "/../../bootstrap.php";

    $io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

    if (PHP_SAPI !== 'cli') {
        $io->error("switch-mode can only be run from the command line.");
        exit(CLI\ERROR);
    }

    // ---- parse args --------------------------------------------------------
    $argvRest = array_slice($argv, 1);
    $flags = array_values(array_filter($argvRest, static fn($a): bool => str_starts_with((string) $a, '-')));
    $positional = array_values(array_filter($argvRest, static fn($a): bool => !str_starts_with((string) $a, '-')));

    $assumeYes = (bool) array_intersect(['-y', '--yes'], $flags);
    $skipPostUpdate = in_array('--no-post-update', $flags, true);
    $force = in_array('--force', $flags, true);

    // ---- dev guard ---------------------------------------------------------
    if (APPLICATION_ENV === 'production' && !$force) {
        $io->error([
            "This is a developer tool and APPLICATION_ENV is 'production'.",
            "Re-run with --force only if you really mean to switch a production box.",
        ]);
        exit(CLI\ERROR);
    }

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    /** @var ConfigService $configService */
    $configService = ContainerRegistry::get(ConfigService::class);
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    // ---- resolve current + target modes ------------------------------------
    // Canonical values written by registration: 'lismode' / 'stsmode'.
    // Legacy aliases still honoured by CommonService: 'vluser' / 'remoteuser'.
    $current = (string) ($general->getInstanceType() ?? '');
    $currentIsSts = $general->isSTSInstance();
    $currentIsLis = $general->isLISInstance();

    $arg = strtolower((string) ($positional[0] ?? ''));
    $target = match ($arg) {
        'lis', 'lismode', 'vluser' => 'lismode',
        'sts', 'stsmode', 'remoteuser' => 'stsmode',
        '' => $currentIsSts ? 'lismode' : 'stsmode', // toggle
        default => null,
    };

    if ($target === null) {
        $io->error("Unknown mode '$arg'. Use 'lis' or 'sts' (or omit to toggle).");
        exit(CLI\INVALID_INPUT);
    }

    $io->title('Switch instance mode');
    $io->text([
        "Current : <info>" . ($current !== '' ? $current : '(unset)') . "</info>"
            . ($currentIsSts ? '  → STS' : ($currentIsLis ? '  → LIS' : '')),
        "Target  : <info>$target</info>  → " . ($target === 'stsmode' ? 'STS' : 'LIS'),
    ]);

    $alreadyThere = ($target === 'stsmode' && $currentIsSts) || ($target === 'lismode' && $currentIsLis);
    if ($alreadyThere && $current === $target) {
        $io->success("Already in $target. Nothing to change.");
        exit(CLI\OK);
    }

    if (!$assumeYes && !$io->confirm("Switch to $target?", true)) {
        $io->text('Aborted.');
        exit(CLI\OK);
    }

    // ---- write the new mode ------------------------------------------------
    $db->where('name', 'sc_user_type');
    $db->update('system_config', ['value' => $target]);

    $io->text("Updated <info>sc_user_type</info> → <info>$target</info>");

    // When moving to STS, make sure an STS API key exists in the config file.
    if ($target === 'stsmode') {
        $config = $configService->getConfig();
        if (empty($config['sts']['api_key'])) {
            $domain = rtrim((string) $general->getRemoteURL(), '/') ?: 'http://localhost';
            $configService->updateConfig(['sts.api_key' => ConfigService::generateAPIKeyForSTS($domain)]);
            $io->text("Generated <info>sts.api_key</info> (from $domain).");
        }
    }

    // ---- clear caches so the new mode is read immediately ------------------
    (ContainerRegistry::get(FileCacheUtility::class))->clear();
    MiscUtility::deleteFile(CACHE_PATH . DIRECTORY_SEPARATOR . 'CompiledContainer.php');
    if (isset($_SESSION['instance'])) {
        unset($_SESSION['instance']);
    }
    $io->text('Cleared application cache (file cache + compiled container).');

    // ---- composer post-update ----------------------------------------------
    if ($skipPostUpdate) {
        $io->success("Switched to $target.");
        $io->note("Skipped post-update. Run it yourself for config to fully take effect:\n    composer post-update");
        exit(CLI\OK);
    }

    $composer = findComposer();
    if ($composer === null) {
        $io->success("Switched to $target.");
        $io->warning("Could not locate the composer binary. Run manually:\n    composer post-update");
        exit(CLI\OK);
    }

    $io->section('Running composer post-update');
    $process = new Process([$composer, 'post-update'], dirname(__DIR__, 2));
    $process->setTimeout(null);
    if (Process::isTtySupported()) {
        try {
            $process->setTty(true);
        } catch (\RuntimeException) {
            // fall through to streamed output below
        }
    }
    $process->run(function (string $type, string $buffer): void {
        echo $buffer;
    });

    if (!$process->isSuccessful()) {
        $io->error("composer post-update failed (exit {$process->getExitCode()}). The mode is switched; re-run `composer post-update` manually.");
        exit(CLI\ERROR);
    }

    $io->success("Switched to $target and ran composer post-update.");
    exit(CLI\OK);
} catch (Throwable $e) {
    if ($io !== null) {
        $io->error("Switch failed: " . $e->getMessage());
    } else {
        fwrite(STDERR, "Switch failed: " . $e->getMessage() . PHP_EOL);
    }
    LoggerUtility::logError("switch-mode failure: " . $e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(CLI\ERROR);
}

/**
 * Locate the composer executable: PATH first, then common install locations.
 */
function findComposer(): ?string
{
    $fromPath = trim((string) shell_exec('command -v composer 2>/dev/null'));
    if ($fromPath !== '') {
        return $fromPath;
    }
    foreach (['/usr/local/bin/composer', '/opt/homebrew/bin/composer', '/usr/bin/composer'] as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }
    return null;
}

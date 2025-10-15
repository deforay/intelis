#!/usr/bin/env php
<?php
// bin/setup-sts.php

declare(strict_types=1);

use App\Services\CommonService;
use App\Services\ConfigService;
use App\Services\DatabaseService;
use App\Utilities\FileCacheUtility;
use App\Utilities\LoggerUtility;
use App\Utilities\MiscUtility;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;


$cliMode = php_sapi_name() === 'cli';

// --- graceful Ctrl+C (if pcntl available)
if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () {
        echo PHP_EOL . "⚠️  Setup cancelled by user." . PHP_EOL;
        exit(130);
    });
}

try {
    require_once __DIR__ . "/../../bootstrap.php";
    ini_set('memory_limit', '-1');
    set_time_limit(0);

    $input = new ArgvInput();
    $output = new ConsoleOutput();
    $io = new SymfonyStyle($input, $output);

    $io->title('STS Configuration Setup');

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    /** @var ConfigService $configService */
    $configService = ContainerRegistry::get(ConfigService::class);
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    $isLIS = $general->isLISInstance();
    if (!$isLIS || !$cliMode) {
        echo "❗ This script is only for LIS instances and must be run from the command line." . PHP_EOL;
        exit(0);
    }

    // ---- helpers -----------------------------------------------------------
    /**
     * Read user input from CLI. Returns null on EOF.
     */
    function readUserInput(string $prompt = ''): ?string
    {
        echo $prompt;
        $h = fopen('php://stdin', 'r');
        if ($h === false) return null;
        $line = fgets($h);
        fclose($h);
        if ($line === false) return null;
        return trim($line);
    }

    function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    function hasCmd(string $cmd): bool
    {
        return trim((string) shell_exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null || true')) !== '';
    }

    /**
     * Try https, then http for STS; verify via CommonService::validateStsUrl().
     */
    function normalizeUrl(string $url, ?int $labId = null): string
    {
        $url = trim($url);
        if ($url === '') return $url;

        // If full URL given, test as-is first, then flip scheme
        if (preg_match('/^https?:\/\//i', $url)) {
            $testUrl = rtrim($url, '/');
            if (CommonService::validateStsUrl($testUrl, $labId)) return $testUrl;
            $domain = preg_replace('/^https?:\/\//i', '', $url);
        } else {
            $domain = $url;
        }

        $https = 'https://' . rtrim($domain, '/');
        if (CommonService::validateStsUrl($https, $labId)) return $https;

        $http = 'http://' . rtrim($domain, '/');
        if (CommonService::validateStsUrl($http, $labId)) return $http;

        // default to https (later validation may still fail)
        return $https;
    }

    /**
     * Attempt apt-get install of fzf if missing (only if root).
     */
    function maybeInstallFzf(): void
    {
        if (hasCmd('fzf')) return;
        $isRoot = function_exists('posix_geteuid') ? (posix_geteuid() === 0) : false;
        $hasApt = hasCmd('apt-get');

        if (!$isRoot || !$hasApt) {
            echo "❌ fzf not found. Please install:\n   sudo apt-get update && sudo apt-get install -y fzf\n";
            exit(1);
        }
        $ans = strtolower((string) (readUserInput("fzf not found. Install now? (y/N): ") ?? ''));
        if ($ans !== 'y' && $ans !== 'yes') {
            echo "❌ fzf required. Aborting." . PHP_EOL;
            exit(1);
        }
        system('apt-get update -y && apt-get install -y fzf', $rc);
        if ($rc !== 0 || !hasCmd('fzf')) {
            echo "❌ Failed to install fzf." . PHP_EOL;
            exit(1);
        }
        echo "✅ fzf installed." . PHP_EOL;
    }

    /**
     * Fetch active testing labs and sanitize names.
     * @return array<int,array{facility_id:int|string,facility_name:string}>
     */
    function fetchActiveLabs(DatabaseService $db): array
    {
        $labs = $db->rawQuery("
            SELECT facility_id, facility_name
            FROM facility_details
            WHERE facility_type = 2 AND status = 'active'
            ORDER BY facility_name
        ");
        foreach ($labs as &$l) {
            $name = str_replace(["\r", "\n", "\t"], ' ', (string) $l['facility_name']);
            $name = preg_replace('/\s+/', ' ', $name);
            $l['facility_name'] = trim($name);
        }
        unset($l);
        return $labs;
    }

    /**
     * fzf-based picker; returns selected lab or null.
     * @param array<int,array{facility_id:int|string,facility_name:string}> $labs
     */
    function pickLabViaFzf(array $labs): ?array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'labs_');
        $lines = array_map(fn($l) => $l['facility_id'] . "\t" . $l['facility_name'], $labs);
        file_put_contents($tmp, implode(PHP_EOL, $lines));

        $cmd = 'cat ' . escapeshellarg($tmp)
            . ' | fzf --ansi --height=80% --reverse --border --cycle '
            . ' --prompt="Select lab > " '
            . ' --header="Type to filter • ↑/↓ to move • Enter to select" '
            . ' --with-nth=2 --delimiter="\t" 2>/dev/null';

        $out = trim((string) shell_exec($cmd));
        @unlink($tmp);

        if ($out === '' || strpos($out, "\t") === false) return null;
        [$id, $name] = explode("\t", $out, 2);
        return ['facility_id' => trim($id), 'facility_name' => trim($name)];
    }
    // ------------------------------------------------------------------------

    // Clear file cache
    (ContainerRegistry::get(FileCacheUtility::class))->clear();

    // Parse CLI options (kept for future use)
    $options = getopt('k', ['key:']);
    $apiKey = $options['key'] ?? null;

    echo "=== STS Configuration Setup ===" . PHP_EOL . PHP_EOL;

    // ---- Step 1: STS URL ---------------------------------------------------
    $currentRemoteURL = rtrim((string) $general->getRemoteURL(), '/');
    $urlChanged = false;
    $newRemoteURL = $currentRemoteURL;
    $currentLabId = (int) ($general->getSystemConfig('sc_testing_lab_id') ?? 0);

    if ($currentRemoteURL === '') {
        $io->warning('No STS URL is currently configured.');
        $io->note('Press Enter to skip (you can set it later from Admin → System Config).');

        $attempts = 0;
        do {
            $attempts++;
            $userInput = $io->ask('Enter STS URL (or press Enter to skip)');
            if ($userInput === null || $userInput === '') {
                $io->text('Skipping STS URL setup for now.');
                $newRemoteURL = '';
                break;
            }

            $candidate = normalizeUrl($userInput, $currentLabId);
            if (!isValidUrl($candidate)) {
                $io->warning('Invalid URL. Try again or press Enter to skip.');
                continue;
            }
            if (!CommonService::validateStsUrl($candidate, $currentLabId)) {
                $io->error('Cannot connect to STS at this URL. Try again or press Enter to skip.');
                continue;
            }

            $newRemoteURL = $candidate;
            $urlChanged = true;
            $io->text("Using: <info>$newRemoteURL</info>");
            break;
        } while ($attempts < 5);
    } else {
        $io->section('Current Configuration');
        $io->text("Current STS URL: <info>$currentRemoteURL</info>");

        // confirm() returns bool — use it directly
        $ok = $io->confirm('Is this STS URL correct?', true);

        if ($ok) {
            // Keep as-is; do NOT ask again
            $newRemoteURL = $currentRemoteURL;
        } else {
            $io->note('Press Enter to skip (you can set it later).');
            $attempts = 0;
            do {
                $attempts++;
                $userInput = $io->ask('Enter new STS URL (or press Enter to skip)', '');
                if ($userInput === null || $userInput === '') {
                    $io->text('Skipping change; keeping the previous value.');
                    $newRemoteURL = $currentRemoteURL;
                    break;
                }

                $candidate = normalizeUrl($userInput, $currentLabId);
                if (!isValidUrl($candidate)) {
                    $io->warning('Invalid URL. Try again or press Enter to skip.');
                    continue;
                }
                if (!CommonService::validateStsUrl($candidate, $currentLabId)) {
                    $io->error('Cannot connect to STS at this URL. Try again or press Enter to skip.');
                    continue;
                }

                $newRemoteURL = $candidate;
                $urlChanged = ($newRemoteURL !== $currentRemoteURL);
                $io->text("Using: <info>$newRemoteURL</info>");
                break;
            } while ($attempts < 5);
        }
    }

    if ($urlChanged && $newRemoteURL !== '') {
        $io->text('Updating STS URL in configuration…');
        try {
            $configService->updateConfig(['remoteURL' => $newRemoteURL]);
            $io->success('STS URL updated successfully to: ' . $newRemoteURL);
        } catch (Throwable $e) {
            LoggerUtility::logError("Error updating STS URL: " . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
            $io->error('Error updating STS URL. Check logs.');
            throw $e;
        }
    }

    // ---- Step 2: Refresh Metadata -----------------------------------------
    $effectiveRemoteURL = $newRemoteURL !== '' ? $newRemoteURL : rtrim((string) $general->getRemoteURL(), '/');
    if ($effectiveRemoteURL === '') {
        echo PHP_EOL
            . "⚠️  No STS URL configured; skipping metadata refresh and lab configuration." . PHP_EOL
            . "   Login as System Admin → System Config, then run: composer setup-sts" . PHP_EOL
            . PHP_EOL . "✅ Setup complete. Configure STS URL before proceeding." . PHP_EOL;
        exit(0);
    }

    $io->section('Refreshing Database Metadata');
    $io->text('Executing metadata sync…');

    $metadataScriptPath = APPLICATION_PATH . "/tasks/remote/sts-metadata-receiver.php";
    if (!file_exists($metadataScriptPath)) {
        echo "❌ Metadata script not found at: $metadataScriptPath" . PHP_EOL;
        echo "Run: php app/tasks/remote/sts-metadata-receiver.php -ft" . PHP_EOL;
        echo "Or:  ./intelis reset-metadata" . PHP_EOL;
        throw new RuntimeException("Metadata script missing");
    }

    $metadataCommand = "php " . escapeshellarg($metadataScriptPath) . " -ft";
    echo "Executing: $metadataCommand" . PHP_EOL . PHP_EOL;

    $bar = MiscUtility::spinnerStart(1, 'Refreshing metadata…', '█', '░', '█', 'cyan');
    $output = [];
    $returnCode = 0;

    $handle = popen($metadataCommand . " 2>&1", 'r');
    if ($handle) {
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line !== false) {
                $trim = trim($line);
                $output[] = $trim;
                if (stripos($trim, 'error') !== false || stripos($trim, 'warning') !== false) {
                    MiscUtility::spinnerPausePrint($bar, function () use ($trim) {
                        echo $trim . PHP_EOL;
                    });
                }
            }
        }
        $returnCode = pclose($handle);
    } else {
        exec($metadataCommand . " 2>&1", $output, $returnCode);
    }

    MiscUtility::spinnerAdvance($bar, 1);
    MiscUtility::spinnerFinish($bar);

    if ($returnCode !== 0) {
        echo PHP_EOL . "Full output:" . PHP_EOL;
        foreach ($output as $l) echo $l . PHP_EOL;
        echo PHP_EOL . "❌ Metadata refresh failed with return code: $returnCode" . PHP_EOL;
        echo "Run manually: php app/tasks/remote/sts-metadata-receiver.php -ft" . PHP_EOL;
        throw new RuntimeException("Metadata refresh failed");
    }
    echo PHP_EOL . "✅ Metadata refresh completed successfully." . PHP_EOL;

    // ---- Step 3: Lab Configuration (fzf only) ------------------------------
    $io->section('Lab Configuration');

    $currentLabId = (int) ($general->getSystemConfig('sc_testing_lab_id') ?? 0);
    $needLabSelection = false;

    // ---- Step 3: Lab Configuration ------------------------------------------
    $io->section('Lab Configuration');

    $currentLabId = (int) ($general->getSystemConfig('sc_testing_lab_id') ?? 0);
    $needLabSelection = false;

    if ($currentLabId > 0) {
        $labDetails = $db->rawQueryOne(
            "SELECT facility_name 
         FROM facility_details 
         WHERE facility_id = ? 
           AND facility_type = 2 
           AND status = 'active'",
            [$currentLabId]
        );

        if ($labDetails) {
            $io->text([
                "Current InteLIS Lab ID: <info>$currentLabId</info>",
                "Lab Name: <info>{$labDetails['facility_name']}</info>",
            ]);

            $ok = $io->confirm('Is this the correct lab?', true);
            $needLabSelection = !$ok;
        } else {
            $io->warning("Current lab ID ($currentLabId) not found in active facilities.");
            $needLabSelection = true;
        }
    } else {
        $io->warning('No lab is currently configured.');
        $needLabSelection = true;
    }

    if ($needLabSelection) {
        $io->section('Selecting Lab');
        maybeInstallFzf();

        $labFetchBar = MiscUtility::spinnerStart(1, 'Fetching available labs…', '█', '░', '█', 'cyan');
        $testingLabs = fetchActiveLabs($db);
        MiscUtility::spinnerAdvance($labFetchBar, 1);
        MiscUtility::spinnerFinish($labFetchBar);

        if (empty($testingLabs)) {
            $io->error('No active testing labs found. Please ensure facilities are properly configured.');
            throw new RuntimeException('No active labs found');
        }

        if (count($testingLabs) === 1) {
            $selectedLab = $testingLabs[0];
            $io->note("Only one lab found, auto-selecting: [ID: {$selectedLab['facility_id']}] {$selectedLab['facility_name']}");
        } else {
            $io->text([
                'You can now pick your lab using fzf:',
                '• Type to search by name',
                '• Use ↑/↓ to move',
                '• Press Enter to confirm',
            ]);

            $selectedLab = pickLabViaFzf($testingLabs);
            if ($selectedLab === null) {
                $io->error('No lab selected. Setup cancelled.');
                exit(0);
            }
        }

        $io->text("Selected Lab: <info>[InteLIS Lab ID: {$selectedLab['facility_id']}] {$selectedLab['facility_name']}</info>");
        $io->text('Updating lab configuration…');

        try {
            $db->where('name', 'sc_testing_lab_id');
            $ok = $db->update('system_config', ['value' => $selectedLab['facility_id']]);
            if ($ok) {
                $io->success('Lab ID updated successfully.');
            } else {
                $io->error('Failed to update lab ID in system configuration.');
                throw new RuntimeException('Failed to update lab ID');
            }
        } catch (Throwable $e) {
            LoggerUtility::logError("Error updating lab ID: " . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
            $io->error('Error updating lab ID. Check logs.');
            throw $e;
        }
    } else {
        $selectedLab = $db->rawQueryOne(
            "SELECT facility_id, facility_name 
         FROM facility_details 
         WHERE facility_id = ? 
           AND facility_type = 2 
           AND status = 'active'",
            [$currentLabId]
        );
        if ($selectedLab) {
            $io->text("Keeping lab: <info>[ID: {$selectedLab['facility_id']}] {$selectedLab['facility_name']}</info>");
        }
    }


    // Fetch once and reuse
    $labFetchBar = MiscUtility::spinnerStart(1, 'Fetching available labs…', '█', '░', '█', 'cyan');
    $testingLabs = fetchActiveLabs($db);
    MiscUtility::spinnerAdvance($labFetchBar, 1);
    MiscUtility::spinnerFinish($labFetchBar);

    if (empty($testingLabs)) {
        echo "❌ No active testing labs found. Please ensure facilities are properly configured." . PHP_EOL;
        throw new RuntimeException("No active labs found");
    }

    if ($needLabSelection) {
        echo PHP_EOL . "=== Lab Selection  ===" . PHP_EOL;
        maybeInstallFzf();

        // If only one lab, auto-select
        if (count($testingLabs) === 1) {
            $selectedLab = $testingLabs[0];
            echo "Only one lab found, auto-selecting: [ID: {$selectedLab['facility_id']}] {$selectedLab['facility_name']}" . PHP_EOL;
        } else {
            $io->section('Lab Selection');
            $io->text([
                'You can now pick your lab using fzf:',
                '• Type to search by name',
                '• Use ↑/↓ to move',
                '• Press Enter to confirm',
            ]);

            $selectedLab = pickLabViaFzf($testingLabs);
            if ($selectedLab === null) {
                echo PHP_EOL . "❌ No lab selected. Setup cancelled." . PHP_EOL;
                exit(0);
            }
        }

        echo PHP_EOL . "ℹ️ Selected Lab: [InteLIS Lab ID: {$selectedLab['facility_id']}] {$selectedLab['facility_name']}" . PHP_EOL;
        echo "Updating lab configuration…" . PHP_EOL;

        try {
            $db->where('name', 'sc_testing_lab_id');
            $ok = $db->update('system_config', ['value' => $selectedLab['facility_id']]);
            if ($ok) {
                echo "✅ Lab ID updated successfully." . PHP_EOL;
            } else {
                echo "❌ Failed to update lab ID in system configuration." . PHP_EOL;
                throw new RuntimeException("Failed to update lab ID");
            }
        } catch (Throwable $e) {
            LoggerUtility::logError("Error updating lab ID: " . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
            echo "❌ Error updating lab ID. Check logs." . PHP_EOL;
            throw $e;
        }
    } else {
        // Show the resolved lab
        $selectedLab = $db->rawQueryOne(
            "SELECT facility_id, facility_name FROM facility_details WHERE facility_id = ? AND facility_type = 2 AND status = 'active'",
            [$currentLabId]
        );
        if ($selectedLab) {
            echo "Keeping lab: [ID: {$selectedLab['facility_id']}] {$selectedLab['facility_name']}" . PHP_EOL;
        }
    }

    // Final cache clear
    (ContainerRegistry::get(FileCacheUtility::class))->clear();

    $io->success('STS setup complete!');

    exit(0);
} catch (Throwable $e) {
    echo PHP_EOL . "❌ Setup failed: " . $e->getMessage() . PHP_EOL . PHP_EOL;
    LoggerUtility::logError("STS setup failure: " . $e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString(),
    ]);
    echo "Please check logs for details." . PHP_EOL;
    exit(1);
}

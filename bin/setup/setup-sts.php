#!/usr/bin/env php
<?php
// bin/setup-sts.php

declare(strict_types=1);

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\ConfigService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Process\Process;


$cliMode = PHP_SAPI === 'cli';

// --- graceful Ctrl+C (if pcntl available)
if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function (): void {
        echo PHP_EOL . "⚠️  Setup cancelled by user." . PHP_EOL;
        exit(CLI\SIGINT); // Standard exit code for SIGINT
    });
}

try {
    require_once __DIR__ . "/../../bootstrap.php";
    ini_set('memory_limit', '-1');
    set_time_limit(0);

    $io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    /** @var ConfigService $configService */
    $configService = ContainerRegistry::get(ConfigService::class);
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    $isLIS = $general->isLISInstance();
    if (!$cliMode) {
        $io->error("STS setup can only be run in CLI mode.");
        exit(CLI\ERROR);
    }
    if (!$isLIS) {
        $io->error("STS setup can only be run for LIS instances.");
        exit(CLI\OK);
    }


    $io->title('STS Configuration Setup');

    // ---- helpers -----------------------------------------------------------
    /**
     * Read user input from CLI. Returns null on EOF.
     */
    function readUserInput(string $prompt = ''): ?string
    {
        echo $prompt;
        $h = fopen('php://stdin', 'r');
        if ($h === false) {
            return null;
        }
        $line = fgets($h);
        fclose($h);
        if ($line === false) {
            return null;
        }
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
        if ($url === '') {
            return $url;
        }

        // If full URL given, test as-is first, then flip scheme
        if (preg_match('/^https?:\/\//i', $url)) {
            $testUrl = rtrim($url, '/');
            if (CommonService::validateStsUrl($testUrl, $labId)) {
                return $testUrl;
            }
            $domain = preg_replace('/^https?:\/\//i', '', $url);
        } else {
            $domain = $url;
        }

        $https = 'https://' . rtrim((string) $domain, '/');
        if (CommonService::validateStsUrl($https, $labId)) {
            return $https;
        }

        $http = 'http://' . rtrim((string) $domain, '/');
        if (CommonService::validateStsUrl($http, $labId)) {
            return $http;
        }

        // default to https (later validation may still fail)
        return $https;
    }

    /**
     * Attempt apt-get install of fzf if missing (only if root).
     */
    function maybeInstallFzf(): void
    {
        if (hasCmd('fzf')) {
            return;
        }
        $isRoot = function_exists('posix_geteuid') ? (posix_geteuid() === 0) : false;
        $hasApt = hasCmd('apt-get');

        if (!$isRoot || !$hasApt) {
            echo "❌ fzf not found. Please install:\n   sudo apt-get update && sudo apt-get install -y fzf\n";
            exit(CLI\ERROR);
        }
        $ans = strtolower((string) (readUserInput("fzf not found. Install now? (y/N): ") ?? ''));
        if ($ans !== 'y' && $ans !== 'yes') {
            echo "❌ fzf required. Aborting." . PHP_EOL;
            exit(CLI\ERROR);
        }
        system('apt-get update -y && apt-get install -y fzf', $rc);
        if ($rc !== 0 || !hasCmd('fzf')) {
            echo "❌ Failed to install fzf." . PHP_EOL;
            exit(CLI\ERROR);
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
            $l['facility_name'] = trim((string) $name);
        }
        unset($l);
        return $labs;
    }

    /**
     * fzf-based picker; returns selected lab or null.
     * Filters by BOTH facility_id and facility_name.
     * @param array<int,array{facility_id:int|string,facility_name:string}> $labs
     */
    function pickLabViaFzf(array $labs): ?array
    {
        $inFile = tempnam(sys_get_temp_dir(), 'labs_in_');
        $outFile = tempnam(sys_get_temp_dir(), 'labs_out_');

        // Keep two clear fields: "ID<TAB>Name"
        $lines = array_map(fn($l): string => $l['facility_id'] . "\t" . $l['facility_name'], $labs);
        file_put_contents($inFile, implode(PHP_EOL, $lines));

        // Show both fields and filter on both (nth=1,2). Keep attached to TTY and write selection via keybind.
        // Optional: a tiny preview to make it clearer.
        $cmd = sprintf(
            'OUT=%s; export OUT; ' .
            'cat %s | fzf --ansi --height=80%% --reverse --border --cycle ' .
            ' --prompt="Select lab > " ' .
            ' --header="Type ID or name • ↑/↓ to move • Enter to select" ' .
            ' --delimiter="\t" --nth=1,2 ' .        // <-- search by BOTH ID and Name
            ' --bind "enter:execute-silent(echo {+} > \"$OUT\")+abort" ' .
            ' --preview \'printf "ID: %%s\nName: %%s\n" "$(echo {} | cut -f1)" "$(echo {} | cut -f2)"\' ' .
            ' --preview-window=down,3,wrap',
            escapeshellarg($outFile),
            escapeshellarg($inFile)
        );

        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(null);
        if (Process::isTtySupported()) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException) {
                // Fallback when TTY cannot be enabled (e.g., running detached).
            }
        }
        $process->run();

        $out = @file_get_contents($outFile);
        MiscUtility::deleteFile($inFile);
        MiscUtility::deleteFile($outFile);

        $out = $out === false ? '' : trim($out);
        if ($out === '' || !str_contains($out, "\t")) {
            return null; // user aborted or nothing selected
        }

        [$id, $name] = explode("\t", $out, 2);
        return [
            'facility_id' => trim($id),
            'facility_name' => trim($name),
        ];
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
        exit(CLI\ERROR);
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
                    MiscUtility::spinnerPausePrint($bar, function () use ($trim): void {
                        echo $trim . PHP_EOL;
                    });
                }
            }
        }
        $returnCode = pclose($handle);
    } else {
        exec("$metadataCommand 2>&1", $output, $returnCode);
    }

    MiscUtility::spinnerAdvance($bar, 1);
    MiscUtility::spinnerFinish($bar);

    if ($returnCode !== 0) {
        echo PHP_EOL . "Full output:" . PHP_EOL;
        foreach ($output as $l)
            echo $l . PHP_EOL;
        $io->text([
            "❌ <error>Metadata refresh failed with return code: $returnCode</error>",
            '<info>Run manually: php app/tasks/remote/sts-metadata-receiver.php -ft</info>',
        ]);
        throw new RuntimeException("Metadata refresh failed");
    }

    $io->text("✅ <success>Metadata refresh completed successfully.</success>");

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

        if ($testingLabs === []) {
            $io->error('No active testing labs found. Please ensure facilities are properly configured.');
            throw new RuntimeException('No active labs found');
        }

        if (count($testingLabs) === 1) {
            $selectedLab = $testingLabs[0];
            $io->note("Only one lab found, auto-selecting: [ID: {$selectedLab['facility_id']}] {$selectedLab['facility_name']}");
        } else {
            $io->text([
                'Please select your lab:',
                '• Type to search by name',
                '• Use ↑/↓ to move',
                '• Press Enter to confirm',
            ]);

            $selectedLab = pickLabViaFzf($testingLabs);
            if ($selectedLab === null) {
                $io->error('No lab selected. Setup cancelled.');
                exit(CLI\ERROR);
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

    // Final cache clear
    (ContainerRegistry::get(FileCacheUtility::class))->clear();

    $io->success('STS setup complete!');

    exit(CLI\OK);
} catch (Throwable $e) {
    $io->error("❌ Setup failed: " . $e->getMessage());
    LoggerUtility::logError("STS setup failure: " . $e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString(),
    ]);
    $io->info("<info>Please check logs for details.</info>");
    exit(CLI\ERROR);
}

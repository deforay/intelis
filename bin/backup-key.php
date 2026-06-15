#!/usr/bin/env php
<?php

// Generate this instance's backup encryption key if missing and save a copy to
// the STS so it can be recovered on a replacement machine.
//
// No-op unless this is a LIS instance, `backup_key_recovery_enabled` is 'yes' (set
// on the STS, synced down), and an STS URL + token are configured. Rides the
// sync-sts chain (after @token, which mints the Bearer token this uses). Safe to
// run repeatedly: it re-verifies the STS still holds the current key by
// fingerprint and only prints the one-time recovery code on the run that first
// creates the key.

require_once __DIR__ . "/../bootstrap.php";

use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\BackupEncryptionService;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

ini_set('memory_limit', -1);
set_time_limit(0);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var BackupEncryptionService $keyService */
$keyService = ContainerRegistry::get(BackupEncryptionService::class);

$cliMode = PHP_SAPI === 'cli';

// Same no-op-on-non-LIS / CLI-only posture as bin/token.php: this runs from
// install/upgrade/cron hooks that must not fail because this is irrelevant here.
if (!$general->isLISInstance()) {
    exit(CLI\OK);
}
if (!$cliMode) {
    exit(CLI\ERROR);
}

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

// Feature flag (defaults 'no'). Until an admin turns this on at the STS, saving
// the key is a no-op and the fleet keeps its current behavior.
if ($general->getGlobalConfig('backup_key_recovery_enabled') !== 'yes') {
    $io->note('Backup key recovery is disabled (backup_key_recovery_enabled != yes) — skipping.');
    exit(CLI\OK);
}

$remoteURL = rtrim((string) $general->getRemoteURL(), '/');
if ($remoteURL === '' || $remoteURL === '0') {
    $io->note('No STS URL configured — skipping backup key recovery.');
    exit(CLI\OK);
}

$stsToken = (string) $general->getSTSToken();
if ($stsToken === '' || $stsToken === '0') {
    $io->note('No STS token yet — skipping backup key recovery (run token generation first).');
    exit(CLI\OK);
}

$labId = $general->getSystemConfig('sc_testing_lab_id');
$instanceId = $general->getInstanceId();

// Generate the key on first run; capture whether this run created it so the
// one-time recovery code is shown exactly once.
$freshlyCreated = !$keyService->localKeyExists();
$backupKey = $keyService->getOrCreateLocalKey();
$fingerprint = $keyService->fingerprint($backupKey);
$keyVersion = $keyService->getKeyVersion();

if ($freshlyCreated) {
    $io->warning('A new backup encryption key was generated for this instance.');
    $io->block(
        [
            'RECOVERY CODE (shown only once — store it somewhere safe and offline):',
            '',
            $backupKey,
            '',
            'This code can restore encrypted backups if the machine and the STS are both unavailable.',
        ],
        'RECOVERY CODE',
        'fg=black;bg=yellow',
        ' ',
        true
    );
}

$tokenURL = "$remoteURL/remote/v2/backup-key-recovery.php";

// The key travels in cleartext at the application layer, protected by TLS + the
// Bearer token; the STS re-encrypts it at rest with its own instance key. (A
// shared app-layer secret is impossible here and unnecessary — the STS must be
// able to read the key to ever return it for recovery.)
$payload = [
    'labId'       => $labId,
    'instanceId'  => $instanceId,
    'keyVersion'  => $keyVersion,
    'fingerprint' => $fingerprint,
    'backupKey'   => $backupKey,
];

try {
    $apiService->setBearerToken($stsToken);

    $jsonResponse = $apiService->post($tokenURL, json_encode($payload), gzip: true);
    if (empty($jsonResponse) || $jsonResponse === '[]') {
        $io->error('Empty response from STS during backup key recovery save.');
        exit(CLI\ERROR);
    }

    $response = JsonUtility::decodeJson($jsonResponse, true);

    if (
        !empty($response['status']) && $response['status'] === 'success'
        && !empty($response['fingerprint'])
        && hash_equals($fingerprint, (string) $response['fingerprint'])
    ) {
        $db->update('s_vlsm_instance', [
            'backup_key_recovery_ready' => 1,
            'backup_key_saved_at'       => DateUtility::getCurrentDateTime(),
        ]);
        $io->success("Backup key saved to STS and verified for recovery (version {$keyVersion}).");
    } else {
        $errMsg = $response['message'] ?? ($response['error'] ?? 'fingerprint mismatch or unexpected response');
        $io->error('Backup key recovery save failed: ' . (is_array($errMsg) ? implode(' | ', $errMsg) : $errMsg));
        exit(CLI\ERROR);
    }
} catch (Throwable $e) {
    LoggerUtility::logError(
        'Error in backup key recovery save: ' . $e->getMessage(),
        [
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString(),
        ]
    );
    $io->error('Error in backup key recovery save. Please check logs for details.');
    exit(CLI\ERROR);
}

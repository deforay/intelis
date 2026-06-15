<?php

/**
 * db-tools configuration - Maps VLSM/InteLIS config to db-tools profiles.
 *
 * This file is read by vendor/bin/db-tools to get database credentials.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Backup directory
$backupDir = BACKUP_PATH . '/db';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

// Main database profile (default)
$profiles = [
    'intelis' => [
        'host' => SYSTEM_CONFIG['database']['host'] ?? 'localhost',
        'port' => (int) (SYSTEM_CONFIG['database']['port'] ?? 3306),
        'database' => SYSTEM_CONFIG['database']['db'] ?? 'vlsm',
        'user' => SYSTEM_CONFIG['database']['username'] ?? 'root',
        'password' => SYSTEM_CONFIG['database']['password'] ?? null,
        'output_dir' => $backupDir,
        'retention' => 7,
    ],
];

// Add interfacing profile if enabled
if (!empty(SYSTEM_CONFIG['interfacing']['enabled']) && !empty(SYSTEM_CONFIG['interfacing']['database']['db'])) {
    $profiles['interfacing'] = [
        'host' => SYSTEM_CONFIG['interfacing']['database']['host'] ?? 'localhost',
        'port' => (int) (SYSTEM_CONFIG['interfacing']['database']['port'] ?? 3306),
        'database' => SYSTEM_CONFIG['interfacing']['database']['db'] ?? 'interfacing',
        'user' => SYSTEM_CONFIG['interfacing']['database']['username'] ?? 'root',
        'password' => SYSTEM_CONFIG['interfacing']['database']['password'] ?? null,
        'output_dir' => $backupDir,
        'retention' => 7,
    ];
}

// Backup encryption (gated, default OFF). When `backup_encryption_enabled` is
// 'yes', db-tools encrypts each dump with this instance's saved backup key, used
// as a fixed passphrase. db-tools >= 3.1.0 honors an explicit encryption_password
// verbatim (3.0.x ignored it), so the key the STS escrows is the one that decrypts
// the backup on a replacement machine. The flag defaults to 'no' (Phase C turns it
// on, per-lab canary first), so this is a no-op until then. See BackupEncryptionService.
try {
    /** @var \App\Services\CommonService $general */
    $general = \App\Registries\ContainerRegistry::get(\App\Services\CommonService::class);
    if ($general->getGlobalConfig('backup_encryption_enabled') === 'yes') {
        /** @var \App\Services\BackupEncryptionService $keyService */
        $keyService = \App\Registries\ContainerRegistry::get(\App\Services\BackupEncryptionService::class);
        $backupKey = $keyService->getLocalKey();
        if (!empty($backupKey)) {
            foreach ($profiles as &$profile) {
                $profile['encryption_password'] = $backupKey;
            }
            unset($profile);
        }
    }
} catch (\Throwable $e) {
    // Never let encryption wiring break plain backups; fall through unencrypted.
    \App\Utilities\LoggerUtility::logError('db-tools backup encryption setup skipped: ' . $e->getMessage());
}

return $profiles;

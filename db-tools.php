<?php

/**
 * db-tools configuration - Maps VLSM/InteLIS config to db-tools profiles.
 *
 * This file is read by vendor/bin/db-tools to get database credentials.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Backup directory
$backupDir = BACKUP_PATH;
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

return $profiles;

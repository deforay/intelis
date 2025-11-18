#!/usr/bin/env php
<?php

require_once __DIR__ . "/../bootstrap.php";

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    exit(0);
}

use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Connect to MySQL "interface" DB


$mysqlConnected = false;
$sqliteConnected = false;

if (!empty(SYSTEM_CONFIG['interfacing']['database']['host']) && !empty(SYSTEM_CONFIG['interfacing']['database']['username'])) {
    $mysqlConnected = true;
    $db->addConnection('interface', SYSTEM_CONFIG['interfacing']['database']);
}

if (!$mysqlConnected) {
    echo "❌ MySQL interface connection not configured.\n";
    exit(CLI\ERROR);
}

// Get SQLite path from config
if (empty(SYSTEM_CONFIG['interfacing']['sqlite3Path'])) {
    echo "❌ SQLite path not set in SYSTEM_CONFIG.\n";
    exit(CLI\ERROR);
}

$sqlitePath = SYSTEM_CONFIG['interfacing']['sqlite3Path'];
if (!file_exists($sqlitePath)) {
    echo "❌ SQLite database not found at: $sqlitePath\n";
    exit(CLI\ERROR);
}

try {
    $sqlite = new PDO("sqlite:$sqlitePath");
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    LoggerUtility::logError("❌ Failed to connect to SQLite: " . $e->getMessage(), [
        'sqlitePath' => $sqlitePath,
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(CLI\ERROR);
}

// Fetch unsynced orders
try {
    $stmt = $sqlite->query("SELECT * FROM orders WHERE mysql_inserted = 0");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    LoggerUtility::logError("❌ Failed to fetch records from SQLite: " . $e->getMessage(), [
        'sqlitePath' => $sqlitePath,
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(CLI\ERROR);
}

if (empty($records)) {
    echo "ℹ️ No records to resync.\n";
    exit(CLI\OK);
}

// Insert into MySQL and update SQLite
foreach ($records as $record) {
    $mysqlRecord = $record;
    unset($mysqlRecord['mysql_inserted'], $mysqlRecord['id']);

    try {
        $inserted = $db->connection('interface')->insert('orders', $mysqlRecord);

        if ($inserted) {
            $update = $sqlite->prepare("UPDATE orders SET mysql_inserted = 1 WHERE id = :id");
            $update->bindValue(':id', $record['id'], PDO::PARAM_INT);
            $update->execute();

            echo "✔ Synced record ID {$record['id']}\n";
        }
    } catch (Throwable $e) {
        LoggerUtility::logError("❌ Failed to sync record ID {$record['id']}: " . $e->getMessage(), [
            'sqlitePath' => $sqlitePath,
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

echo "✅ Resync process completed.\n";

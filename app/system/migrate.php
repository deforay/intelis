#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../../bootstrap.php");

use PhpMyAdmin\SqlParser\Parser;
use App\Registries\ContainerRegistry;

// Ensure the script only runs for VERSION >= 5.2.5
if (version_compare(VERSION, '5.2.5', '<')) {
    exit("This script requires VERSION 5.2.5 or higher. Current version: " . VERSION . "\n");
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

$db->where('name', 'sc_version');
$currentVersion = $db->getValue('system_config', 'value');
$migrationFiles = glob(APPLICATION_PATH . '/../dev/migrations/*.sql');
usort($migrationFiles, 'version_compare');

$options = getopt("yq");  // Parse command line options for -y and -q
$autoContinueOnError = isset($options['y']);  // Set a flag if -y option is provided
$quietMode = isset($options['q']);  // Set a flag if -q option is provided

foreach ($migrationFiles as $file) {
    $version = basename($file, '.sql');
    if (version_compare($version, $currentVersion, '>=')) {
        if (!$quietMode) { // Only output messages if -q option is not provided
            echo "Migrating to version $version...\n";
        }

        $sql_contents = file_get_contents($file);
        $parser = new Parser($sql_contents);

        $db->startTransaction();  // Start a new transaction

        foreach ($parser->statements as $statement) {
            $query = $statement->build();
            $db->rawQuery($query);
            if ($db->getLastErrno()) {
                if (!$quietMode) {  // Only show error messages if -q option is not provided
                    echo "Error executing query: " . $db->getLastError() . "\n";
                }
                if (!$autoContinueOnError) {  // Only prompt user if -y option is not provided
                    echo "Do you want to continue? (y/n): ";
                    $handle = fopen("php://stdin", "r");
                    $response = trim(fgets($handle));
                    fclose($handle);
                    if (strtolower($response) !== 'y') {
                        $db->rollback();  // Rollback the transaction on error
                        exit("Migration aborted by user.\n");
                    }
                }
            }
        }

        if (!$quietMode) { // Only output messages if -q option is not provided
            echo "Migration to version $version completed.\n";
        }

        $db->where('name', 'sc_version')->update('system_config', ['value' => $version]);
        $db->commit();  // Commit the transaction if no error occurred
    }
}

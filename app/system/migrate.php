#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../../bootstrap.php");

use App\Utilities\LoggerUtility;
use PhpMyAdmin\SqlParser\Parser;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

// Ensure the script only runs for VLSM APP VERSION >= 4.5.3
if (version_compare(VERSION, '4.5.3', '<')) {
    exit("This script requires VERSION 4.5.3 or higher. Current version: " . VERSION . "\n");
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$db->where('name', 'sc_version');
$currentVersion = $db->getValue('system_config', 'value');
$migrationFiles = glob(APPLICATION_PATH . '/../dev/migrations/*.sql');

// Extract version numbers and map them to files
$versions = array_map(function ($file) {
    return basename($file, '.sql');
}, $migrationFiles);

// Sort versions
usort($versions, 'version_compare');


$options = getopt("yq");  // Parse command line options for -y and -q
$autoContinueOnError = isset($options['y']);  // Set a flag if -y option is provided

// Only output messages if -q option is not provided
$quietMode = isset($options['q']);  // Set a flag if -q option is provided

if ($quietMode) {
    error_reporting(0);  // Suppress warnings and notices
}

foreach ($versions as $version) {
    $file = APPLICATION_PATH . '/../dev/migrations/' . $version . '.sql';

    if (version_compare($version, $currentVersion, '>=')) {
        //if (!$quietMode) {
        echo "Migrating to version $version...\n";
        //}

        $sql_contents = file_get_contents($file);
        $parser = new Parser($sql_contents);

        $db->beginTransaction();  // Start a new transaction
        $db->rawQuery("SET FOREIGN_KEY_CHECKS = 0;"); // Disable foreign key checks
        $errorOccurred = false;
        foreach ($parser->statements as $statement) {
            try {
                $query = $statement->build();
                $db->rawQuery($query);
                $errorOccurred = false;
            } catch (Exception $e) {

                $message = "Exception : " . $e->getMessage() . PHP_EOL;

                $errorOccurred = true;
                if (!$quietMode) {
                    LoggerUtility::log('error', $message);
                    echo $message;
                }
            }
            if ($db->getLastErrno() > 0 || $errorOccurred) {
                $dbMessage = "Error executing query: " . $db->getLastErrno() . ":" . $db->getLastError() . PHP_EOL . $db->getLastQuery() . PHP_EOL;
                if (!$quietMode) {
                    echo $dbMessage;
                    LoggerUtility::log('error', $dbMessage);
                }

                if (!$autoContinueOnError) {  // Only prompt user if -y option is not provided
                    echo "Do you want to continue? (y/n): ";
                    $handle = fopen("php://stdin", "r");
                    $response = trim(fgets($handle));
                    fclose($handle);
                    if (strtolower($response) !== 'y') {
                        $db->rollbackTransaction();  // Rollback the transaction on error
                        exit("Migration aborted by user.\n");
                    }
                }
            }
        }
        unset($sql_contents, $parser);

        //if (!$quietMode) { // Only output messages if -q option is not provided
        echo "Migration to version $version completed." . PHP_EOL;
        //}

        //$db->where('name', 'sc_version')->update('system_config', ['value' => $version]);
        $db->rawQuery("SET FOREIGN_KEY_CHECKS = 1;"); // Re-enable foreign key checks
        $db->commitTransaction();  // Commit the transaction if no error occurred
    }

    gc_collect_cycles();
}

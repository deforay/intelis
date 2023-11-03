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

$options = getopt("y");  // Parse command line options
$autoContinueOnError = isset($options['y']);  // Set a flag if -y option is provided

foreach ($migrationFiles as $file) {
    $version = basename($file, '.sql');
    if (version_compare($version, $currentVersion, '>=')) {
        $sql_contents = file_get_contents($file);
        $parser = new Parser($sql_contents);

        $db->startTransaction();  // Start a new transaction

        foreach ($parser->statements as $statement) {
            $query = $statement->build();
            $db->rawQuery($query);
            if ($db->getLastErrno()) {
                echo "Error executing query: " . $db->getLastError() . "\n";
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

        $db->where('name', 'sc_version')->update('system_config', ['value' => $version]);
        $db->commit();  // Commit the transaction if no error occurred
    }
}

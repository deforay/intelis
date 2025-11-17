#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . "/../../bootstrap.php";

use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;

$versionFilePath = APPLICATION_PATH . '/system/version.php';
$composerJsonPath = ROOT_PATH . '/composer.json';

// Function to get current version from composer.json
function getComposerVersion($composerJsonPath)
{
    if (!file_exists($composerJsonPath)) {
        return null;
    }

    $composerContent = file_get_contents($composerJsonPath);
    $composerJson = json_decode($composerContent, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($composerJson['version'])) {
        return null;
    }

    return $composerJson['version'];
}

// Get the current major version from composer.json (primary source)
$currentMajorVersion = getComposerVersion($composerJsonPath);

// Function to extract the version number from the version.php file
function getCurrentVersion($versionFilePath): ?string
{
    if (!file_exists($versionFilePath)) {
        return null;
    }

    $versionContent = file_get_contents($versionFilePath);
    if (preg_match("/define\('VERSION', '([\d\.]+)'\);/", $versionContent, $matches)) {
        return $matches[1];
    }
    return null;
}

// Function to update composer.json version
function updateComposerJson($composerJsonPath, $newVersion): bool
{
    if (!file_exists($composerJsonPath)) {
        echo "Warning: composer.json not found at {$composerJsonPath}" . PHP_EOL;
        return false;
    }

    // Get the current content of composer.json
    $composerContent = file_get_contents($composerJsonPath);
    $composerJson = json_decode($composerContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error parsing composer.json: " . json_last_error_msg() . PHP_EOL;
        return false;
    }

    // Extract just the major.minor.patch part for composer.json
    $versionParts = explode('.', (string) $newVersion);
    $composerVersion = implode('.', array_slice($versionParts, 0, 3));

    // Update the version in the composer.json array
    $composerJson['version'] = $composerVersion;

    // Write the updated content back to composer.json with pretty formatting
    $updatedContent = json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($composerJsonPath, $updatedContent);

    echo "Updated composer.json version to " . htmlspecialchars($composerVersion, ENT_QUOTES, 'UTF-8') . PHP_EOL;
    return true;
}

try {
    // Set migrations path
    $migrationsPath = ROOT_PATH . '/sys/migrations/';

    // Get the current version from version.php
    $currentVersion = getCurrentVersion($versionFilePath);

    if ($currentMajorVersion === null) {
        // If composer.json doesn't exist or has no version
        /** @var CommonService $general */
        $general = ContainerRegistry::get(CommonService::class);
        $fallbackVersion = $general->getAppVersion();

        echo "Warning: Could not read version from composer.json, using fallback version: {$fallbackVersion}" . PHP_EOL;
        $currentMajorVersion = $fallbackVersion;
    }

    if ($currentVersion === null) {
        // If version.php does not exist or has issues, initialize it with major.minor.patch.1
        $newVersion = "{$currentMajorVersion}.1";
    } else {
        // Extract the major.minor.patch part and the build number
        $currentVersionParts = explode('.', (string) $currentVersion);

        // Extract the current major.minor.patch version from the file
        $currentVersionCore = implode('.', array_slice($currentVersionParts, 0, 3));

        // Extract the current build number
        $currentBuildNumber = intval(end($currentVersionParts));

        // Ask user for version update preference
        echo "Current version is: {$currentVersion}\n";
        echo "Would you like to:\n";
        echo "1. Change major version (from {$currentVersionCore} to a new major version)\n";
        echo "2. Just increment the current version (from {$currentVersion} to " .
            $currentVersionCore . "." . ($currentBuildNumber + 1) . ")\n";

        $choice = null;
        while ($choice !== '1' && $choice !== '2') {
            echo "Enter your choice (1 or 2): ";
            $choice = trim(fgets(STDIN));
        }

        if ($choice === '1') {
            // User wants to change major version
            echo "Enter the new major version (current is {$currentVersionCore}): ";
            $newMajorVersion = trim(fgets(STDIN));
            // Validate input (check for x.y.z format)
            while (!preg_match('/^\d+\.\d+\.\d+$/', $newMajorVersion)) {
                echo "Invalid format. Please enter in format x.y.z (e.g., 2.0.0): ";
                $newMajorVersion = trim(fgets(STDIN));
            }
            // Validate that new version is greater than current version
            while (version_compare($newMajorVersion, $currentVersionCore, '<=')) {
                echo "New version must be greater than the current version ({$currentVersionCore}). Please enter a higher version: ";
                $newMajorVersion = trim(fgets(STDIN));

                // Re-validate format
                while (!preg_match('/^\d+\.\d+\.\d+$/', $newMajorVersion)) {
                    echo "Invalid format. Please enter in format x.y.z (e.g., 2.0.0): ";
                    $newMajorVersion = trim(fgets(STDIN));
                }
            }
            // When changing major version, start with .0 for the build number
            $newVersion = "{$newMajorVersion}.0";
            // Update composer.json with the new major version
            updateComposerJson($composerJsonPath, $newVersion);
        } elseif ($currentVersionCore !== $currentMajorVersion) {
            // User wants to increment build number
            // If the major.minor.patch version has changed, reset the build number to 1
            $newVersion = "{$currentMajorVersion}.1";
            // Update composer.json with the new version
            updateComposerJson($composerJsonPath, $newVersion);
        } else {
            // Otherwise, increment the build number
            $newVersion = $currentMajorVersion . '.' . ($currentBuildNumber + 1);

            // No need to update composer.json for build number changes
        }
    }

    // Generate the content for version.php
    $versionFileContent = <<<PHP
<?php

// DO NOT MODIFY THIS FILE
// Version is defined in composer.json
// This file is automatically generated by the build process
defined('VERSION')
    || define('VERSION', '$newVersion');

PHP;

    // Write the new version content to version.php
    file_put_contents($versionFilePath, $versionFileContent);

    // Extract the major.minor.patch portion for the migration filename
    $versionParts = explode('.', $newVersion);
    $migrationVersion = implode('.', array_slice($versionParts, 0, min(3, count($versionParts))));
    $migrationFileName = $migrationsPath . $migrationVersion . '.sql';

    // For the previous version migration file
    $currentVersionParts = explode('.', (string) $currentVersion);
    $currentMigrationVersion = implode('.', array_slice($currentVersionParts, 0, min(3, count($currentVersionParts))));
    $currentMigrationFileName = $migrationsPath . $currentMigrationVersion . '.sql';

    // Check if migrations directory exists, create it if it doesn't
    if (!is_dir($migrationsPath)) {
        mkdir($migrationsPath, 0755, true);
    }

    // If this is a major version change and the previous migration file exists,
    // add the END OF VERSION markers to the previous file
    if ($choice === '1' && file_exists($currentMigrationFileName)) {
        $endOfVersionMarker = str_repeat("\n-- END OF VERSION --", 12);
        file_put_contents($currentMigrationFileName, $endOfVersionMarker, FILE_APPEND);
        echo "Added end markers to previous migration file: $currentMigrationFileName" . PHP_EOL;
    }

    // Create the new migration file only if it doesn't already exist
    if (!file_exists($migrationFileName)) {
        // Create migration content with the version update SQL
        $migrationContent = "-- Migration file for version {$migrationVersion}\n-- Created on " . date('Y-m-d H:i:s') . "\n\n\n";
        $migrationContent .= "UPDATE `system_config` SET `value` = '{$migrationVersion}' WHERE `system_config`.`name` = 'sc_version';\n\n";

        // Sanitize migration file path to prevent path traversal
        $realMigrationsPath = realpath($migrationsPath);
        $realMigrationFileName = $realMigrationsPath . DIRECTORY_SEPARATOR . basename($migrationFileName);

        if (!str_starts_with(realpath(dirname($realMigrationFileName)), $realMigrationsPath)) {
            throw new RuntimeException("Invalid migration file path detected.");
        }

        file_put_contents($realMigrationFileName, $migrationContent);
        echo "Created migration file: " . htmlspecialchars($realMigrationFileName) . PHP_EOL;
    }

    echo "version.php has been updated to version " . htmlspecialchars($newVersion) . PHP_EOL;
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}

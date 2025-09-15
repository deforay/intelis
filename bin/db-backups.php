#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../bootstrap.php");

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use Ifsnop\Mysqldump as IMysqldump;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$backupFolder = APPLICATION_PATH . '/../backups';
if (!is_dir($backupFolder)) {
    MiscUtility::makeDirectory($backupFolder);
}
$randomString = MiscUtility::generateRandomString(12);
$sqlFileName = realpath($backupFolder) . DIRECTORY_SEPARATOR . 'vlsm-' . date("dmYHis") . '-' . $randomString . '.sql';

try {

    $hostname = SYSTEM_CONFIG['database']['host'];
    $username = SYSTEM_CONFIG['database']['username'];
    $password = SYSTEM_CONFIG['database']['password'];
    $database = SYSTEM_CONFIG['database']['db'];
    $dump = new IMysqldump\Mysqldump("mysql:host=$hostname;dbname=$database", $username, $password);
    $dump->start($sqlFileName);

    $zip = new ZipArchive();
    $zipStatus = $zip->open("$sqlFileName.zip", ZipArchive::CREATE);
    if ($zipStatus !== true) {
        throw new SystemException(sprintf('Failed to create zip archive. (Status code: %s)', $zipStatus));
    }

    if (!$zip->setPassword($password . $randomString)) {
        throw new SystemException('Set password failed');
    }

    // compress file
    $baseName = basename($sqlFileName);
    if (!$zip->addFile($sqlFileName, $baseName)) {
        throw new SystemException(sprintf('Add file failed: %s', $sqlFileName));
    }

    // encrypt the file with AES-256
    if (!$zip->setEncryptionName($baseName, ZipArchive::EM_AES_256)) {
        throw new SystemException(sprintf('Set encryption failed: %s', $baseName));
    }

    $zip->close();
    unlink($sqlFileName);


    if (SYSTEM_CONFIG['interfacing']['enabled'] === true) {
        $sqlFileName = realpath($backupFolder) . DIRECTORY_SEPARATOR . 'interfacing-' . date("dmYHis") . '-' . $randomString . '.sql';

        $hostname = SYSTEM_CONFIG['interfacing']['database']['host'];
        $username = SYSTEM_CONFIG['interfacing']['database']['username'];
        $password = SYSTEM_CONFIG['interfacing']['database']['password'];
        $database = SYSTEM_CONFIG['interfacing']['database']['db'];
        $dump = new IMysqldump\Mysqldump("mysql:host=$hostname;dbname=$database", $username, $password);
        $dump->start($sqlFileName);

        $zip = new ZipArchive();
        $zipStatus = $zip->open($sqlFileName . ".zip", ZipArchive::CREATE);
        if ($zipStatus !== true) {
            throw new SystemException(sprintf('Failed to create zip archive. (Status code: %s)', $zipStatus));
        }

        if (!$zip->setPassword($password . $randomString)) {
            throw new SystemException('Set password failed');
        }

        // compress file
        $baseName = basename($sqlFileName);
        if (!$zip->addFile($sqlFileName, $baseName)) {
            throw new SystemException(sprintf('Add file failed: %s', $sqlFileName));
        }

        // encrypt the file with AES-256
        if (!$zip->setEncryptionName($baseName, ZipArchive::EM_AES_256)) {
            throw new SystemException(sprintf('Set encryption failed: %s', $baseName));
        }

        $zip->close();
        unlink($sqlFileName);
    }
} catch (Exception $e) {
    LoggerUtility::log('error', $e->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $e->getTraceAsString()
    ]);
}

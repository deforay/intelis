<?php

if (php_sapi_name() == 'cli') {
    require_once(__DIR__ . "/../../bootstrap.php");
} else {
    // only run from command line
    exit(0);
}

use App\Services\CommonService;
use Ifsnop\Mysqldump as IMysqldump;
use App\Registries\ContainerRegistry;


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$backupFolder = APPLICATION_PATH . '/../backups';
if (!is_dir($backupFolder)) {
    mkdir($backupFolder, 0777, true);
}
$randomString = $general->generateRandomString(12);
$sqlFileName = realpath($backupFolder) . DIRECTORY_SEPARATOR . 'vlsm-' . date("dmYHis") . '-' . $randomString . '.sql';

try {

    $hostname = SYSTEM_CONFIG['database']['host'];
    $username = SYSTEM_CONFIG['database']['username'];
    $password = SYSTEM_CONFIG['database']['password'];
    $database = SYSTEM_CONFIG['database']['db'];
    $dump = new IMysqldump\Mysqldump("mysql:host=$hostname;dbname=$database", $username, $password);
    $dump->start($sqlFileName);

    $zip = new ZipArchive();
    $zipStatus = $zip->open($sqlFileName . ".zip", ZipArchive::CREATE);
    if ($zipStatus !== true) {
        throw new RuntimeException(sprintf('Failed to create zip archive. (Status code: %s)', $zipStatus));
    }

    if (!$zip->setPassword($password . $randomString)) {
        throw new RuntimeException('Set password failed');
    }

    // compress file
    $baseName = basename($sqlFileName);
    if (!$zip->addFile($sqlFileName, $baseName)) {
        throw new RuntimeException(sprintf('Add file failed: %s', $fileName));
    }

    // encrypt the file with AES-256
    if (!$zip->setEncryptionName($baseName, ZipArchive::EM_AES_256)) {
        throw new RuntimeException(sprintf('Set encryption failed: %s', $baseName));
    }

    $zip->close();
    unlink($sqlFileName);

    //exec("cd $backupFolder && zip -P $password $baseFileName.zip $baseFileName && rm $baseFileName");


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
            throw new RuntimeException(sprintf('Failed to create zip archive. (Status code: %s)', $zipStatus));
        }

        if (!$zip->setPassword($password . $randomString)) {
            throw new RuntimeException('Set password failed');
        }

        // compress file
        $baseName = basename($sqlFileName);
        if (!$zip->addFile($sqlFileName, $baseName)) {
            throw new RuntimeException(sprintf('Add file failed: %s', $fileName));
        }

        // encrypt the file with AES-256
        if (!$zip->setEncryptionName($baseName, ZipArchive::EM_AES_256)) {
            throw new RuntimeException(sprintf('Set encryption failed: %s', $baseName));
        }

        $zip->close();
        unlink($sqlFileName);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/db-backups.php');
}

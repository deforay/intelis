<?php

require_once(__DIR__ . "/../startup.php");

if (empty($systemConfig['mysqlDump']) || !is_executable($systemConfig['mysqlDump'])) {
    error_log('Please set the mysqldump path in config.');
    exit();
}

$backupFolder = APPLICATION_PATH . '/../backups';
if (!is_dir($backupFolder)) {
    mkdir($backupFolder, 0777, true);
}
$randomString = $general->generateRandomString(6);
$fileName = $backupFolder . DIRECTORY_SEPARATOR . 'vlsm-' . date("dmYHis") . '-' . $randomString . '.sql';

try {
    exec($systemConfig['mysqlDump'] . ' --create-options --user=' . $systemConfig['dbUser'] . ' --password="' . $systemConfig['dbPassword'] . '" --host=' . $systemConfig['dbHost'] . ' --port=' . $systemConfig['dbPort'] . ' --databases ' . $systemConfig['dbName'] . '  > ' . $fileName);

    sleep(2);

    $zip = new ZipArchive();

    $baseName = basename($fileName);

    $zipFile = $backupFolder . DIRECTORY_SEPARATOR . "$baseName.zip";
    if (file_exists($zipFile)) {
        unlink($zipFile);
    }

    $zipStatus = $zip->open($zipFile, ZipArchive::CREATE);
    if ($zipStatus !== true) {
        throw new RuntimeException(sprintf('Failed to create zip archive. (Status code: %s)', $zipStatus));
    }

    $password = md5($systemConfig['dbPassword'] . $randomString);
    if (!$zip->setPassword($password)) {
        throw new RuntimeException('Set password failed');
    }

    // compress file

    if (!$zip->addFile($fileName, $baseName)) {
        throw new RuntimeException(sprintf('Add file failed: %s', $fileName));
    }

    // encrypt the file with AES-256
    if (!$zip->setEncryptionName($baseName, ZipArchive::EM_AES_256)) {
        throw new RuntimeException(sprintf('Set encryption failed: %s', $baseName));
    }

    $zip->close();
    unlink($fileName);
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/db-backups.php');
}

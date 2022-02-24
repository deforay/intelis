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

$filename = $backupFolder . DIRECTORY_SEPARATOR . 'vlsm-db-backup-' . date("dmYHis") . '-' . rand() . '.sql';

try {
    exec($systemConfig['mysqlDump'] . ' --create-options --user=' . $systemConfig['dbUser'] . ' --password="' . $systemConfig['dbPassword'] . '" --host=' . $systemConfig['dbHost'] . ' --port=' . $systemConfig['dbPort'] . ' --databases ' . $systemConfig['dbName'] . '  > ' . $filename);
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/db-backups.php');
}

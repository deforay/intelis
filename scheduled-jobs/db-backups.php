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
$baseFileName = 'vlsm-' . date("dmYHis") . '-' . $randomString . '.sql';

$password = md5($systemConfig['dbPassword'] . $randomString);

try {
    exec("cd $backupFolder && " . $systemConfig['mysqlDump'] . ' --create-options --user=' . $systemConfig['dbUser'] . ' --password="' . $systemConfig['dbPassword'] . '" --host=' . $systemConfig['dbHost'] . ' --port=' . $systemConfig['dbPort'] . ' --databases ' . $systemConfig['dbName'] . '  > ' . $baseFileName);

    exec("cd $backupFolder && zip -P $password $baseFileName.zip $baseFileName && rm $baseFileName");

} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/db-backups.php');
}

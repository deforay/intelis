<?php

require_once(__DIR__ . "/../startup.php");

if (empty(SYSTEM_CONFIG['mysqlDump']) || !is_executable(SYSTEM_CONFIG['mysqlDump'])) {
    error_log('Please set the mysqldump path in config.');
    exit();
}

$backupFolder = APPLICATION_PATH . '/../backups';
if (!is_dir($backupFolder)) {
    mkdir($backupFolder, 0777, true);
}
$randomString = $general->generateRandomString(6);
$baseFileName = 'vlsm-' . date("dmYHis") . '-' . $randomString . '.sql';
$password = md5(SYSTEM_CONFIG['dbPassword'] . $randomString);

try {
    exec("cd $backupFolder && " . SYSTEM_CONFIG['mysqlDump'] . ' --create-options --user=' . SYSTEM_CONFIG['dbUser'] . ' --password="' . SYSTEM_CONFIG['dbPassword'] . '" --host=' . SYSTEM_CONFIG['dbHost'] . ' --port=' . SYSTEM_CONFIG['dbPort'] . ' --databases ' . SYSTEM_CONFIG['dbName'] . '  > ' . $baseFileName);

    exec("cd $backupFolder && zip -P $password $baseFileName.zip $baseFileName && rm $baseFileName");

    if (isset(SYSTEM_CONFIG['interfacing']['enabled']) && SYSTEM_CONFIG['interfacing']['enabled'] == true) {
        $baseFileName = 'interfacing-' . date("dmYHis") . '-' . $randomString . '.sql';
        $password = md5(SYSTEM_CONFIG['interfacing']['dbPassword'] . $randomString);
        exec("cd $backupFolder && " . SYSTEM_CONFIG['mysqlDump'] . ' --create-options --user=' . SYSTEM_CONFIG['interfacing']['dbUser'] . ' --password="' . SYSTEM_CONFIG['interfacing']['dbPassword'] . '" --host=' . SYSTEM_CONFIG['interfacing']['dbHost'] . ' --port=' . SYSTEM_CONFIG['interfacing']['dbPort'] . ' --databases ' . SYSTEM_CONFIG['interfacing']['dbName'] . '  > ' . $baseFileName);
        exec("cd $backupFolder && zip -P $password $baseFileName.zip $baseFileName && rm $baseFileName");
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/db-backups.php');
}

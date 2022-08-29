<?php


require_once(__DIR__ . "/../startup.php");

use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

$sftp = null;
if (!empty(SYSTEM_CONFIG['sftp']['host'])) {

    if (!empty(SYSTEM_CONFIG['sftp']['privateKey'])) {
        $password = PublicKeyLoader::load(file_get_contents(SYSTEM_CONFIG['sftp']['privateKey']), SYSTEM_CONFIG['sftp']['privateKeyPassphrase']);
    } else {
        $password = SYSTEM_CONFIG['sftp']['password'];
    }

    if (!empty($password) && $password !== false) {
        $sftp = new SFTP(SYSTEM_CONFIG['sftp']['host'], SYSTEM_CONFIG['sftp']['port']);

        if (!$sftp->login(SYSTEM_CONFIG['sftp']['username'], $password)) {
            error_log('Please provide proper SFTP settings.');
            $sftp = null;
        }
    }
}

if (empty(SYSTEM_CONFIG['mysqlDump']) || !is_executable(SYSTEM_CONFIG['mysqlDump'])) {
    error_log('Please set the mysqldump path in config.');
    exit();
}

$backupFolder = APPLICATION_PATH . '/../backups';
if (!is_dir($backupFolder)) {
    mkdir($backupFolder, 0777, true);
}
$randomString = $general->generateRandomString(12);
$baseFileName = 'vlsm-' . date("dmYHis") . '-' . $randomString . '.sql';
$password = hash('sha1', SYSTEM_CONFIG['dbPassword'] . $randomString);

try {
    exec("cd $backupFolder && " . SYSTEM_CONFIG['mysqlDump'] . ' --create-options --user=' . SYSTEM_CONFIG['dbUser'] . ' --password="' . SYSTEM_CONFIG['dbPassword'] . '" --host=' . SYSTEM_CONFIG['dbHost'] . ' --port=' . SYSTEM_CONFIG['dbPort'] . ' --databases ' . SYSTEM_CONFIG['dbName'] . '  > ' . $baseFileName);

    exec("cd $backupFolder && zip -P $password $baseFileName.zip $baseFileName && rm $baseFileName");

    if (!empty($sftp) && $sftp !== false) {
        $sftp->chdir(SYSTEM_CONFIG['sftp']['path']);
        $sftp->put("$baseFileName.zip", file_get_contents($backupFolder . "/" . "$baseFileName.zip"));
    }

    if (isset(SYSTEM_CONFIG['interfacing']['enabled']) && SYSTEM_CONFIG['interfacing']['enabled'] == true) {
        $baseFileName = 'interfacing-' . date("dmYHis") . '-' . $randomString . '.sql';
        $password = hash('sha1', SYSTEM_CONFIG['interfacing']['dbPassword'] . $randomString);
        exec("cd $backupFolder && " . SYSTEM_CONFIG['mysqlDump'] . ' --create-options --user=' . SYSTEM_CONFIG['interfacing']['dbUser'] . ' --password="' . SYSTEM_CONFIG['interfacing']['dbPassword'] . '" --host=' . SYSTEM_CONFIG['interfacing']['dbHost'] . ' --port=' . SYSTEM_CONFIG['interfacing']['dbPort'] . ' --databases ' . SYSTEM_CONFIG['interfacing']['dbName'] . '  > ' . $baseFileName);
        exec("cd $backupFolder && zip -P $password $baseFileName.zip $baseFileName && rm $baseFileName");
        if (!empty($sftp) && $sftp !== false) {
            $sftp->chdir(SYSTEM_CONFIG['sftp']['path']);
            $sftp->put("$baseFileName.zip", file_get_contents($backupFolder . "/" . "$baseFileName.zip"));
        }
    }
} catch (\Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/db-backups.php');
}

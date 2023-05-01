<?php

if (php_sapi_name() == 'cli') {
    require_once(__DIR__ . "/../../bootstrap.php");
} else {
    // only run from command line
    exit(0);
}

use App\Registries\ContainerRegistry;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;
use App\Services\CommonService;


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

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
$password = hash('sha1', SYSTEM_CONFIG['database']['password'] . $randomString);

try {
    exec("cd $backupFolder && " . SYSTEM_CONFIG['mysqlDump'] . ' --create-options --user=' . SYSTEM_CONFIG['database']['username'] . ' --password="' . SYSTEM_CONFIG['database']['password'] . '" --host=' . SYSTEM_CONFIG['database']['host'] . ' --port=' . SYSTEM_CONFIG['database']['port'] . ' --databases ' . SYSTEM_CONFIG['database']['db'] . '  > ' . $baseFileName);

    exec("cd $backupFolder && zip -P $password $baseFileName.zip $baseFileName && rm $baseFileName");

    if (!empty($sftp)) {
        $sftp->chdir(SYSTEM_CONFIG['sftp']['path']);
        $sftp->put("$baseFileName.zip", file_get_contents($backupFolder . "/" . "$baseFileName.zip"));
    }

    if (isset(SYSTEM_CONFIG['interfacing']['enabled']) && SYSTEM_CONFIG['interfacing']['enabled']) {
        $baseFileName = 'interfacing-' . date("dmYHis") . '-' . $randomString . '.sql';
        $password = hash('sha1', SYSTEM_CONFIG['interfacing']['database']['password'] . $randomString);
        exec("cd $backupFolder && " . SYSTEM_CONFIG['mysqlDump'] . ' --create-options --user=' . SYSTEM_CONFIG['interfacing']['database']['username'] . ' --password="' . SYSTEM_CONFIG['interfacing']['database']['password'] . '" --host=' . SYSTEM_CONFIG['interfacing']['database']['host'] . ' --port=' . SYSTEM_CONFIG['interfacing']['database']['port'] . ' --databases ' . SYSTEM_CONFIG['interfacing']['database']['db'] . '  > ' . $baseFileName);
        exec("cd $backupFolder && zip -P $password $baseFileName.zip $baseFileName && rm $baseFileName");
        if (!empty($sftp)) {
            $sftp->chdir(SYSTEM_CONFIG['sftp']['path']);
            $sftp->put("$baseFileName.zip", file_get_contents($backupFolder . "/" . "$baseFileName.zip"));
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/db-backups.php');
}

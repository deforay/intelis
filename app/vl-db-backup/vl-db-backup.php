<?php

require_once(dirname(__FILE__) . "/../../startup.php");

define("BACKUP_PATH", "/../db-backup");

$dbUsername = SYSTEM_CONFIG['dbUser'];
$dbPassword = SYSTEM_CONFIG['dbPassword'];
$dbName = SYSTEM_CONFIG['dbName'];
$dbHost = SYSTEM_CONFIG['dbHost'];
$mysqlDumpPath = SYSTEM_CONFIG['mysqlDump'];

$folderPath = BACKUP_PATH . DIRECTORY_SEPARATOR;

if (!file_exists($folderPath) && !is_dir($folderPath)) {
    mkdir($folderPath);
}
$currentDate = date("d-m-Y-H-i-s");
$file = $folderPath . 'vlsm-db-backup-' . $currentDate . '.sql';
$command = sprintf("$mysqlDumpPath -h %s -u %s --password='%s' -d %s --skip-no-data > %s", $dbHost, $dbUsername, $dbPassword, $dbName, $file);
exec($command);

$days = 30;
if (is_dir($folderPath)) {
    $dh = opendir($folderPath);
    while (($oldFileName = readdir($dh)) !== false) {
        if ($oldFileName == 'index.php' || $oldFileName == "." || $oldFileName == ".." || $oldFileName == "") {
            continue;
        }
        if (time() - filemtime($file) > (86400) * $days) {
            unlink($file);
        }
    }
    closedir($dh);
}

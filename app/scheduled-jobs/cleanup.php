<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../../bootstrap.php");

use App\Services\CommonService;


$cleanup = array(
    APPLICATION_PATH . DIRECTORY_SEPARATOR .  'backups',
    WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary',
);

$general = new CommonService();

$durationToDelete = 180 * 86400; // 180 days

foreach ($cleanup as $folder) {
    if (file_exists($folder)) {
        foreach (new DirectoryIterator($folder) as $fileInfo) {
            if (
                !$fileInfo->isDot()
                && !$fileInfo->getFilename() == 'index.php'
                && (time() - $fileInfo->getCTime() >= $durationToDelete)
            ) {
                if ($fileInfo->isFile() || $fileInfo->isLink()) {
                    unlink($fileInfo->getRealPath());
                } else if ($fileInfo->isDir()) {
                    $general->removeDirectory($fileInfo->getRealPath());
                }
            }
        }
    }
}

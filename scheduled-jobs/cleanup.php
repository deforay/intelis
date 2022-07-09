<?php

require_once(__DIR__ . "/../startup.php");

$cleanup = array(
    APPLICATION_PATH . '/../backups',
    APPLICATION_PATH . 'temporary',
);

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

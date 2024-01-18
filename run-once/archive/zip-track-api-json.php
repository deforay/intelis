<?php

require_once(__DIR__ . '/../bootstrap.php');

$folderPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api';







$start = microtime(true);

$total_files = 0;

$files = glob($folderPath . DIRECTORY_SEPARATOR . 'requests' . DIRECTORY_SEPARATOR . '*.{json}', GLOB_BRACE);

foreach ($files as $path) {


    $total_files++;
    //echo basename($path). PHP_EOL; continue;
    $zip = new ZipArchive();
    if ($zip->open($path . '.zip', (ZipArchive::CREATE | ZipArchive::OVERWRITE)) === true) {
        $zip->addFromString(basename($path), file_get_contents($path));
        //$zip->close();
        //unlink($path);
    }
}


$files = glob($folderPath . DIRECTORY_SEPARATOR . 'responses' . DIRECTORY_SEPARATOR . '*.{json}', GLOB_BRACE);

foreach ($files as $path) {


    $total_files++;
    //echo basename($path). PHP_EOL; continue;
    $zip = new ZipArchive();
    if ($zip->open($path . '.zip', (ZipArchive::CREATE | ZipArchive::OVERWRITE)) === true) {
        $zip->addFromString(basename($path), file_get_contents($path));
        //$zip->close();
        //unlink($path);
    }
}
echo "Memory peak usage: " . (memory_get_peak_usage(true) / 1024 / 1024) . " MiB" . PHP_EOL;
echo "Total number of files: " . $total_files . PHP_EOL;
echo "Completed in: ", microtime(true) - $start, " seconds" . PHP_EOL . PHP_EOL;

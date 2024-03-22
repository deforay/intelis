<?php


// only run from command line
if (php_sapi_name() === 'cli') {
    require_once(__DIR__ . "/../../bootstrap.php");
}




use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;

if (isset($_SESSION['instance'])) {
    unset($_SESSION['instance']);
}

echo (ContainerRegistry::get(FileCacheUtility::class))->clear();

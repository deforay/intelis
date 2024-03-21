<?php

use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;

if (isset($_SESSION['instance'])) {
    unset($_SESSION['instance']);
}

echo (ContainerRegistry::get(FileCacheUtility::class))->clear();

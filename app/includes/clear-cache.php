<?php

use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;

unset($_SESSION['instance']);

echo (ContainerRegistry::get(FileCacheUtility::class))->clear();

<?php

use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;

echo (ContainerRegistry::get(FileCacheUtility::class))->clear();

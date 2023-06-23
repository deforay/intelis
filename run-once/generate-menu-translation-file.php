<?php

use App\Services\AppMenuService;
use App\Registries\ContainerRegistry;

require_once(__DIR__ . '/../bootstrap.php');

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var AppMenuService $appMenuService */
$appMenuService = ContainerRegistry::get(AppMenuService::class);

$menuItems = $appMenuService->getMenuDisplayTexts();
$menuItems = array_unique($menuItems);

ob_start();
echo "<?php\n\n";
echo "// SYSTEM GENERATED FILE. DO NOT EDIT.\n\n";
echo "// THIS FILE IS USED TO GENERATE THE MENU TRANSLATION.\n\n";
foreach ($menuItems as $item) {
    echo '_("' . addslashes($item) . "\");\n";
}

// Get the content of the output buffer and write it to the file
$fileContent = ob_get_clean();
file_put_contents(APPLICATION_PATH . "/system/menu.php", $fileContent);

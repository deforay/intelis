<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}


require_once __DIR__ . "/../../bootstrap.php";

include_once(ROOT_PATH . DIRECTORY_SEPARATOR . 'fix-scripts/app-menu.php');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// unset global config cache so that it can be reloaded with new values
(ContainerRegistry::get(FileCacheUtility::class))->delete('app_global_config');

$formId = (int) $general->getGlobalConfig('vl_form');

// Get the country code from the command line argument (if provided)
$countryId = isset($argv[1]) ? intval($argv[1]) : null;

// Check if the formId matches the countryId
if ($countryId !== null && $formId !== $countryId) {
    echo "The system formId ($formId) does not match the provided countryId ($countryId).\n";
    echo "Do you still want to proceed? (y/n): ";
    $confirmation = trim(fgets(STDIN));

    if (strtolower($confirmation) !== 'y') {
        echo "Translation process aborted.\n";
        exit;
    }
}

// Common Metadata
$tablesToTranslate = [
    's_app_menu' => [
        'name_column' => 'display_text',
        'where_condition' => "status = 'active'",
    ],
    'r_sample_status' => [
        'name_column' => 'status_name',
        'where_condition' => "status = 'active'",
    ],
    'privileges' => [
        'name_column' => 'display_name',
        'where_condition' => null,
    ],
    'resources' => [
        'name_column' => 'display_name',
        'where_condition' => null,
    ]
];

// Country-specific Metadata
$countrySpecificTablesToTranslate = [
    'r_vl_test_reasons' => [
        'name_column' => 'test_reason_name',
        'where_condition' => "test_reason_status = 'active'",
    ],
    'r_vl_results' => [
        'name_column' => 'result',
        'where_condition' => "status = 'active'",
    ],
    'r_vl_sample_rejection_reasons' => [
        'name_column' => 'rejection_reason_name',
        'where_condition' => "rejection_reason_status = 'active'",
    ],
    'r_vl_sample_type' => [
        'name_column' => 'sample_name',
        'where_condition' => "status = 'active'",
    ],
];

// Generate translation file for common metadata
generateTranslationFile($tablesToTranslate, APPLICATION_PATH . "/system/translate-strings.php");

// Generate country-specific translation file if country code is provided
if ($countryId !== null) {
    generateTranslationFile($countrySpecificTablesToTranslate, APPLICATION_PATH . "/system/translate-strings-country-$countryId.php", $countryId);
}

function generateTranslationFile($tablesToTranslate, $filePath, $countryId = null): void
{
    global $db;

    ob_start();
    echo "<?php\n\n";
    echo "// SYSTEM GENERATED FILE. DO NOT EDIT.\n\n";
    echo "// THIS FILE IS USED TO GENERATE THE STRING TRANSLATIONS";
    if ($countryId !== null) {
        echo " FOR COUNTRY ID: $countryId";
    }
    echo ".\n\n";

    $translatableStrings = [];
    foreach ($tablesToTranslate as $tableName => $tableInfo) {
        if (!empty($tableInfo['where_condition'])) {
            $db->where($tableInfo['where_condition']);
        }
        $result = $db->setQueryOption('DISTINCT')->getValue($tableName, $tableInfo['name_column'], null);
        foreach ($result as $string) {
            $translatableStrings[] = (string) $string;
        }
    }

    // Make $translatableStrings unique and sort it
    $translatableStrings = array_unique($translatableStrings);

    foreach ($translatableStrings as $string) {
        if (!empty($string)) {
            echo '_translate("' . $string . "\");\n";
        }
    }

    // Get the content of the output buffer and write it to the file
    $fileContent = ob_get_clean();
    file_put_contents($filePath, $fileContent);
}

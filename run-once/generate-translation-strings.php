<?php

use App\Registries\ContainerRegistry;

require_once(__DIR__ . '/../bootstrap.php');

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    ],
    // 'r_generic_sample_rejection_reasons' => [
    //     'name_column' => 'rejection_reason_name',
    //     'where_condition' => "rejection_reason_status = 'active'",
    // ],
    // 'r_generic_sample_types' => [
    //     'name_column' => 'sample_type_name',
    //     'where_condition' => "sample_type_status = 'active'",
    // ],
    // 'r_generic_symptoms' => [
    //     'name_column' => 'symptom_name',
    //     'where_condition' => "symptom_status = 'active'",
    // ],
    // 'r_generic_test_categories' => [
    //     'name_column' => 'test_category_name',
    //     'where_condition' => "test_category_status = 'active'",
    // ],
    // 'r_generic_test_failure_reasons' => [
    //     'name_column' => 'test_failure_reason',
    //     'where_condition' => "test_failure_reason_status = 'active'",
    // ],
    // 'r_generic_test_methods' => [
    //     'name_column' => 'test_method_name',
    //     'where_condition' => "test_method_status = 'active'",
    // ],
    // 'r_generic_test_reasons' => [
    //     'name_column' => 'test_reason',
    //     'where_condition' => "test_reason_status = 'active'",
    // ],
    // 'r_generic_test_result_units' => [
    //     'name_column' => 'unit_name',
    //     'where_condition' => "unit_status = 'active'",
    // ],
];


ob_start();
echo "<?php\n\n";
echo "// SYSTEM GENERATED FILE. DO NOT EDIT.\n\n";
echo "// THIS FILE IS USED TO GENERATE THE STRING TRANSLATIONS.\n\n";


$translatableStrings = [];
foreach ($tablesToTranslate as $tableName => $tableInfo) {

    if (!empty($tableInfo['where_condition'])) {
        $db->where($tableInfo['where_condition']);
    }
    $result = $db->getValue($tableName, $tableInfo['name_column'], null);

    foreach ($result as $string) {
        $translatableStrings[] = addslashes($string);
    }
}
// make $translatableStrings unique and sort it
$translatableStrings = array_unique($translatableStrings);

foreach ($translatableStrings as $string) {
    echo '_("' . $string . "\");\n";
}

// Get the content of the output buffer and write it to the file
$fileContent = ob_get_clean();
file_put_contents(APPLICATION_PATH . "/system/translate-strings.php", $fileContent);

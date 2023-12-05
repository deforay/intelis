<?php


if (php_sapi_name() == 'cli') {
    require_once(__DIR__ . "/../../../bootstrap.php");
}

use App\Services\ApiService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$data = [];

// if forceSync is set as true, we will drop and create tables on VL Dashboard DB
$data['forceSync'] = false;


$referenceTables = [
    'facility_details',
    'geographical_divisions',
    'instrument_machines',
    'instruments'
];

if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
    $vlTables = [
        'r_vl_sample_type',
        'r_vl_test_reasons',
        'r_vl_art_regimen',
        'r_vl_sample_rejection_reasons'
    ];

    $referenceTables = array_merge($referenceTables, $vlTables);
}


if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
    $eidTables = [
        //'r_eid_results',
        'r_eid_sample_rejection_reasons',
        'r_eid_sample_type',
        //'r_eid_test_reasons',
    ];
    $referenceTables = array_merge($referenceTables, $eidTables);
}


if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
    $covid19Tables = [
        'r_covid19_comorbidities',
        'r_covid19_sample_rejection_reasons',
        'r_covid19_sample_type',
        'r_covid19_symptoms',
        'r_covid19_test_reasons',
    ];
    $referenceTables = array_merge($referenceTables, $covid19Tables);
}

if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) {
    $hepatitisTables = [
        //'r_covid19_results',
        'r_hepatitis_sample_rejection_reasons',
        'r_hepatitis_sample_type',
        'r_hepatitis_results',
        'r_hepatitis_risk_factors',
        'r_hepatitis_test_reasons',
    ];
    $referenceTables = array_merge($referenceTables, $hepatitisTables);
}

try {

    foreach ($referenceTables as $table) {
        if ($data['forceSync']) {
            $createResult = $db->rawQueryOne("SHOW CREATE TABLE `$table`");
            $data[$table]['tableStructure'] = "SET FOREIGN_KEY_CHECKS=0;" . PHP_EOL;
            $data[$table]['tableStructure'] .= "ALTER TABLE `$table` DISABLE KEYS ;" . PHP_EOL;
            $data[$table]['tableStructure'] .= "DROP TABLE IF EXISTS `$table`;" . PHP_EOL;
            $data[$table]['tableStructure'] .= $createResult['Create Table'] . ";" . PHP_EOL;
            $data[$table]['tableStructure'] .= "ALTER TABLE `$table` ENABLE KEYS ;" . PHP_EOL;
            $data[$table]['tableStructure'] .= "SET FOREIGN_KEY_CHECKS=1;" . PHP_EOL;
        }
        $data[$table]['lastModifiedTime'] = $general->getLastModifiedDateTime($table);
        $data[$table]['tableData'] = $db->get($table);
    }



    $currentDate = DateUtility::getCurrentDateTime();


    $filename = 'reference-data-' . $currentDate . '.json';
    $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
    fwrite($fp, json_encode($data));
    fclose($fp);


    $vldashboardUrl = $general->getGlobalConfig('vldashboard_url');
    $url = rtrim((string) $vldashboardUrl, "/") . "/api/vlsm-reference-tables";

    $params = [
        [
            'name' => 'api-version',
            'contents' => 'v2'
        ],
        [
            'name' => 'source',
            'contents' => ($general->getSystemConfig('sc_user_type') == 'remoteuser') ? 'STS' : 'LIS'
        ],
        [
            'name' => 'labId',
            'contents' => $general->getSystemConfig('sc_testing_lab_id') ?? null
        ]
    ];

    $response  = $apiService->postFile($url, 'referenceFile', TEMP_PATH . DIRECTORY_SEPARATOR . $filename, $params);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

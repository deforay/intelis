<?php


$cliMode = php_sapi_name() === 'cli';
if ($cliMode) {
    require_once(__DIR__ . "/../../bootstrap.php");
}

use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$smartConnectURL = $general->getGlobalConfig('vldashboard_url');

if (empty($smartConnectURL)) {
    echo "VL Dashboard URL not set";
    exit(0);
}

$data = [];

// if forceSync is set as true, we will drop and create tables on VL Dashboard DB
$data['forceSync'] = false;

$lastUpdatedOn = $db->getValue('s_vlsm_instance', 'last_vldash_sync');


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
        if ($data['forceSync'] === true) {
            $createResult = $db->rawQueryOne("SHOW CREATE TABLE `$table`");
            $data[$table]['tableStructure'] = "SET FOREIGN_KEY_CHECKS=0;" . PHP_EOL;
            $data[$table]['tableStructure'] .= "ALTER TABLE `$table` DISABLE KEYS ;" . PHP_EOL;
            $data[$table]['tableStructure'] .= "DROP TABLE IF EXISTS `$table`;" . PHP_EOL;
            $data[$table]['tableStructure'] .= $createResult['Create Table'] . ";" . PHP_EOL;
            $data[$table]['tableStructure'] .= "ALTER TABLE `$table` ENABLE KEYS ;" . PHP_EOL;
            $data[$table]['tableStructure'] .= "SET FOREIGN_KEY_CHECKS=1;" . PHP_EOL;
        }
        $data[$table]['lastModifiedTime'] = $general->getLastModifiedDateTime($table);


        if (!empty($lastUpdatedOn)) {
            $db->where('updated_datetime', $lastUpdatedOn, ">");
        }
        $db->orderBy("updated_datetime", "ASC");
        $data[$table]['tableData'] = $db->get($table);
    }

    $dataToSync = [];
    $dataToSync['timestamp'] = !empty($lastUpdatedOn) ? strtotime((string) $lastUpdatedOn) : time();
    $dataToSync['data'] = $data;

    $currentDate = DateUtility::getCurrentDateTime();

    $filename = 'reference-data-' . $currentDate . '.json';
    $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
    fwrite($fp, json_encode($dataToSync));
    fclose($fp);


    $url = rtrim((string) $smartConnectURL, "/") . "/api/vlsm-metadata";

    $params = [
        [
            'name' => 'api-version',
            'contents' => 'v2'
        ],
        [
            'name' => 'source',
            'contents' => ($general->isSTSInstance()) ? 'STS' : 'LIS'
        ],
        [
            'name' => 'labId',
            'contents' => $general->getSystemConfig('sc_testing_lab_id') ?? null
        ]
    ];

    $response  = $apiService->postFile($url, 'referenceFile', TEMP_PATH . DIRECTORY_SEPARATOR . $filename, $params);

    unlink(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);


    $latestDateTime = null;

    foreach ($referenceTables as $table) {
        // Build the SQL query to fetch the latest updated_datetime for each table
        $query = "SELECT MAX(updated_datetime) AS latest_update FROM `$table`";
        // Execute the query. Make sure to adjust this line to use your actual method for executing the query.
        $result = $db->rawQueryOne($query); // This method might vary depending on your DatabaseService class.
        // Extract the latest_update value from the result.
        $tableLatestDateTime = $result['latest_update'];

        // Update $latestDateTime if this table's latest update is more recent.
        if (!empty($tableLatestDateTime) && (is_null($latestDateTime) || $tableLatestDateTime > $latestDateTime)) {
            $latestDateTime = $tableLatestDateTime;
        }
    }

    $data = [
        'last_vldash_sync' => $latestDateTime ?? DateUtility::getCurrentDateTime()
    ];

    $db->update('s_vlsm_instance', $data);
} catch (Exception $exc) {
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $exc->getTraceAsString(),
    ]);
}

<?php


$cliMode = PHP_SAPI === 'cli';
if ($cliMode) {
    require_once(__DIR__ . "/../../bootstrap.php");
}

use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
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

$output = MiscUtility::console();

$smartConnectURL = $general->getGlobalConfig('vldashboard_url');

if (empty($smartConnectURL)) {
    $output->writeln("<error> Smart Connect URL not set </error>");
    exit(0);
}

$baseUrl = rtrim((string) $smartConnectURL, "/");

$data = [];

// if forceSync is set as true, we will drop and create tables on VL Dashboard DB
$options = getopt("f", ["force"]);
$data['forceSync'] = isset($options['f']) || isset($options['force']);

$output->writeln("<info>Smart Connect Metadata Sync</info>");
if ($data['forceSync']) {
    $output->writeln("<comment>Force sync enabled — tables will be dropped and recreated</comment>");
}

$lastUpdatedOn = $data['forceSync'] ? null : $db->getValue('s_vlsm_instance', 'last_vldash_sync');


$metadataTables = [
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

    $metadataTables = [...$metadataTables, ...$vlTables];
}


if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
    $eidTables = [
        //'r_eid_results',
        'r_eid_sample_rejection_reasons',
        'r_eid_sample_type',
        //'r_eid_test_reasons',
    ];
    $metadataTables = [...$metadataTables, ...$eidTables];
}


if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
    $covid19Tables = [
        'r_covid19_comorbidities',
        'r_covid19_sample_rejection_reasons',
        'r_covid19_sample_type',
        'r_covid19_symptoms',
        'r_covid19_test_reasons',
    ];
    $metadataTables = [...$metadataTables, ...$covid19Tables];
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
    $metadataTables = [...$metadataTables, ...$hepatitisTables];
}

$transactionId = MiscUtility::generateULID();

try {

    $healthUrl = $baseUrl . "/api/health";

    $output->write("Checking connectivity... ");
    if ($apiService->checkConnectivity($healthUrl) !== true) {
        $output->writeln("<error>FAILED</error>");
        LoggerUtility::log("error", "Unable to connect to Smart Connect health endpoint", [
            'file' => __FILE__,
            'line' => __LINE__,
            'url' => $healthUrl,
        ]);
        exit(0);
    }
    $output->writeln("<info>OK</info>");

    $url = "$baseUrl/api/vlsm-metadata";

    $bar = MiscUtility::spinnerStart(count($metadataTables), 'Collecting table data…');
    foreach ($metadataTables as $table) {
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
        MiscUtility::spinnerAdvance($bar);
    }
    MiscUtility::spinnerFinish($bar);

    $dataToSync = [];
    $dataToSync['timestamp'] = empty($lastUpdatedOn) ? time() : strtotime((string) $lastUpdatedOn);
    $dataToSync['data'] = $data;

    $currentDate = DateUtility::getCurrentDateTime();

    $filename = "reference-data-$currentDate.json";
    $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
    fwrite($fp, json_encode($dataToSync));
    fclose($fp);


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
            'contents' => $general->getSystemConfig('sc_testing_lab_id')
        ]
    ];

    $output->write("$transactionId : Uploading to Smart Connect... ");
    $response = $apiService->postFile($url, 'referenceFile', TEMP_PATH . DIRECTORY_SEPARATOR . $filename, $params);

    MiscUtility::deleteFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);

    if (empty($response)) {
        $output->writeln("<error>FAILED</error>");
        LoggerUtility::log("error", "Metadata sync failed: no response from Smart Connect", [
            'file' => __FILE__,
            'line' => __LINE__,
            'url' => $url,
        ]);
        exit(0);
    }

    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || (isset($responseData['status']) && $responseData['status'] === 'error')) {
        $errorMsg = $responseData['message'] ?? $response;
        $output->writeln("<error>FAILED</error>");
        $output->writeln("<error> API Error: $errorMsg </error>");
        LoggerUtility::log("error", "Metadata sync API error", [
            'file' => __FILE__,
            'line' => __LINE__,
            'url' => $url,
            'response' => $response,
        ]);
        exit(0);
    }
    $output->writeln("<info>OK</info>");

    $general->addApiTracking(
        $transactionId,
        'vlsm-system',
        count($metadataTables),
        'smart-connect-metadata-sync',
        'common',
        $url,
        $dataToSync,
        $response,
        'json'
    );
    $unionParts = array_map(fn($table) => "SELECT MAX(updated_datetime) AS latest_update FROM `$table`", $metadataTables);
    $query = "SELECT MAX(latest_update) AS latest_update FROM (" . implode(" UNION ALL ", $unionParts) . ") AS combined";
    $result = $db->rawQueryOne($query);
    $latestDateTime = $result['latest_update'];

    $data = [
        'last_vldash_sync' => $latestDateTime ?? DateUtility::getCurrentDateTime()
    ];

    $db->update('s_vlsm_instance', $data);

    $output->writeln("<info>Sync complete. Last sync time: " . $data['last_vldash_sync'] . "</info>");
} catch (Throwable $exc) {
    $output->writeln("<error> Error: " . $exc->getMessage() . " </error>");
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $exc->getTraceAsString(),
    ]);
}

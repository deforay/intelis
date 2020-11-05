<?php

ini_set('memory_limit', -1);

require_once(__DIR__ . "/../../startup.php");




$general = new \Vlsm\Models\General($db);

$data = array();

// if forceSync is set as true, we will drop and create tables on VL Dashboard DB
$data['forceSync'] = true;


$referenceTables = array(
    'facility_details'
);

if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
    $vlTables = array(
        'r_vl_sample_type',
        'r_vl_test_reasons',
        'r_vl_art_regimen',
        'r_vl_sample_rejection_reasons',
    );

    $referenceTables = array_merge($referenceTables, $vlTables);
}


if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
    $eidTables = array(
        //'r_eid_results',
        'r_eid_sample_rejection_reasons',
        'r_eid_sample_type',
        //'r_eid_test_reasons',
    );
    $referenceTables = array_merge($referenceTables, $eidTables);
}


if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
    $covid19Tables = array(
        //'r_covid19_results',
        'r_covid19_comorbidities',
        'r_covid19_sample_rejection_reasons',
        'r_covid19_sample_type',
        'r_covid19_symptoms',
        'r_covid19_test_reasons',
    );
    $referenceTables = array_merge($referenceTables, $covid19Tables);
}

if (isset($systemConfig['modules']['common']) && $systemConfig['modules']['common'] == true) {
    $commonTables = array(
        'import_config_machines',
    );
    $referenceTables = array_merge($referenceTables, $commonTables);
}
// print_r($referenceTables);die;

try {

    foreach ($referenceTables as $table) {
        if ($data['forceSync']) {
            $createResult = $db->rawQueryOne("SHOW CREATE TABLE `$table`");
            $data[$table]['tableStructure'] = "SET FOREIGN_KEY_CHECKS=0;" . PHP_EOL;
            $data[$table]['tableStructure'] .= "ALTER TABLE `$table` DISABLE KEYS ;" . PHP_EOL;
            $data[$table]['tableStructure'] .= "DROP TABLE IF EXISTS `$table`;" . PHP_EOL;
            $data[$table]['tableStructure'] .= $createResult['Create Table'] . ";". PHP_EOL;;
            $data[$table]['tableStructure'] .= "ALTER TABLE `$table` ENABLE KEYS ;" . PHP_EOL;
            $data[$table]['tableStructure'] .= "SET FOREIGN_KEY_CHECKS=1;" . PHP_EOL;
        }
        if($table!='import_config_machines')
            $data[$table]['lastModifiedTime'] = $general->getLastModifiedDateTime($table);
        $data[$table]['tableData'] = $db->get($table);
    }
    
    
    
    $currentDate = $general->getDateTime();

    
    $filename = 'reference-data-' . $currentDate . '.json';
    $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
    fwrite($fp, json_encode($data));
    fclose($fp);
    
    
    // print_r($data);die;
    
    $vldashboardUrl = $general->getGlobalConfig('vldashboard_url');
    $vldashboardUrl = rtrim($vldashboardUrl, "/");
    // $vldashboardUrl = "http://vldashboard";

    $apiUrl = $vldashboardUrl . "/api/vlsm-reference-tables";


    $data = [];
    $data['api-version'] = 'v2';
    $data['referenceFile'] = new CURLFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'application/json', $filename);
    // echo "<pre>";print_r($data);die;
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data']
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    curl_close($ch);

    // echo "<pre>";print_r($result);die;
    $response = json_decode($result, true);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

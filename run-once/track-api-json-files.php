<?php

use App\Registries\ContainerRegistry;

require_once(__DIR__ . '/../bootstrap.php');


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');


ini_set('memory_limit', -1);

if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api')) {
    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api', 0777, true);
}
if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'requests')) {
    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'requests', 0777, true);
}
if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'responses')) {
    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'responses', 0777, true);
}


//To select the list of json data
$query = "SELECT * FROM track_api_requests WHERE (request_data is not null OR response_data is not null) AND transaction_id IS NOT NULL ORDER BY requested_on";
$jsonResult = $db->rawQuery($query);
foreach ($jsonResult as $row) {
    if (isset($row['request_data']) && !empty($row['request_data']) && $row['request_data'] != 'x' && $row['request_data'] != '[]') {
        $output = stripslashes($row['request_data']);
        $pathname = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'requests' . DIRECTORY_SEPARATOR;
        // Save in json file
        if (!empty($output)) {
            $filename = $row['transaction_id'] . '.json';
            $fp = fopen($pathname . $filename, 'w');
            fwrite($fp, $output);
            fclose($fp);
        }
        //error_log('REQUEST - ' . $row['transaction_id']);
    }
    if (isset($row['response_data']) && !empty($row['response_data']) && $row['response_data'] != 'x' && $row['response_data'] != '[]') {
        $output = stripslashes($row['response_data']);
        $pathname = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'responses' . DIRECTORY_SEPARATOR;
        // Save in json file
        if (!empty($output)) {
            $filename = $row['transaction_id'] . '.json';
            $fp = fopen($pathname . $filename, 'w');
            fwrite($fp, $output);
            fclose($fp);
        }
        //error_log('RESPONSE - ' . $row['transaction_id']);
    }


    $db->where("api_track_id", $row['api_track_id']);
    $db->update("track_api_requests", array("request_data" => null, "response_data" => null));
}

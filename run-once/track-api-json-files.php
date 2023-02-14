<?php 
require_once(__DIR__ . '/../startup.php');

//To select the list of json data
$query = "SELECT * FROM track_api_requests WHERE (request_data IS NOT NULL OR response_data IS NOT NULL) AND transaction_id IS NOT NULL LIMIT 1000";
$jsonResult = $db->rawQuery($query);
foreach($jsonResult as $row){
    if(isset($row['request_data']) && !empty($row['request_data'])){
        $output = $row['request_data'];
        $pathname = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'requests' . DIRECTORY_SEPARATOR;
        // Save in json file
        if(isset($output) && !empty($output)){
            $filename = $row['transaction_id'] . '.json';
            $fp = fopen($pathname . $filename, 'w');
            fwrite($fp, json_encode($output));
            fclose($fp);
        }
        $db->where("api_track_id", $row['api_track_id']);
        $db->update("track_api_requests", array("request_data" => null));
    }
    if(isset($row['response_data']) && !empty($row['response_data'])){
        $output = $row['response_data'];
        $pathname = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'response' . DIRECTORY_SEPARATOR;
        // Save in json file
        if(isset($output) && !empty($output)){
            $filename = $row['transaction_id'] . '.json';
            $fp = fopen($pathname . $filename, 'w');
            fwrite($fp, json_encode($output));
            fclose($fp);
        }
        $db->where("api_track_id", $row['api_track_id']);
        $db->update("track_api_requests", array("response_data" => null));
    }
}
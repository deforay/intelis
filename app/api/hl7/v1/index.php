<?php

session_unset(); // no need of session in json response

use App\Models\Covid19;
use App\Models\Facilities;
use App\Models\General;
use App\Models\Users;
use App\Models\Vl;
use Aranyasen\HL7\Message;
use Aranyasen\HL7\Messages\ACK;
use Aranyasen\HL7\Segments\MSH;

ini_set('memory_limit', -1);
header('Content-Type: application/json');
$user = null;
$general = new General();
$userDb = new Users();
$facilityDb = new Facilities();
$c19Db = new Covid19();
$vlDb = new Vl();

$transactionId = $general->generateUUID();

$user = null;
try {
    $requestUrl = $_SERVER['REQUEST_URI'];
    // The request has to send an Authorization Bearer token 
    $auth = $general->getHeader('Authorization');
    if (!empty($auth)) {
        $authToken = str_replace("Bearer ", "", $auth);
        // Check if API token exists
        $user = $userDb->getAuthToken($authToken);
    }
    // If authentication fails then do not proceed
    if (empty($user) || empty($user['user_id'])) {
        $response = array(
            'status' => 'failed',
            'timestamp' => time(),
            'error' => 'Bearer Token Invalid',
            'data' => array()
        );
        http_response_code(401);
        echo json_encode($response);
        exit(0);
    }

    $hl7Msg = file_get_contents("php://input");
    if (!isset($hl7Msg) && !empty($hl7Msg)) {
        $msh = new MSH();
        $ack = new ACK($msg, $msh);
        $ack->setAckCode('AR', "Message Type not found");
        $returnString = $ack->toString(true);
        echo $returnString;
        // http_response_code(204);
        unset($ack);
    }
    // print_r(explode("MSH", $hl7Msg));die;
    foreach (explode("MSH", $hl7Msg) as $hl7) {
        if (isset($hl7) && !empty($hl7) && trim($hl7) != "") {
            
            
            $hl7 = 'MSH' . $hl7;
            $msg = new Message($hl7);
            // To get the type of test
            $msh = $msg->getSegmentByIndex(0);
            $type = $msh->getField(9);
            // Get if have any filters
            if ($msg->hasSegment('MSH')) {
                $mshF = (array)$msg->getSegmentsByName('MSH')[0];
                $mshF = array_shift($mshF);
            }
            if ($msg->hasSegment('PID')) {
                $pidF = (array)$msg->getSegmentsByName('PID')[0];
                $pidF = array_shift($pidF);
            }
            if ($msg->hasSegment('SPM')) {
                $spmF = (array)$msg->getSegmentsByName('SPM')[0];
                $spmF = array_shift($spmF);
            }
            if ($msg->hasSegment('ZFL')) {
                $dateRange = (array)$msg->getSegmentsByName('ZFL')[0];
                $dateRange = array_shift($dateRange);
            }

            // print_r($mshF);
            // print_r($pidF);
            // print_r($spmF);
            // print_r($dateRange);
            // die;
            if (isset($type) && count($type) > 0 && in_array($type[0], array("COVID-19", "VL", "EID"))) {

                if ($type[0] == "COVID-19") {
                    include("covid-19.php");
                }
                if ($type[0] == "VL") {
                    include("vl.php");
                }
                if ($type[0] == "EID") {
                    include("eid.php");
                }
            } else {
                $msh = new MSH();
                $ack = new ACK($msg, $msh);
                $ack->setAckCode('AR', "Message Type not found");
                $returnString = $ack->toString(true);
                echo $returnString;
                // http_response_code(204);
                unset($ack);
            }
        }
    }
} catch (Exception $exc) {

    // http_response_code(500);
    $payload = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => array()
    );
    if (isset($user['token_updated']) && $user['token_updated'] == true) {
        $payload['token'] = $user['new_token'];
    }

    echo json_encode($payload);

    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    exit(0);
}

<?php
session_unset(); // no need of session in json response

use App\Exceptions\SystemException;
use App\Services\Covid19Service;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;
use App\Services\VlService;
use Aranyasen\HL7\Message;
use Aranyasen\HL7\Messages\ACK;
use Aranyasen\HL7\Segments\MSH;

ini_set('memory_limit', -1);
header('Content-Type: application/json');

$user = null;
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
$vlService = ContainerRegistry::get(VlService::class);

$transactionId = $general->generateUUID();

$user = null;
try {
    $requestUrl = $_SERVER['REQUEST_URI'];
    // The request has to send an Authorization Bearer token
    $authToken = $general->getAuthorizationBearerToken();
    if (!empty($authToken)) {
        $user = $usersService->getUserFromToken($authToken);
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
        $payload = json_encode($response);
        //exit(0);
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
            if (isset($type) && !empty($type) && in_array($type[0], array("COVID-19", "VL", "EID"))) {

                if ($type[0] == "COVID-19") {
                    include_once("covid-19.php");
                }
                if ($type[0] == "VL") {
                    include_once("vl.php");
                }
                if ($type[0] == "EID") {
                    include_once("eid.php");
                }
            } else {
                $msh = new MSH();
                $ack = new ACK($msg, $msh);
                $ack->setAckCode('AR', "Message Type not found");
                $returnString = $ack->toString(true);
                echo $returnString;
                unset($ack);
            }
        }
    }
} catch (SystemException $exc) {

    // http_response_code(500);
    $payload = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => array()
    );
    if (isset($user['token_updated']) && $user['token_updated']) {
        $payload['token'] = $user['new_token'];
    }

    $payload =  json_encode($payload);

    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    //exit(0);
}

echo $payload;

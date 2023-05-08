<?php

require_once(__DIR__ . "/../../../../bootstrap.php");
require_once(APPLICATION_PATH . '/../configs/config.interop.php');


$recipient =  $_POST['recipient'];
$message = $_POST['message'];

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://cbs1.moh.gov.rw/hepatitis/api/sms/outbound?message='.$message.'&recipient='.$recipient,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;
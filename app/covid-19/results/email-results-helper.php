<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\TestResultsService;
use App\Utilities\DateUtility;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TestResultsService $testResult */
$testResult = ContainerRegistry::get(TestResultsService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = "form_covid19";

//get vl result mail sent list
$resultmailSentQuery = "SELECT result_mail_datetime FROM form_covid19 where MONTH(result_mail_datetime) = MONTH(CURRENT_DATE())";
$resultmailSentResult = $db->rawQuery($resultmailSentQuery);
$sourcecode = sprintf("%02d", (count($resultmailSentResult) + 1));
//get instance facility code
$sequencenumber = '';
$instancefacilityCodeQuery = "SELECT instance_facility_code FROM s_vlsm_instance";
$instancefacilityCodeResult = $db->rawQuery($instancefacilityCodeQuery);
$instancefacilityCode = (isset($instancefacilityCodeResult[0]['instance_facility_code']) && trim((string) $instancefacilityCodeResult[0]['instance_facility_code']) !== '') ? '/' . $instancefacilityCodeResult[0]['instance_facility_code'] : '';
$year = date("Y");
$month = strtolower(date("M"));
$sequencenumber = 'Ref : vlsm/results/' . $year . '/' . $month . $instancefacilityCode . '/' . $sourcecode;
//get other config values
$geQuery = "SELECT * FROM other_config WHERE type = 'result'";
$geResult = $db->rawQuery($geQuery);
$mailconf = [];
foreach ($geResult as $row) {
   $mailconf[$row['name']] = $row['value'];
}

if (isset($_POST['toEmail']) && trim((string) $_POST['toEmail']) !== '') {
   //  $result_file_to_attach = $pathFront . DIRECTORY_SEPARATOR . $_POST['pdfFile2'];
   // $mail->AddAttachment($result_file_to_attach);
   $tempMailData = ["to_mail" => $_POST['toEmail'], "subject" => $_POST['subject'], "text_message" => $_POST['message'], "report_email" => $_POST['reportEmail'], "test_type" => 'covid19', "attachment" => $_POST['pdfFile1'], "samples" => $_POST['sample'], "status" => "pending"];

   $storeMail = $db->insert('temp_mail', $tempMailData);

   if ($storeMail) {
      $testResult->updateEmailTestResultsInfo('covid19', $tempMailData);

      $_SESSION['alertMsg'] = 'Email will be sent shortly';
      header('location:email-results.php');
   } else {
      $_SESSION['alertMsg'] = 'Unable to send mail. Please try later.';
      header('location:email-results.php');
   }
} else {
   $_SESSION['alertMsg'] = 'Unable to send mail. Please try later.';
   header('location:email-results.php');
}
?>
<div class=".b."></div>
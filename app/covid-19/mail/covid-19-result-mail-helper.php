<?php

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
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
   if (isset($mailconf['rs_field']) && trim((string) $mailconf['rs_field']) !== '') {
      //Create a new PHPMailer instance
      $mail = new PHPMailer();
      //Tell PHPMailer to use SMTP
      $mail->isSMTP();
      //Enable SMTP debugging
      // 0 = off (for production use)
      // 1 = client messages
      // 2 = client and server messages
      $mail->SMTPDebug = 2;
      //Ask for HTML-friendly debug output
      $mail->Debugoutput = 'html';
      //Set the hostname of the mail server
      $mail->Host = 'smtp.gmail.com';
      //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
      $mail->Port = 587;
      //Set the encryption system to use - ssl (deprecated) or tls
      $mail->SMTPSecure = 'tls';
      //Whether to use SMTP authentication
      $mail->SMTPAuth = true;
      $mail->SMTPKeepAlive = true;
      //Username to use for SMTP authentication - use full email address for gmail
      $mail->Username = $mailconf['rs_email'];
      //Password to use for SMTP authentication
      $mail->Password = $mailconf['rs_password'];
      //Set who the message is to be sent from
      $mail->setFrom($mailconf['rs_email']);

      $subject = "";
      if (isset($_POST['subject']) && trim((string) $_POST['subject']) !== "") {
         $subject = $_POST['subject'];
      }
      $mail->Subject = $subject;
      //Set To EmailId(s)
      if (isset($_POST['toEmail']) && trim((string) $_POST['toEmail']) !== '') {
         $xplodAddress = explode(",", (string) $_POST['toEmail']);
         $counter = count($xplodAddress);
         for ($to = 0; $to < $counter; $to++) {
            $mail->addAddress($xplodAddress[$to]);
         }
      }
      //Set CC EmailId(s)
      if (isset($_POST['reportEmail']) && trim((string) $_POST['reportEmail']) !== '') {
         $xplodCc = explode(",", (string) $_POST['reportEmail']);
         $counter = count($xplodCc);
         for ($cc = 0; $cc < $counter; $cc++) {
            $mail->AddCC($xplodCc[$cc]);
         }
      }
      //Pdf file attach
      $pathFront = realpath(TEMP_PATH);

      if (MiscUtility::fileExists($pathFront . DIRECTORY_SEPARATOR . $_POST['pdfFile1'])) {
         $mail->AddAttachment($pathFront . DIRECTORY_SEPARATOR . $_POST['pdfFile1']);
      }
      if (MiscUtility::fileExists($pathFront . DIRECTORY_SEPARATOR . $_POST['pdfFile2'])) {
         $mail->AddAttachment($pathFront . DIRECTORY_SEPARATOR . $_POST['pdfFile2']);
      }
      $message = '';
      if (isset($_POST['message']) && trim((string) $_POST['message']) !== "") {
         $message = (nl2br((string) $_POST['message']));
      }
      $message = $sequencenumber . '<br><br>' . $message;
      $mail->msgHTML($message);
      $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
      if ($mail->send()) {
         //update result mail sent flag
         $_POST['sample'] = explode(',', (string) $_POST['sample']);
         $counter = count($_POST['sample']);
         for ($s = 0; $s < $counter; $s++) {
            $sampleQuery = "SELECT covid19_id FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id where vl.covid19_id = '" . $_POST['sample'][$s] . "'";
            $sampleResult = $db->rawQuery($sampleQuery);
            $db->where('covid19_id', $sampleResult[0]['covid19_id']);
            $db->update($tableName, ['is_result_mail_sent' => 'yes', 'result_mail_datetime' => DateUtility::getCurrentDateTime()]);
         }
         $_SESSION['alertMsg'] = 'Email sent successfully';
         header('location:/covid-19/mail/mail-covid-19-results.php');
      } else {
         $_SESSION['alertMsg'] = 'Unable to send mail. Please try later.';
         error_log("Mailer Error: " . $mail->ErrorInfo);
         header('location:/covid-19/mail/mail-covid-19-results.php');
      }
   } else {
      $_SESSION['alertMsg'] = 'Unable to send mail. Please try later.';
      header('location:/covid-19/mail/mail-covid-19-results.php');
   }
} else {
   $_SESSION['alertMsg'] = 'Unable to send mail. Please try later.';
   header('location:/covid-19/mail/mail-covid-19-results.php');
}

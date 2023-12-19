<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();

$tableName = "form_eid";

//get vl result mail sent list
$resultmailSentQuery = "SELECT result_mail_datetime FROM form_eid where MONTH(result_mail_datetime) = MONTH(CURRENT_DATE())";
$resultmailSentResult = $db->rawQuery($resultmailSentQuery);
$sourcecode = sprintf("%02d", (count($resultmailSentResult) + 1));
//get instance facility code
$sequencenumber = '';
$instancefacilityCodeQuery = "SELECT instance_facility_code FROM s_vlsm_instance";
$instancefacilityCodeResult = $db->rawQuery($instancefacilityCodeQuery);
$instancefacilityCode = (isset($instancefacilityCodeResult[0]['instance_facility_code']) && trim((string) $instancefacilityCodeResult[0]['instance_facility_code']) != '') ? '/' . $instancefacilityCodeResult[0]['instance_facility_code'] : '';
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

if (isset($_POST['toEmail']) && trim((string) $_POST['toEmail']) != '') {
   if (isset($mailconf['rs_field']) && trim((string) $mailconf['rs_field']) != '') {
      //Create a new PHPMailer instance
      $mail = new PHPMailer\PHPMailer\PHPMailer();
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
      if (isset($_POST['subject']) && trim((string) $_POST['subject']) != "") {
         $subject = $_POST['subject'];
      }
      $mail->Subject = $subject;
      //Set To EmailId(s)
      if (isset($_POST['toEmail']) && trim((string) $_POST['toEmail']) != '') {
         $xplodAddress = explode(",", (string) $_POST['toEmail']);
         for ($to = 0; $to < count($xplodAddress); $to++) {
            $mail->addAddress($xplodAddress[$to]);
         }
      }
      //Set CC EmailId(s)
      if (isset($_POST['reportEmail']) && trim((string) $_POST['reportEmail']) != '') {
         $xplodCc = explode(",", (string) $_POST['reportEmail']);
         for ($cc = 0; $cc < count($xplodCc); $cc++) {
            $mail->AddCC($xplodCc[$cc]);
         }
      }
      //Pdf file attach
      $pathFront = realpath(UPLOAD_PATH);
      $file_to_attach = $pathFront . DIRECTORY_SEPARATOR . $_POST['pdfFile1'];
      $mail->AddAttachment($file_to_attach);
      $result_file_to_attach = $pathFront . DIRECTORY_SEPARATOR . $_POST['pdfFile2'];
      $mail->AddAttachment($result_file_to_attach);
      $message = '';
      if (isset($_POST['message']) && trim((string) $_POST['message']) != "") {
         $message = (nl2br((string) $_POST['message']));
      }
      $message = $sequencenumber . '<br><br>' . $message;
      $mail->msgHTML($message);
      $mail->SMTPOptions = array(
         'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
         )
      );
      if ($mail->send()) {
         //update result mail sent flag
         $_POST['sample'] = explode(',', (string) $_POST['sample']);
         for ($s = 0; $s < count($_POST['sample']); $s++) {
            $sampleQuery = "SELECT eid_id FROM form_eid as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id where vl.eid_id = '" . $_POST['sample'][$s] . "'";
            $sampleResult = $db->rawQuery($sampleQuery);
            $db->where('eid_id', $sampleResult[0]['eid_id']);
            $db->update($tableName, array('is_result_mail_sent' => 'yes', 'result_mail_datetime' => DateUtility::getCurrentDateTime()));
         }

         $_SESSION['alertMsg'] = 'Email sent successfully';
         header('location:email-results.php');
      } else {
         $_SESSION['alertMsg'] = 'Unable to send mail. Please try later.';
         error_log("Mailer Error: " . $mail->ErrorInfo);
         header('location:email-results.php');
      }
   } else {
      $_SESSION['alertMsg'] = 'Unable to send mail. Please try later.';
      header('location:email-results.php');
   }
} else {
   $_SESSION['alertMsg'] = 'Unable to send mail. Please try later.';
   header('location:email-results.php');
}

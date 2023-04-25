<?php

use App\Services\CommonService;
use App\Utilities\DateUtils;


if (session_status() == PHP_SESSION_NONE) {
   session_start();
}

$general = new CommonService();
$tableName = "form_hepatitis";
$configSyncQuery = "SELECT `value` FROM global_config where `name`='sync_path'";
$configSyncResult = $db->rawQuery($configSyncQuery);
//get vl result mail sent list
$resultmailSentQuery = "SELECT result_mail_datetime FROM form_hepatitis where MONTH(result_mail_datetime) = MONTH(CURRENT_DATE())";
$resultmailSentResult = $db->rawQuery($resultmailSentQuery);
$sourcecode = sprintf("%02d", (count($resultmailSentResult) + 1));
//get instance facility code
$sequencenumber = '';
$instancefacilityCodeQuery = "SELECT instance_facility_code FROM s_vlsm_instance";
$instancefacilityCodeResult = $db->rawQuery($instancefacilityCodeQuery);
$instancefacilityCode = (isset($instancefacilityCodeResult[0]['instance_facility_code']) && trim($instancefacilityCodeResult[0]['instance_facility_code']) != '') ? '/' . $instancefacilityCodeResult[0]['instance_facility_code'] : '';
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
if (isset($_POST['toEmail']) && trim($_POST['toEmail']) != '') {
   if (isset($mailconf['rs_field']) && trim($mailconf['rs_field']) != '') {
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
      if (isset($_POST['subject']) && trim($_POST['subject']) != "") {
         $subject = $_POST['subject'];
      }
      $mail->Subject = $subject;
      //Set To EmailId(s)
      if (isset($_POST['toEmail']) && trim($_POST['toEmail']) != '') {
         $xplodAddress = explode(",", $_POST['toEmail']);
         for ($to = 0; $to < count($xplodAddress); $to++) {
            $mail->addAddress($xplodAddress[$to]);
         }
      }
      //Set CC EmailId(s)
      if (isset($_POST['reportEmail']) && trim($_POST['reportEmail']) != '') {
         $xplodCc = explode(",", $_POST['reportEmail']);
         for ($cc = 0; $cc < count($xplodCc); $cc++) {
            $mail->AddCC($xplodCc[$cc]);
         }
      }
      //Pdf file attach
      $pathFront = realpath(TEMP_PATH);
      $file_to_attach = $pathFront . DIRECTORY_SEPARATOR . $_POST['pdfFile1'];
      $mail->AddAttachment($file_to_attach);
      $result_file_to_attach = $pathFront . DIRECTORY_SEPARATOR . $_POST['pdfFile2'];
      $mail->AddAttachment($result_file_to_attach);
      $message = '';
      if (isset($_POST['message']) && trim($_POST['message']) != "") {
         $message = (nl2br($_POST['message']));
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
         $_POST['sample'] = explode(',', $_POST['sample']);
         for ($s = 0; $s < count($_POST['sample']); $s++) {
            $sampleQuery = "SELECT hepatitis_id FROM form_hepatitis as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id where vl.hepatitis_id = '" . $_POST['sample'][$s] . "'";
            $sampleResult = $db->rawQuery($sampleQuery);
            $db = $db->where('hepatitis_id', $sampleResult[0]['hepatitis_id']);
            $db->update($tableName, array('is_result_mail_sent' => 'yes', 'result_mail_datetime' => DateUtils::getCurrentDateTime()));
         }
         //put file in sync path
         if (file_exists($configSyncResult[0]['value']) && $_POST['storeFile'] == 'yes') {
            if (!file_exists($configSyncResult[0]['value'] . DIRECTORY_SEPARATOR . "result-email") && !is_dir($configSyncResult[0]['value'] . DIRECTORY_SEPARATOR . "result-email")) {
               mkdir($configSyncResult[0]['value'] . DIRECTORY_SEPARATOR . "result-email", 0777, true);
            }
            copy($pathFront . DIRECTORY_SEPARATOR . $_POST['pdfFile1'], $configSyncResult[0]['value'] . DIRECTORY_SEPARATOR . "result-email" . DIRECTORY_SEPARATOR . $_POST['pdfFile1']);
            copy($pathFront . DIRECTORY_SEPARATOR . $_POST['pdfFile2'], $configSyncResult[0]['value'] . DIRECTORY_SEPARATOR . "result-email" . DIRECTORY_SEPARATOR . $_POST['pdfFile2']);
         }
         $_SESSION['alertMsg'] = 'Email sent successfully';
         header('location:/hepatitis/mail/mail-hepatitis-results.php');
      } else {
         $_SESSION['alertMsg'] = 'Unable to send mail. Please try later.';
         error_log("Mailer Error: " . $mail->ErrorInfo);
         header('location:/hepatitis/mail/mail-hepatitis-results.php');
      }
   } else {
      $_SESSION['alertMsg'] = 'Unable to send mail. Please try later.';
      header('location:/hepatitis/mail/mail-hepatitis-results.php');
   }
} else {
   $_SESSION['alertMsg'] = 'Unable to send mail. Please try later.';
   header('location:/hepatitis/mail/mail-hepatitis-results.php');
}

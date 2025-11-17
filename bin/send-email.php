<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use App\Registries\ContainerRegistry;


require_once(__DIR__ . "/../bootstrap.php");

try {

   $phpPath = SYSTEM_CONFIG['system']['php_path'] ?? PHP_BINARY;

   /** @var DatabaseService $db */
   $db = ContainerRegistry::get(DatabaseService::class);

   /** @var CommonService $general */
   $general = ContainerRegistry::get(CommonService::class);


   //get vl result mail sent list
   $resultmailSentQuery = "SELECT result_mail_datetime
                           FROM form_vl
                           WHERE MONTH(result_mail_datetime) = MONTH(CURRENT_DATE())";
   $resultmailSentResult = $db->rawQuery($resultmailSentQuery);
   $sourcecode = sprintf("%02d", (count($resultmailSentResult) + 1));


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

   $db->where("attachment IS NOT NULL AND status='pending'");
   $tempMail = $db->get('temp_mail');

   if (!empty($tempMail)) {
      foreach ($tempMail as $data) {

         $mail = new PHPMailer(true);
         //Tell PHPMailer to use SMTP
         $mail->isSMTP();
         $mail->SMTPDebug = 0;
         //Ask for HTML-friendly debug output
         $mail->Debugoutput = 'html';
         //Set the hostname of the mail server
         $mail->Host = 'smtp.gmail.com';
         //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
         $mail->Port = 465;
         //Set the encryption system to use - ssl (deprecated) or tls
         $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
         //Whether to use SMTP authentication
         $mail->SMTPAuth = true;
         $mail->SMTPKeepAlive = true;
         //Username to use for SMTP authentication - use full email address for gmail
         // $mail->Username = $mailconf['rs_email'];
         $mail->Username = SYSTEM_CONFIG['adminEmailUserName'];
         //Password to use for SMTP authentication
         $mail->Password = SYSTEM_CONFIG['adminEmailPassword'];
         //Set who the message is to be sent from
         $mail->setFrom($mailconf['rs_email']);

         $subject = "";
         if (isset($data['subject']) && trim((string) $data['subject']) !== "") {
            $subject = $data['subject'];
         }
         $mail->Subject = $subject;
         //Set To EmailId(s)
         if (isset($data['to_mail']) && trim((string) $data['to_mail']) !== '') {
            $xplodAddress = explode(",", (string) $data['to_mail']);
            $counter = count($xplodAddress);
            for ($to = 0; $to < $counter; $to++) {
               $mail->addAddress($xplodAddress[$to]);
            }
         }
         //Set CC EmailId(s)
         if (isset($data['report_email']) && trim((string) $data['report_email']) !== '') {
            $xplodCc = explode(",", (string) $data['report_email']);
            $counter = count($xplodCc);
            for ($cc = 0; $cc < $counter; $cc++) {
               $mail->AddCC($xplodCc[$cc]);
            }
         }

         //Pdf file attach
         $pathFront = realpath(UPLOAD_PATH);
         $file = realpath(urldecode(base64_decode((string) $data['attachment'])));

         $file_to_attach =  $file;
         $mail->AddAttachment($file_to_attach);
         $message = '';
         if (isset($data['text_message']) && trim((string) $data['text_message']) !== "") {
            $message = (nl2br((string) $data['text_message']));
         }
         $message = $sequencenumber . '<br><br>' . $message;
         $mail->msgHTML($message);
         $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

         if ($data['test_type'] == "vl") {
            $testTable = "form_vl";
            $columnName = "vl_sample_id";
         } elseif ($data['test_type'] == "eid") {
            $testTable = "form_eid";
            $columnName = "eid_id";
         } elseif ($data['test_type'] == "covid19") {
            $testTable = "form_covid19";
            $columnName = "covid19_id";
         } elseif ($data['test_type'] == "hepatitis") {
            $testTable = "form_hepatitis";
            $columnName = "hepatitis_id";
         } elseif ($data['test_type'] == "tb") {
            $testTable = "form_tb";
            $columnName = "tb_id";
         } elseif ($data['test_type'] == "generic-tests") {
            $testTable = "form_generic";
            $columnName = "sample_id";
         }

         if ($mail->send()) {

            //update result mail sent flag
            $updateQuery = "UPDATE $testTable SET is_result_mail_sent = 'yes',result_mail_datetime = '" . DateUtility::getCurrentDateTime() . "' WHERE $columnName IN (" . $data['samples'] . ")";
            $db->rawQuery($updateQuery);

            //Add event log
            $eventType = 'email-results';
            $action = $_SESSION['userName'] . ' Sent an test results Email to ' . $data['toEmail'];
            $resource = $data['test_type'] . '-results';
            $general->activityLog($eventType, $action, $resource);

            //Update status in temp_mail table
            $db->where('id', $data['id']);
            $db->update('temp_mail', ['status' => 'completed']);
            echo "Email sent";
         } else {
            echo $mail->ErrorInfo;
         }
      }
   }
} catch (Exception $e) {
   LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
   LoggerUtility::logError($e->getMessage(), [
      'file' => $e->getFile(),
      'line' => $e->getLine(),
      'trace' => $e->getTraceAsString(),
   ]);
}

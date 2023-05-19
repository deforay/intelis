<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;




require_once APPLICATION_PATH . '/header.php';

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "form_covid19";
//get other config values
$geQuery = "SELECT * FROM other_config WHERE type = 'result'";
$geResult = $db->rawQuery($geQuery);
$mailconf = [];
foreach ($geResult as $row) {
   $mailconf[$row['name']] = $row['value'];
}
$filename = '';
$downloadFile1 = '';
$downloadFile2 = '';
if (isset($_POST['toEmail']) && trim($_POST['toEmail']) != "" && !empty($_POST['sample'])) {
   if (isset($mailconf['rs_field']) && trim($mailconf['rs_field']) != '') {
      //Pdf code start
      // create new PDF document
      class MYPDF extends TCPDF
      {
         public $logo;
         public $text;


         //Page header
         public function setHeading($logo, $text)
         {
            $this->logo = $logo;
            $this->text = $text;
         }
         public function imageExists($filePath)
         {
            return (!empty($filePath) && file_exists($filePath) && !is_dir($filePath) && filesize($filePath) > 0 && false !== getimagesize($filePath));
         }
         //Page header
         public function Header()
         {
            // Logo
            //$imageFilePath = K_PATH_IMAGES.'logo_example.jpg';
            //$this->Image($imageFilePath, 10, 10, 15, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            // Set font
            if (trim($this->logo) != '') {
               if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                  $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                  $this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T');
               }
            }
            $this->SetFont('helvetica', 'B', 7);
            $this->writeHTMLCell(30, 0, 16, 28, $this->text, 0, 0, 0, true, 'A');
            $this->SetFont('helvetica', '', 18);
            $this->writeHTMLCell(0, 0, 10, 18, 'VIRAL LOAD TEST RESULT', 0, 0, 0, true, 'C');
            $this->writeHTMLCell(0, 0, 15, 36, '<hr>', 0, 0, 0, true, 'C');
         }

         // Page footer
         public function Footer()
         {
            // Position at 15 mm from bottom
            $this->SetY(-15);
            // Set font
            $this->SetFont('helvetica', '', 8);
            // Page number
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0);
         }
      }
      $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
      $pdf->setHeading($global['logo'], $global['header']);
      $pdf->setPageOrientation('L');
      // set document information
      $pdf->SetCreator(PDF_CREATOR);
      $pdf->SetTitle('Vl Result Mail');
      //$pdf->SetSubject('TCPDF Tutorial');
      //$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

      // set default header data
      $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

      // set header and footer fonts
      $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
      $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

      // set default monospaced font
      $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

      // set margins
      $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 14, PDF_MARGIN_RIGHT);
      $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
      $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

      // set auto page breaks
      $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

      // set image scale factor
      $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

      // set font
      $pdf->SetFont('helvetica', '', 8);
      $pdf->AddPage();
      $pdfContent = '';
      $filedGroup = [];
      $filedGroup = explode(",", $mailconf['rs_field']);
      $pdfContent .= '<table style="width;100%;border:1px solid #333;padding:0px 2px 2px 2px;" cellspacing="0" cellpadding="2">';
      $pdfContent .= '<tr>';
      for ($f = 0; $f < count($filedGroup); $f++) {
         if ($filedGroup[$f] == 'Province') {
            $filedGroup[$f] = 'Province/State';
         } else if ($filedGroup[$f] == 'District Name') {
            $filedGroup[$f] = 'District/County';
         }
         $pdfContent .= '<td style="border:1px solid #333;"><strong>' . $filedGroup[$f] . '</strong></td>';
      }
      $pdfContent .= '</tr>';
      for ($s = 0; $s < count($_POST['sample']); $s++) {
         $sampleQuery = "SELECT sample_code FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id where vl.covid19_id = '" . $_POST['sample'][$s] . "' AND vl.result IS NOT NULL AND vl.result!= '' ORDER BY f.facility_name ASC";
         $sampleResult = $db->rawQuery($sampleQuery);
         if (isset($sampleResult[0]['sample_code'])) {
            $pdfContent .= '<tr>';
            for ($f = 0; $f < count($filedGroup); $f++) {
               $field = '';
               if ($filedGroup[$f] == "Sample ID") {
                  $field = 'sample_code';
               } elseif ($filedGroup[$f] == "Urgency") {
                  $field = 'test_urgency';
               } elseif ($filedGroup[$f] == "Province") {
                  $field = 'facility_state';
               } elseif ($filedGroup[$f] == "District Name") {
                  $field = 'facility_district';
               } elseif ($filedGroup[$f] == "Clinic Name") {
                  $field = 'facility_name';
               } elseif ($filedGroup[$f] == "Clinician Name") {
                  $field = 'lab_contact_person';
               } elseif ($filedGroup[$f] == "Sample Collection Date") {
                  $field = 'sample_collection_date';
               } elseif ($filedGroup[$f] == "Sample Received Date") {
                  $field = 'sample_received_at_vl_lab_datetime';
               } elseif ($filedGroup[$f] == "Collected by (Initials)") {
                  $field = 'sample_collected_by';
               } elseif ($filedGroup[$f] == "Gender") {
                  $field = 'patient_gender';
               } elseif ($filedGroup[$f] == "Date Of Birth") {
                  $field = 'patient_dob';
               } elseif ($filedGroup[$f] == "Age in years") {
                  $field = 'patient_age_in_years';
               } elseif ($filedGroup[$f] == "Age in months") {
                  $field = 'patient_age_in_months';
               } elseif ($filedGroup[$f] == "Is Patient Pregnant?") {
                  $field = 'is_patient_pregnant';
               } elseif ($filedGroup[$f] == "Is Patient Breastfeeding?") {
                  $field = 'is_patient_breastfeeding';
               } elseif ($filedGroup[$f] == "Patient ID/ART/TRACNET") {
                  $field = 'patient_art_no';
               } elseif ($filedGroup[$f] == "Date Of ART Initiation") {
                  $field = 'date_of_initiation_of_current_regimen';
               } elseif ($filedGroup[$f] == "ART Regimen") {
                  $field = 'current_regimen';
               } elseif ($filedGroup[$f] == "Patient consent to SMS Notification?") {
                  $field = 'consent_to_receive_sms';
               } elseif ($filedGroup[$f] == "Patient Mobile Number") {
                  $field = 'patient_mobile_number';
               } elseif ($filedGroup[$f] == "Date of Last VL Test") {
                  $field = 'last_viral_load_date';
               } elseif ($filedGroup[$f] == "Result Of Last Viral Load") {
                  $field = 'last_viral_load_result';
               } elseif ($filedGroup[$f] == "Viral Load Log") {
                  $field = 'last_vl_result_in_log';
               } elseif ($filedGroup[$f] == "Reason For VL Test") {
                  $field = 'reason_for_covid19_testing';
               } elseif ($filedGroup[$f] == "Lab Name") {
                  $field = 'lab_id';
               } elseif ($filedGroup[$f] == "Lab ID") {
                  $field = 'lab_code';
               } elseif ($filedGroup[$f] == "VL Testing Platform") {
                  $field = 'vl_test_platform';
               } elseif ($filedGroup[$f] == "Specimen type") {
                  $field = 'sample_name';
               } elseif ($filedGroup[$f] == "Sample Testing Date") {
                  $field = 'sample_tested_datetime';
               } elseif ($filedGroup[$f] == "Viral Load Result(copiesl/ml)") {
                  $field = 'result_value_absolute';
               } elseif ($filedGroup[$f] == "Log Value") {
                  $field = 'result_value_log';
               } elseif ($filedGroup[$f] == "Is Sample Rejected") {
                  $field = 'is_sample_rejected';
               } elseif ($filedGroup[$f] == "Rejection Reason") {
                  $field = 'rejection_reason_name';
               } elseif ($filedGroup[$f] == "Reviewed By") {
                  $field = 'result_reviewed_by';
               } elseif ($filedGroup[$f] == "Approved By") {
                  $field = 'result_approved_by';
               } elseif ($filedGroup[$f] == "Lab Tech. Comments") {
                  $field = 'lab_tech_comments';
               } elseif ($filedGroup[$f] == "Status") {
                  $field = 'status_name';
               }

               if ($field == '') {
                  continue;
               }
               if ($field ==  'result_reviewed_by') {
                  $fValueQuery = "SELECT u.user_name as reviewedBy FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_covid19_sample_type as s_type ON s_type.sample_id=vl.specimen_type LEFT JOIN r_covid19_sample_rejection_reasons as s_r_r ON s_r_r.rejection_reason_id=vl.reason_for_sample_rejection LEFT JOIN user_details as u ON u.user_id = vl.result_reviewed_by where vl.covid19_id = '" . $_POST['sample'][$s] . "'";
               } elseif ($field ==  'result_approved_by') {
                  $fValueQuery = "SELECT u.user_name as approvedBy FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_covid19_sample_type as s_type ON s_type.sample_id=vl.specimen_type LEFT JOIN r_covid19_sample_rejection_reasons as s_r_r ON s_r_r.rejection_reason_id=vl.reason_for_sample_rejection LEFT JOIN user_details as u ON u.user_id = vl.result_approved_by where vl.covid19_id = '" . $_POST['sample'][$s] . "'";
               } elseif ($field ==  'lab_id') {
                  $fValueQuery = "SELECT f.facility_name as labName FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.lab_id=f.facility_id where vl.covid19_id = '" . $_POST['sample'][$s] . "'";
               } else {
                  $fValueQuery = "SELECT $field FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_covid19_sample_type as s_type ON s_type.sample_id=vl.specimen_type LEFT JOIN r_covid19_sample_rejection_reasons as s_r_r ON s_r_r.rejection_reason_id=vl.reason_for_sample_rejection LEFT JOIN r_sample_status as t_s ON t_s.status_id=vl.result_status where vl.covid19_id = '" . $_POST['sample'][$s] . "'";
               }
               $fValueResult = $db->rawQuery($fValueQuery);
               $fieldValue = '';
               if (!empty($fValueResult)) {
                  if ($field == 'sample_collection_date' || $field == 'sample_received_at_vl_lab_datetime' || $field == 'sample_tested_datetime') {
                     if (isset($fValueResult[0][$field]) && trim($fValueResult[0][$field]) != '' && trim($fValueResult[0][$field]) != '0000-00-00 00:00:00') {
                        $fieldValue = DateUtility::humanReadableDateFormat($fValueResult[0][$field], true);
                     }
                  } elseif ($field == 'patient_dob' || $field == 'date_of_initiation_of_current_regimen' || $field == 'last_viral_load_date') {
                     if (isset($fValueResult[0][$field]) && trim($fValueResult[0][$field]) != '' && trim($fValueResult[0][$field]) != '0000-00-00') {
                        $fieldValue = DateUtility::humanReadableDateFormat($fValueResult[0][$field]);
                     }
                  } elseif ($field ==  'vl_test_platform' || $field ==  'patient_gender' || $field == 'is_sample_rejected') {
                     $fieldValue = (str_replace("_", " ", $fValueResult[0][$field]));
                  } elseif ($field ==  'result_reviewed_by') {
                     $fieldValue = (isset($fValueResult[0]['reviewedBy'])) ? $fValueResult[0]['reviewedBy'] : '';
                  } elseif ($field ==  'result_approved_by') {
                     $fieldValue = (isset($fValueResult[0]['approvedBy'])) ? $fValueResult[0]['approvedBy'] : '';
                  } elseif ($field ==  'lab_id') {
                     $fieldValue = (isset($fValueResult[0]['labName'])) ? $fValueResult[0]['labName'] : '';
                  } else {
                     $fieldValue = (isset($fValueResult[0][$field])) ? $fValueResult[0][$field] : '';
                  }
               }
               $pdfContent .= '<td style="border:1px solid #333;">' . $fieldValue . '</td>';
            }
            $pdfContent .= '</tr>';
         }
      }
      $pdfContent .= '</table>';
      $pdfContent .= '</div>';
      $pdf->writeHTML($pdfContent);
      $pdf->lastPage();
      $pathFront = realpath(TEMP_PATH);
      $filename = 'vlsm-result-' . date('d-M-Y-H-i-s') . '.pdf';
      $pdf->Output($pathFront . DIRECTORY_SEPARATOR . $filename, "F");
      $downloadFile1 = TEMP_PATH . $_POST['pdfFile'];
      $downloadFile2 = TEMP_PATH . $filename;
   } else {
      $_SESSION['alertMsg'] = 'Unable to generate test result pdf. Please check the result fields.';
      header('location:vlResultMail.php');
   }
} else {
   $_SESSION['alertMsg'] = 'Unable to generate test result pdf. Please try later.';
   header('location:vlResultMail.php');
}
?>
<style>
   .send-mail:hover {
      text-decoration: underline !important;
   }
</style>
<div class="content-wrapper">
   <div class="box box-default">
      <div class="box-header with-border">
         <div style="text-align:center;">
            <h4>Facility Name : <?= htmlspecialchars($_POST['toName']); ?></h4>
         </div>
      </div>
      <div class="box-body">
         <form id="vlResultMailConfirmForm" name="vlResultMailConfirmForm" method="post" action="covid-19-result-mail-helper.php">
            <div class="row">
               <div class="col-lg-12" style="text-align:center !important;">
                  <table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true" style="width:18%;margin-left:41%;">
                     <thead>
                        <tr>
                           <th style="text-align:center;background-color:#71b9e2;color:#FFFFFF;">Selected Sample(s)</th>
                        </tr>
                     </thead>
                     <tbody>
                        <?php
                        $resultOlySamples = [];
                        for ($s = 0; $s < count($_POST['sample']); $s++) {
                           $sampleQuery = "SELECT covid19_id,sample_code FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id where vl.covid19_id = '" . $_POST['sample'][$s] . "' AND vl.result IS NOT NULL AND vl.result!= '' ORDER BY f.facility_name ASC";
                           $sampleResult = $db->rawQuery($sampleQuery);
                           if (isset($sampleResult[0]['sample_code'])) {
                              $resultOlySamples[] = $sampleResult[0]['covid19_id'];
                        ?>
                              <tr>
                                 <td style="text-align:left;"><?php echo $sampleResult[0]['sample_code']; ?></td>
                              </tr>
                        <?php }
                        } ?>
                     </tbody>
                  </table>
               </div>
            </div>
            <div class="row">
               <input type="hidden" id="subject" name="subject" value="<?php echo htmlspecialchars($_POST['subject']); ?>" />
               <input type="hidden" id="toEmail" name="toEmail" value="<?php echo htmlspecialchars($_POST['toEmail']); ?>" />
               <input type="hidden" id="reportEmail" name="reportEmail" value="<?php echo htmlspecialchars($_POST['reportEmail']); ?>" />
               <input type="hidden" id="message" name="message" value="<?php echo htmlspecialchars($_POST['message']); ?>" />
               <input type="hidden" id="sample" name="sample" value="<?php echo implode(',', $resultOlySamples); ?>" />
               <input type="hidden" id="pdfFile1" name="pdfFile1" value="<?php echo htmlspecialchars($_POST['pdfFile']); ?>" />
               <input type="hidden" id="pdfFile2" name="pdfFile2" value="<?php echo $filename; ?>" />
               <input type="hidden" id="storeFile" name="storeFile" value="no" />
               <div class="col-lg-12" style="text-align:center;padding-left:0;">
                  <a href="/covid-19/mail/mail-covid-19-results.php" class="btn btn-default"> Cancel</a>&nbsp;
                  <a class="btn btn-primary" href="javascript:void(0);" onclick="confirmResultMail();"><em class="fa-solid fa-paper-plane"></em> Send</a>
                  <div><code><?php echo ($global['sync_path'] == '') ? 'Please enter "Sync Path" in General Config to enable file sharing via shared folder' : '' ?></code></div>
                  <p style="margin-top:10px;"><a class="send-mail" href="<?php echo htmlspecialchars($downloadFile1); ?>" target="_blank" rel="noopener" style="text-decoration:none;">Click here to download the result only pdf</a></p>
                  <p style="margin-top:10px;"><a class="send-mail" href="<?php echo htmlspecialchars($downloadFile2); ?>" target="_blank" rel="noopener" style="text-decoration:none;">Click here to download the result pdf </a></p>
               </div>
            </div>
         </form>
      </div>
   </div>
</div>
<script>
   function confirmResultMail() {
      <?php
      if ($global['sync_path'] != '') {
      ?>
         conf = confirm("Do you also want to store this file on the shared directory <?php echo $global['sync_path']; ?> ?");
         if (conf) {
            $("#storeFile").val('yes');
         } else {
            $("#storeFile").val('no');
         }
      <?php
      }
      ?>
      $.blockUI();
      document.getElementById('vlResultMailConfirmForm').submit();
   }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';

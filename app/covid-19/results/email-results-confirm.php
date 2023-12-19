<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$global = $general->getGlobalConfig();

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();

$tableName = "form_covid19";
//get other config values
$geQuery = "SELECT * FROM other_config WHERE `type` = 'result'";
$geResult = $db->rawQuery($geQuery);
$mailconf = [];
foreach ($geResult as $row) {
   $mailconf[$row['name']] = $row['value'];
}

$filename = '';
$downloadFile1 = '';
$downloadFile2 = '';
$selectedSamplesArray = !empty($_POST['selectedSamples']) ? json_decode((string) $_POST['selectedSamples'], true) : [];
if (isset($_POST['toEmail']) && trim((string) $_POST['toEmail']) != "" && !empty($selectedSamplesArray)) {
   if (isset($mailconf['rs_field']) && trim((string) $mailconf['rs_field']) != '') {
      //Pdf code start
      // create new PDF document
      class MYPDF extends TCPDF
      {
         public ?string $logo;
         public string $text = '';

         //Page header
         public function setHeading($logo, $text)
         {
            $this->logo = $logo;
            $this->text = $text;
         }
         public function imageExists($filePath): bool
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
      $pdf->SetCreator(_translate('VLSM'));
      $pdf->SetTitle('VLSM');
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

      for ($s = 0; $s < count($selectedSamplesArray); $s++) {
         $sampleQuery = "SELECT sample_code FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id where vl.covid19_id = '" . $selectedSamplesArray[$s] . "' AND vl.result IS NOT NULL AND vl.result!= '' ORDER BY f.facility_name ASC";
         $sampleResult = $db->rawQuery($sampleQuery);
         if (isset($sampleResult[0]['sample_code'])) {
            $pdfContent .= '<tr>';
           
               $fValueQuery = "SELECT vl.* FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_covid19_sample_type as s_type ON s_type.sample_id=vl.sample_type LEFT JOIN r_covid19_sample_rejection_reasons as s_r_r ON s_r_r.rejection_reason_id=vl.reason_for_sample_rejection LEFT JOIN r_sample_status as t_s ON t_s.status_id=vl.result_status where vl.covid19_id = '" . $selectedSamplesArray[$s] . "'";
               $fValueResult = $db->rawQuery($fValueQuery);
              
               foreach ($fValueResult as $result) {
                  $fieldValue = '';
               if (!empty($result)) {
                  if ($result['sample_collection_date']!="" || $field == 'sample_received_at_lab_datetime' || $field == 'sample_tested_datetime') {
                        $fieldValue = DateUtility::humanReadableDateFormat($result['sample_collection_date'], true);
                  } elseif ($result['patient_dob'] || $result['date_of_initiation_of_current_regimen']){
                        $fieldValue = DateUtility::humanReadableDateFormat($result['patient_dob']);
                  } elseif ($field ==  'covid19_test_platform' || $field ==  'patient_gender' || $field == 'is_sample_rejected') {
                     $fieldValue = (str_replace("_", " ", (string) $fValueResult[0][$field]));
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
      $downloadFile1 = TEMP_PATH . DIRECTORY_SEPARATOR . (htmlspecialchars((string) $_POST['pdfFile']));
      $downloadFile2 = TEMP_PATH . DIRECTORY_SEPARATOR . $filename;
   } else {
      $_SESSION['alertMsg'] = 'Unable to generate test result pdf. Please check the result fields.';
      header('location:email-results.php');
   }
} else {
   $_SESSION['alertMsg'] = "Unable to generate test result pdf. This facility doesn't have the email to send. Please try later.";
   header('location:email-results.php');
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
            <h4>Facility Name : <?= htmlspecialchars((string) $_POST['toName']); ?></h4>
         </div>
      </div>
      <div class="box-body">
         <form id="emailResultConfirmForm" name="emailResultConfirmForm" method="post" action="email-results-helper.php">
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
                        for ($s = 0; $s < count($selectedSamplesArray); $s++) {
                           $sampleQuery = "SELECT covid19_id,sample_code FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id where vl.covid19_id = '" . $selectedSamplesArray[$s] . "' AND vl.result IS NOT NULL AND vl.result!= '' ORDER BY f.facility_name ASC";
                           $sampleResult = $db->rawQuery($sampleQuery);
                           echo '<pre>'; print_r($sampleResult); die;
                           if (isset($sampleResult[0]['sample_code'])) {
                              $resultOlySamples[] = $sampleResult[0]['covid19_id'];
                        ?>
                              <tr>
                                 <td style="text-align:left;"><?php echo $sampleResult[0]['sample_code']; ?></td>
                              </tr>
                        <?php }
                        ?>
                     </tbody>
                  </table>
               </div>
            </div>
            <div class="row">
               <input type="hidden" id="subject" name="subject" value="<?php echo htmlspecialchars((string) $_POST['subject']); ?>" />
               <input type="hidden" id="toEmail" name="toEmail" value="<?php echo htmlspecialchars((string) $_POST['toEmail']); ?>" />
               <input type="hidden" id="reportEmail" name="reportEmail" value="<?php echo htmlspecialchars((string) $_POST['reportEmail']); ?>" />
               <input type="hidden" id="message" name="message" value="<?php echo htmlspecialchars((string) $_POST['message']); ?>" />
               <input type="hidden" id="sample" name="sample" value="<?php echo implode(',', $resultOlySamples); ?>" />
               <input type="hidden" id="pdfFile1" name="pdfFile1" value="<?php echo htmlspecialchars((string) $_POST['pdfFile']); ?>" />
               <input type="hidden" id="pdfFile2" name="pdfFile2" value="<?php echo $filename; ?>" />
               <input type="hidden" id="storeFile" name="storeFile" value="no" />
               <div class="col-lg-12" style="text-align:center;padding-left:0;">
                  <a href="../vl/results/email-results.php" class="btn btn-default"> Cancel</a>&nbsp;
                  <a class="btn btn-primary" href="javascript:void(0);" onclick="confirmResultMail();"><em class="fa-solid fa-paper-plane"></em> Send</a>
                  <p style="margin-top:10px;"><a class="send-mail" href="#" rel="noopener" onclick="resultPDF(<?php echo $sampleResult[0]['covid19_id']; ?>,this)" style="text-decoration:none;">Click here to download the result only pdf</a></p>
                  <p style="margin-top:10px;"><a class="send-mail" href="<?php echo htmlspecialchars($downloadFile2); ?>" target="_blank" rel="noopener" download style="text-decoration:none;">Click here to download the result pdf </a></p>
               </div>
            </div>
         </form>    
         <?php } ?>
      </div>
   </div>
</div>
<script>
   function confirmResultMail() {
      $.blockUI();
      document.getElementById('emailResultConfirmForm').submit();
   }

   function resultPDF(id, this) {
      alert(id);
        $.blockUI();
        <?php
        $path = '';
        $path = '/covid-19/results/generate-result-pdf.php';
        ?>
        $.post("<?php echo $path; ?>", {
                source: 'print',
                id: id,
                newData: newData
            },
            function(data) {
               alert(data);
                if (data == "" || data == null || data == undefined) {
                    $.unblockUI();
                    alert("<?php echo _translate("Unable to generate download"); ?>");
                } else {
                    $.unblockUI();
                    //window.open('/download.php?f=' + data, '_blank');
                    this.dispatchEvent(new MouseEvent('click'));

                }
            });
    }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';

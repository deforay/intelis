<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$global = $general->getGlobalConfig();

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = "form_cd4";
//get other config values
$geQuery = "SELECT * FROM other_config WHERE `type` = 'result'";
$geResult = $db->rawQuery($geQuery);
$mailconf = [];
foreach ($geResult as $row) {
   $mailconf[$row['name']] = $row['value'];
}


$selectedSamplesArray = empty($_POST['selectedSamples']) ? [] : json_decode((string) $_POST['selectedSamples'], true);

if (isset($_POST['toEmail']) && trim((string) $_POST['toEmail']) !== "" && !empty($selectedSamplesArray)) {
   //Pdf code start

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
            <form id="emailResultConfirmForm" name="emailResultConfirmForm" method="post"
               action="email-results-helper.php">
               <div class="row">
                  <div class="col-lg-12" style="text-align:center !important;">
                     <table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true"
                        style="width:18%;margin-left:41%;">
                        <thead>
                           <tr>
                              <th style="text-align:center;background-color:#71b9e2;color:#FFFFFF;">Selected Sample(s)</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           $resultOlySamples = [];
                           $counter = count($selectedSamplesArray);
                           for ($s = 0; $s < $counter; $s++) {
                              $sampleQuery = "SELECT cd4_id,sample_code FROM form_cd4 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id where vl.cd4_id = '" . $selectedSamplesArray[$s] . "' AND vl.cd4_result IS NOT NULL AND vl.cd4_result!= '' ORDER BY f.facility_name ASC";
                              $sampleResult = $db->rawQuery($sampleQuery);
                              if (isset($sampleResult[0]['sample_code'])) {
                                 $resultOlySamples[] = $sampleResult[0]['cd4_id'];
                                 ?>
                                 <tr>
                                    <td style="text-align:left;"><?php echo $sampleResult[0]['sample_code']; ?></td>
                                 </tr>
                              <?php }
                           }
                           $sampleIds = implode(',', $selectedSamplesArray);
                           ?>
                        </tbody>
                     </table>
                  </div>
               </div>
               <div class="row">
                  <input type="hidden" id="subject" name="subject"
                     value="<?php echo htmlspecialchars((string) $_POST['subject']); ?>" />
                  <input type="hidden" id="toEmail" name="toEmail"
                     value="<?php echo htmlspecialchars((string) $_POST['toEmail']); ?>" />
                  <input type="hidden" id="reportEmail" name="reportEmail"
                     value="<?php echo htmlspecialchars((string) $_POST['reportEmail']); ?>" />
                  <input type="hidden" id="message" name="message"
                     value="<?php echo htmlspecialchars((string) $_POST['message']); ?>" />
                  <input type="hidden" id="sample" name="sample" value="<?php echo implode(',', $resultOlySamples); ?>" />
                  <input type="hidden" id="pdfFile1" name="pdfFile1"
                     value="<?php echo htmlspecialchars((string) $_POST['pdfFile']); ?>" />
                  <input type="hidden" id="pdfFile2" name="pdfFile2" value="<?php ?>" />
                  <input type="hidden" id="storeFile" name="storeFile" value="no" />
                  <div class="col-lg-12" style="text-align:center;padding-left:0;">
                     <a href="../results/email-results.php" class="btn btn-default"> Cancel</a>&nbsp;
                     <a class="btn btn-primary" href="javascript:void(0);" onclick="confirmResultMail();"><em
                           class="fa-solid fa-paper-plane"></em> Send</a>
                     <p style="margin-top:10px;"><a class="send-mail" href="#"
                           onclick="downloadPDF('<?php echo $sampleIds; ?>','printData')"
                           style="text-decoration:none;">Click here to download the result only pdf</a></p>
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

   function downloadPDF(id, newData) {
      $.blockUI();
      <?php
      $path = '';
      $path = '/cd4/results/generate-result-pdf.php';
      ?>
      $.post("<?php echo $path; ?>", {
         source: 'print',
         id: id,
         newData: newData
      },
         function (data) {
            if (data == "" || data == null || data == undefined) {
               $.unblockUI();
               alert("<?= _translate("Unable to generate download", true); ?>");
            } else {
               $.unblockUI();
               //oTable.fnDraw();
               //opTable.fnDraw();
               //window.open('/download.php?f=' + data, '_blank');
               const link = document.createElement('a');
               link.href = '/download.php?f=' + data;
               // link.target = '_blank';
               link.download = data;

               // Simulate a click on the element <a>
               document.body.appendChild(link);
               link.click();
               document.body.removeChild(link);
            }
         });
   }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';

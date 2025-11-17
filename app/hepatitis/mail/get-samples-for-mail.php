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
$_POST = _sanitizeInput($request->getParsedBody());

$formId = (int) $general->getGlobalConfig('vl_form');

if (!is_array($_POST['facility']) || empty($_POST['facility'])) {
  $_POST['facility'] = [];
}
if (!is_array($_POST['batch']) || empty($_POST['batch'])) {
  $_POST['batch'] = [];
}
$facility = $_POST['facility'];
$sampleType = $_POST['sType'];
$gender = $_POST['gender'];
$state = $_POST['state'];
$district = $_POST['district'];
$batch = $_POST['batch'];
$mailSentStatus = $_POST['mailSentStatus'];
$type = $_POST['type'];
//print_r($_POST);die;
[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

$query = "SELECT hepatitis.sample_code,hepatitis.hepatitis_id,hepatitis.facility_id,f.facility_name,f.facility_code FROM form_hepatitis as hepatitis LEFT JOIN facility_details as f ON hepatitis.facility_id=f.facility_id where ((hepatitis.result_status = 7 AND ((hepatitis.hcv_vl_count is NOT NULL AND hepatitis.hcv_vl_count !='') OR (hepatitis.hbv_vl_count is NOT NULL AND hepatitis.hbv_vl_count !=''))) OR (hepatitis.result_status = 4 AND ((hepatitis.hcv_vl_count is NULL AND hepatitis.hcv_vl_count ='') OR (hepatitis.hbv_vl_count is NULL AND hepatitis.hbv_vl_count =''))))";
if (isset($facility) && array_filter($facility) !== []) {
  $query = $query . " AND hepatitis.facility_id IN (" . implode(',', $facility) . ")";
}
if (trim((string) $sampleType) !== '') {
  $query = $query . " AND hepatitis.specimen_type='" . $sampleType . "'";
}
if (trim((string) $gender) !== '') {
  $query = $query . " AND hepatitis.patient_gender='" . $gender . "'";
}
if (trim((string) $state) !== '') {
  $query = $query . " AND f.facility_state LIKE '%" . $state . "%' ";
}
if (trim((string) $district) !== '') {
  $query = $query . " AND f.facility_district LIKE '%" . $district . "%' ";
}
if (isset($batch) && array_filter($batch) !== []) {
  $query = $query . " AND hepatitis.sample_batch_id IN (" . implode(',', $batch) . ")";
}
if (isset($_POST['status']) && trim((string) $_POST['status']) !== '') {
  $query = $query . " AND hepatitis.result_status='" . $_POST['status'] . "'";
}
if (trim((string) $mailSentStatus) !== '') {
  if (trim((string) $type) === 'request') {
    $query = $query . " AND hepatitis.is_request_mail_sent='" . $mailSentStatus . "'";
  } elseif (trim((string) $type) === 'result') {
    $query = $query . " AND hepatitis.is_result_mail_sent='" . $mailSentStatus . "' AND (hepatitis.hcv_vl_count!= '' OR hepatitis.hbv_vl_count!= '')";
  }
}
if (!empty($_POST['sampleCollectionDate'])) {
  if (trim((string) $start_date) === trim((string) $end_date)) {
    $query = $query . ' AND DATE(sample_collection_date) = "' . $start_date . '"';
  } else {
    $query = $query . ' AND DATE(sample_collection_date) >= "' . $start_date . '" AND DATE(sample_collection_date) <= "' . $end_date . '"';
  }
}
$query .= " ORDER BY f.facility_name ASC";
// echo $query;die;
$result = $db->rawQuery($query);
?>
<div class="col-md-9">
  <div class="form-group">
    <label for="sample" class="col-lg-3 control-label">Choose Sample(s) <span class="mandatory">*</span></label>
    <div class="col-lg-9">
      <div style="width:100%;margin:0 auto;clear:both;">
        <a href="#" id="select-all-sample" style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href="#" id="deselect-all-sample" style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;Deselect All</a>
      </div><br /><br />
      <select id="sample" name="sample[]" multiple="multiple" class="search isRequired" title="Please select sample(s)">
        <?php
        foreach ($result as $sample) {
          if (trim((string) $sample['sample_code']) !== '') {
        ?>
            <option value="<?php echo $sample['hepatitis_id']; ?>"><?= $sample['sample_code']; ?></option>
        <?php
          }
        }
        ?>
      </select>
    </div>
  </div>
</div>
<script>
  $(document).ready(function() {
    $('.search').multiSelect({
      selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample ID'>",
      selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample ID'>",
      afterInit: function(ms) {
        var that = this,
          $selectableSearch = that.$selectableUl.prev(),
          $selectionSearch = that.$selectionUl.prev(),
          selectableSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selectable:not(.ms-selected)',
          selectionSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selection.ms-selected';

        that.qs1 = $selectableSearch.quicksearch(selectableSearchString)
          .on('keydown', function(e) {
            if (e.which === 40) {
              that.$selectableUl.focus();
              return false;
            }
          });

        that.qs2 = $selectionSearch.quicksearch(selectionSearchString)
          .on('keydown', function(e) {
            if (e.which == 40) {
              that.$selectionUl.focus();
              return false;
            }
          });
      },
      afterSelect: function() {
        if (this.qs2.cache().matchedResultsCount > noOfAllowedSamples) {
          $("#errorMsg").html("<strong>You have selected " + this.qs2.cache().matchedResultsCount + " Samples out of the maximum allowed " + noOfAllowedSamples + " samples</strong>");
          $("#requestSubmit").attr("disabled", true);
          $("#requestSubmit").css("pointer-events", "none");
        }
        this.qs1.cache();
        this.qs2.cache();
      },
      afterDeselect: function() {
        if (this.qs2.cache().matchedResultsCount > noOfAllowedSamples) {
          $("#errorMsg").html("<strong>You have selected " + this.qs2.cache().matchedResultsCount + " Samples out of the maximum allowed " + noOfAllowedSamples + " samples</strong>");
          $("#requestSubmit").attr("disabled", true);
          $("#requestSubmit").css("pointer-events", "none");
        } else if (this.qs2.cache().matchedResultsCount <= noOfAllowedSamples) {
          $("#errorMsg").html("");
          $("#requestSubmit").attr("disabled", false);
          $("#requestSubmit").css("pointer-events", "auto");
        }
        this.qs1.cache();
        this.qs2.cache();
      }
    });
    $('#select-all-sample').click(function() {
      $('#sample').multiSelect('select_all');
      return false;
    });
    $('#deselect-all-sample').click(function() {
      $('#sample').multiSelect('deselect_all');
      return false;
    });
  });
</script>
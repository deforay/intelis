<?php

$title = "Covid-19 | Add Batch";

require_once APPLICATION_PATH . '/header.php';

// Sanitize values before using them below
$_GET = array_map('htmlspecialchars', $_GET);

$id = base64_decode($_GET['id']);
$pQuery = "SELECT * FROM covid19_positive_confirmation_manifest WHERE manifest_id=?";
// echo $pQuery;die;
$pResult = $db->rawQueryOne($pQuery, array($id));

$sCode = 'sample_code';
$module = 'C19';

$query = "SELECT vl.sample_code,
        vl.covid19_id,
        vl.facility_id,
        vl.result_status,
        f.facility_name,
        f.facility_code,
        vl.positive_test_manifest_id,
        vl.positive_test_manifest_code
        FROM form_covid19 as vl
        INNER JOIN facility_details as f ON vl.facility_id=f.facility_id
        WHERE (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no')
        AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0)
        AND vl.result = 'positive'
        AND (vl.positive_test_manifest_code IS NULL OR vl.positive_test_manifest_code = '' OR vl.positive_test_manifest_id = ?)
        ORDER BY vl.request_created_datetime ASC";

$result = $db->rawQuery($query, [$id]);

?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
  .select2-selection__choice {
    color: #000000 !important;
  }

  #ms-sampleCode {
    width: 110%;
  }

  .showFemaleSection {
    display: none;
  }

  #sortableRow {
    list-style-type: none;
    margin: 30px 0px 30px 0px;
    padding: 0;
    width: 100%;
    text-align: center;
  }

  #sortableRow li {
    color: #333 !important;
    font-size: 16px;
  }

  #alertText {
    text-shadow: 1px 1px #eee;
  }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-pen-to-square"></em> Positive Confirmation Manifest</h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
      <li><a href="/covid-19/results/covid-19-confirmation-manifest.php"> Manage Positive Confirmation Manifest</a></li>
      <li class="active">Edit Positive Confirmation Manifest</li>
    </ol>
  </section>
  <!-- Main content -->
  <section class="content">

    <div class="box box-default">
      <div class="box-header with-border">
        <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <form class="form-horizontal" method="post" name="editConfirmationManifestForm" id="editConfirmationManifestForm" autocomplete="off" action="covid-19-edit-confirmation-manifest-helper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="manifestCode" class="col-lg-4 control-label">Manifest Code <span class="mandatory">*</span></label>
                  <div class="col-lg-7" style="margin-left:3%;">
                    <input type="text" class="form-control isRequired" id="manifestCode" name="manifestCode" placeholder="Manifest Code" title="Please enter manifest code" readonly value="<?php echo strtoupper($pResult['manifest_code']); ?>" />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="manifestStatus" class="col-lg-4 control-label">Manifest Status <span class="mandatory">*</span></label>
                  <div class="col-lg-7" style="margin-left:3%;">
                    <select class="form-control isRequired" name="manifestStatus" id="manifestStatus" title="Please select manifest status">
                      <option value="">-- Select --</option>
                      <option value="pending" <?php echo ($pResult['manifest_status'] == 'pending') ? "selected='selected'" : ''; ?>>Pending</option>
                      <option value="dispatch" <?php echo ($pResult['manifest_status'] == 'dispatch') ? "selected='selected'" : ''; ?>>Dispatch</option>
                      <option value="received" <?php echo ($pResult['manifest_status'] == 'received') ? "selected='selected'" : ''; ?>>Received</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
            <div class="row" id="sampleDetails">
              <div class="col-md-8">
                <div class="form-group">
                  <div class="col-md-12">
                    <div style="width:60%;margin:0 auto;clear:both;">
                      <a href='#' id='select-all-samplecode' style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;Deselect All</a>
                    </div><br /><br />
                    <select id='sampleCode' name="sampleCode[]" multiple='multiple' class="search">
                      <?php foreach ($result as $sample) {
                        if ($sample[$sCode] != '') { ?>
                          <option value="<?php echo $sample['covid19_id']; ?>" <?php echo ($sample['positive_test_manifest_id'] == $id) ? 'selected="selected"' : ''; ?>><?php echo $sample[$sCode]; ?></option>
                      <?php }
                      } ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>
            <div class="row" id="alertText" style="font-size:18px;"></div>
          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <input type="hidden" name="manifestId" value="<?php echo $pResult['manifest_id']; ?>" />
            <input type="hidden" class="form-control isRequired" id="module" name="module" placeholder="" title="" readonly value="<?= htmlspecialchars($module); ?>" />
            <a id="manifestSubmit" class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
            <a href="/covid-19/results/covid-19-confirmation-manifest.php" class="btn btn-default"> Cancel</a>
          </div>
          <!-- /.box-footer -->
        </form>
        <!-- /.row -->
      </div>
    </div>
    <!-- /.box -->
  </section>
  <!-- /.content -->
</div>
<script src="/assets/js/jquery.multi-select.js"></script>
<script src="/assets/js/jquery.quicksearch.js"></script>
<script type="text/javascript">
  noOfSamples = 100;
  $(document).ready(function() {
    //getSampleCodeDetails();
  });

  function validateNow() {
    flag = deforayValidator.init({
      formId: 'editConfirmationManifestForm'
    });

    if (flag) {
      $.blockUI();
      document.getElementById('editConfirmationManifestForm').submit();
    }
  }

  //$("#auditRndNo").multiselect({height: 100,minWidth: 150});
  $(document).ready(function() {
    $('.search').multiSelect({
      selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
      selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
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
        //button disabled/enabled
        if (this.qs2.cache().matchedResultsCount == noOfSamples) {
          alert("You have selected maximum number of samples - " + this.qs2.cache().matchedResultsCount);
          $("#manifestSubmit").attr("disabled", false);
          $("#manifestSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
          $("#manifestSubmit").attr("disabled", false);
          $("#manifestSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
          alert("You have already selected Maximum no. of sample " + noOfSamples);
          $("#manifestSubmit").attr("disabled", true);
          $("#manifestSubmit").css("pointer-events", "none");
        }
        this.qs1.cache();
        this.qs2.cache();
      },
      afterDeselect: function() {
        //button disabled/enabled
        if (this.qs2.cache().matchedResultsCount == 0) {
          $("#manifestSubmit").attr("disabled", true);
          $("#manifestSubmit").css("pointer-events", "none");
        } else if (this.qs2.cache().matchedResultsCount == noOfSamples) {
          alert("You have selected maximum number of samples - " + this.qs2.cache().matchedResultsCount);
          $("#manifestSubmit").attr("disabled", false);
          $("#manifestSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
          $("#manifestSubmit").attr("disabled", false);
          $("#manifestSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
          $("#manifestSubmit").attr("disabled", true);
          $("#manifestSubmit").css("pointer-events", "none");
        }
        this.qs1.cache();
        this.qs2.cache();
      }
    });
    $('#select-all-samplecode').click(function() {
      $('#sampleCode').multiSelect('select_all');
      return false;
    });
    $('#deselect-all-samplecode').click(function() {
      $('#sampleCode').multiSelect('deselect_all');
      $("#manifestSubmit").attr("disabled", true);
      $("#manifestSubmit").css("pointer-events", "none");
      return false;
    });
  });

  function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
    var removeDots = obj.value.replace(/\./g, "");
    var removeDots = removeDots.replace(/\,/g, "");
    removeDots = removeDots.replace(/\s{2,}/g, ' ');
    $.post("/includes/checkDuplicate.php", {
        tableName: tableName,
        fieldName: fieldName,
        value: removeDots.trim(),
        fnct: fnct,
        format: "html"
      },
      function(data) {
        if (data === '1') {
          alert(alrt);
          duplicateName = false;
          document.getElementById(obj.id).value = "";
        }
      });
  }

  function getSampleCodeDetails() {
    $.blockUI();

    $.post("/specimen-referral-manifest/getSpecimenReferralManifestSampleCodeDetails.php", {
        module: $("#module").val()
      },
      function(data) {
        if (data != "") {
          $("#sampleDetails").html(data);
          $("#manifestSubmit").attr("disabled", true);
          $("#manifestSubmit").css("pointer-events", "none");
        }
      });
    $.unblockUI();
  }
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
?>

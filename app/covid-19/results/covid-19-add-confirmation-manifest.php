<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;


$title = "Covid-19 | Add Batch";

require_once(APPLICATION_PATH . '/header.php');


$module = 'C19';

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$packageNo = strtoupper($module) . date('ymd') .  $general->generateRandomString(6);
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
      <li class="active">Add Positive Confirmation Manifest</li>
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
        <form class="form-horizontal" method="post" name="addConfirmManifestForm" id="addConfirmManifestForm" autocomplete="off" action="covid-19-add-confirmation-manifest-helper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="packageCode" class="col-lg-4 control-label">Confirm Manifest Code <span class="mandatory">*</span></label>
                  <div class="col-lg-7" style="margin-left:3%;">
                    <input type="text" class="form-control isRequired" id="packageCode" name="packageCode" placeholder="Manifest Code" title="Please enter manifest code" readonly value="<?php echo strtoupper(htmlspecialchars($packageNo)); ?>" />
                    <input type="hidden" class="form-control isRequired" id="module" name="module" placeholder="" title="" readonly value="<?= htmlspecialchars($module); ?>" />
                  </div>
                </div>

              </div>
            </div>
          </div>
          <br>
          <div class="row" id="sampleDetails">
            <div class="col-md-8">
              <div class="form-group">
                <div class="col-md-12">
                  <div class="col-md-12">
                    <div style="width:60%;margin:0 auto;clear:both;">
                      <a href='#' id='select-all-samplecode' style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;Deselect All</a>
                    </div><br /><br />
                    <select id='sampleCode' name="sampleCode[]" multiple='multiple' class="search"></select>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row" id="alertText" style="font-size:18px;"></div>
      </div>
      <!-- /.box-body -->
      <div class="box-footer">
        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();">Save </a>
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
  sortedTitle = [];
  $(document).ready(function() {
    getSampleCodeDetails();
  });

  function validateNow() {
    flag = deforayValidator.init({
      formId: 'addConfirmManifestForm'
    });
    if (flag) {
      $.blockUI();
      document.getElementById('addConfirmManifestForm').submit();
    }
  }
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
          $("#packageSubmit").attr("disabled", false);
          $("#packageSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
          $("#packageSubmit").attr("disabled", false);
          $("#packageSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
          alert("You have already selected Maximum no. of sample " + noOfSamples);
          $("#packageSubmit").attr("disabled", true);
          $("#packageSubmit").css("pointer-events", "none");
        }
        this.qs1.cache();
        this.qs2.cache();
      },
      afterDeselect: function() {
        //button disabled/enabled
        if (this.qs2.cache().matchedResultsCount == 0) {
          $("#packageSubmit").attr("disabled", true);
          $("#packageSubmit").css("pointer-events", "none");
        } else if (this.qs2.cache().matchedResultsCount == noOfSamples) {
          alert("You have selected maximum number of samples - " + this.qs2.cache().matchedResultsCount);
          $("#packageSubmit").attr("disabled", false);
          $("#packageSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
          $("#packageSubmit").attr("disabled", false);
          $("#packageSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
          $("#packageSubmit").attr("disabled", true);
          $("#packageSubmit").css("pointer-events", "none");
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
      $("#packageSubmit").attr("disabled", true);
      $("#packageSubmit").css("pointer-events", "none");
      return false;
    });
  });

  function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
    var removeDots = obj.value.replace(/\./g, "");
    var removeDots = removeDots.replace(/\,/g, "");
    //str=obj.value;
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

    $.post("/covid-19/results/get-covid-19-confirmation-manifest.php", {
        module: $("#module").val()
      },
      function(data) {
        if (data != "") {
          $("#sampleDetails").html(data);
          $("#packageSubmit").attr("disabled", true);
          $("#packageSubmit").css("pointer-events", "none");
        }
      });
    $.unblockUI();
  }
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');

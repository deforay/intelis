<?php

$title = "Email Hepatitis Test Results";

require_once APPLICATION_PATH . '/header.php';
$configQuery = "SELECT * FROM global_config WHERE name ='vl_form'";
$configResult = $db->rawQuery($configQuery);
$formId = 0;
if (isset($configResult[0]['value']) && trim($configResult[0]['value']) != '') {
  $formId = intval($configResult[0]['value']);
}
//main query
$query = "SELECT hepatitis.sample_code,hepatitis.hepatitis_id,hepatitis.facility_id,f.facility_name,f.facility_code FROM form_hepatitis as hepatitis LEFT JOIN facility_details as f ON hepatitis.facility_id=f.facility_id where is_result_mail_sent like 'no' AND hepatitis.result IS NOT NULL AND hepatitis.result!= '' ORDER BY f.facility_name ASC";
$result = $db->rawQuery($query);
$sTypeQuery = "SELECT * FROM r_hepatitis_sample_type where status='active'";
$sTypeResult = $db->rawQuery($sTypeQuery);
$facilityQuery = "SELECT * FROM facility_details where status='active'";
$facilityResult = $db->rawQuery($facilityQuery);
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$pdResult = $db->query($pdQuery);
$batchQuery = "SELECT * FROM batch_details";
$batchResult = $db->rawQuery($batchQuery);
?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
  .ms-container {
    width: 100%;
  }

  .select2-selection__choice {
    color: #000000 !important;
  }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1> E-mail Test Result</h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
      <li class="active">E-mail Test Result</li>
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
        <form class="form-horizontal" method="post" name="mailForm" id="mailForm" autocomplete="off" action="hepatitis-result-mail-confirm.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-9">
                <div class="form-group">
                  <label for="subject" class="col-lg-3 control-label">Subject <span class="mandatory">*</span></label>
                  <div class="col-lg-9">
                    <input type="text" id="subject" name="subject" class="form-control isRequired" placeholder="Subject" title="Please enter subject" value="Hepatitis Test Results" />
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-9">
                <div class="form-group">
                  <label for="facility" class="col-lg-3 control-label">Facility Name (To)<span class="mandatory">*</span></label>
                  <div class="col-lg-9">
                    <select class="form-control isRequired" id="facility" name="facility" title="Please select facility name">
                      <option value=""> -- Select -- </option>
                      <?php
                      foreach ($facilityResult as $facility) { ?>
                        ?>
                        <option data-name="<?php echo $facility['facility_name']; ?>" data-email="<?php echo $facility['facility_emails']; ?>" data-report-email="<?php echo $facility['report_email']; ?>" value="<?php echo base64_encode($facility['facility_id']); ?>"><?php echo ($facility['facility_name']); ?></option>
                      <?php } ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12 emailSection" style="text-align:center;margin-bottom:10px;"></div>
            </div>
            <div class="row">
              <div class="col-md-9">
                <div class="form-group">
                  <label for="message" class="col-lg-3 control-label">Message <span class="mandatory">*</span></label>
                  <div class="col-lg-9">
                    <textarea id="message" name="message" class="form-control isRequired" row="10" placeholder="Message" title="Please enter message" style="height:80px;"></textarea>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12">
                <table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:90%;">
                  <tr>
                    <td>&nbsp;<strong>Sample Collection Date&nbsp;:</strong></td>
                    <td>
                      <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:275px;background:#fff;" />
                    </td>
                    <td>&nbsp;<strong>Sample Type&nbsp;:</strong></td>
                    <td>
                      <select class="form-control" id="sampleType" name="sampleType" title="Please select sample type">
                        <option value=""> -- Select -- </option>
                        <?php
                        foreach ($sTypeResult as $type) {
                        ?>
                          <option value="<?php echo $type['sample_id']; ?>"><?= $type['sample_name']; ?></option>
                        <?php
                        }
                        ?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;<strong>Facility Name&nbsp;:</strong></td>
                    <td>
                      <select style="width: 275px;" class="form-control" id="facilityName" name="facilityName" title="Please select facility name" multiple="multiple">
                        <?php
                        foreach ($facilityResult as $name) {
                        ?>
                          <option value="<?php echo $name['facility_id']; ?>"><?php echo ($name['facility_name'] . "-" . $name['facility_code']); ?></option>
                        <?php
                        }
                        ?>
                      </select>
                    </td>
                    <td><strong>Gender&nbsp;:</strong></td>
                    <td>
                      <select name="gender" id="gender" class="form-control" title="Please choose gender" onchange="enablePregnant(this);">
                        <option value=""> -- Select -- </option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="not_recorded">Not Recorded</option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;<strong>Province/State &nbsp;:</strong></td>
                    <td>
                      <select name="state" id="state" class="form-control" title="Please choose province/state" onchange="getProvinceDistricts();" style="width:275px;">
                        <option value=""> -- Select -- </option>
                        <?php
                        foreach ($pdResult as $province) {
                        ?>
                          <option value="<?php echo $province['geo_name']; ?>"><?= $province['geo_name']; ?></option>
                        <?php } ?>
                      </select>
                    </td>
                    <td>&nbsp;<strong>District/County&nbsp;:</strong></td>
                    <td>
                      <select name="district" id="district" class="form-control" title="Please choose district/county">
                        <option value=""> -- Select -- </option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td class=""><strong>Batch&nbsp;:</strong></td>
                    <td>
                      <select name="batch" id="batch" class="form-control" title="Please choose batch" style="width:275px;" multiple="multiple">
                        <option value=""> -- Select -- </option>
                        <?php
                        foreach ($batchResult as $batch) {
                        ?>
                          <option value="<?php echo $batch['batch_id']; ?>"><?php echo $batch['batch_code']; ?></option>
                        <?php } ?>
                      </select>
                    </td>
                    <td class=""><strong>Sample Status&nbsp;:</strong></td>
                    <td>
                      <select name="sampleStatus" id="sampleStatus" class="form-control" title="Please choose sample status">
                        <option value=""> -- Select -- </option>
                        <option value="7">Accepted</option>
                        <option value="4">Rejected</option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td class=""><strong>Mail Sent Status&nbsp;:</strong></td>
                    <td>
                      <select name="sampleMailSentStatus" id="sampleMailSentStatus" class="form-control" title="Please choose sample mail sent status" style="width:275px;">
                        <option value="no">Samples Not yet Mailed</option>
                        <option value="">All Samples</option>
                        <option value="yes">Already Mailed Samples</option>
                      </select>
                    </td>
                    <td></td>
                    <td></td>
                  </tr>
                  <tr>
                    <td colspan="4" style="text-align:center;">&nbsp;<input type="button" class="btn btn-success btn-sm" onclick="getSampleDetails();" value="Search" />
                      &nbsp;<input type="button" class="btn btn-danger btn-sm" value="Reset" onclick="document.location.href = document.location;" />
                    </td>
                  </tr>
                </table>
              </div>
            </div>
            <div class="row">
              <div class="col-md-4"></div>
              <div class="col-md-8"><strong>Please select maximum 100 samples</strong></div>
            </div>
            <div class="row" id="sampleDetails">
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
                        if (trim($sample['sample_code']) != '') {
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
            </div>
            <div class="row">
              <div class="col-md-3"></div>
              <div class="col-md-9" id="errorMsg" style="color: #dd4b39;"></div>
            </div>
          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <input type="hidden" id="type" name="type" value="result" />
            <input type="hidden" id="toName" name="toName" />
            <input type="hidden" id="toEmail" name="toEmail" />
            <input type="hidden" id="reportEmail" name="reportEmail" />
            <input type="hidden" name="pdfFile" id="pdfFile" />
            <a href="/vl/result-mail/testResultEmailConfig.php" class="btn btn-default"> Cancel</a>&nbsp;
            <a class="btn btn-primary" id="requestSubmit" href="javascript:void(0);" onclick="validateNow();return false;">Next <em class="fa-solid fa-chevron-right"></em></a>
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
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
  noOfAllowedSamples = 100;
  var startDate = "";
  var endDate = "";
  $(document).ready(function() {
    document.getElementById('message').value = "Hi, \n Please find the the Hepatitis Test results attached. \nThanks";
    $('#facility').select2({
      placeholder: "Select Facility"
    });
    $('#facilityName').select2({
      placeholder: "Select Facilities"
    });
    $('#batch').select2({
      placeholder: "Select Batches"
    });
    $('#sampleCollectionDate').daterangepicker({
        locale: {
          cancelLabel: "<?= _("Clear"); ?>",
          format: 'DD-MMM-YYYY',
          separator: ' to ',
        },
        showDropdowns: true,
        alwaysShowCalendars: false,
        startDate: moment().subtract(28, 'days'),
        endDate: moment(),
        maxDate: moment(),
        ranges: {
          'Today': [moment(), moment()],
          'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
          'Last 7 Days': [moment().subtract(6, 'days'), moment()],
          'Last 30 Days': [moment().subtract(29, 'days'), moment()],
          'This Month': [moment().startOf('month'), moment().endOf('month')],
          'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
      },
      function(start, end) {
        startDate = start.format('YYYY-MM-DD');
        endDate = end.format('YYYY-MM-DD');
      });
    $('#sampleCollectionDate').val("");

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
        //button disabled
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

  function enablePregnant(obj) {
    if (obj.value == "female") {
      $(".pregnant").prop("disabled", false);
    } else {
      $(".pregnant").prop("checked", false);
      $(".pregnant").attr("disabled", true);
    }
  }

  function getSampleDetails() {
    $.blockUI();
    var facilityName = $("#facilityName").val();
    var sTypeName = $("#sampleType").val();
    var gender = $("#gender").val();
    $("#errorMsg").html("");
    var state = $('#state').val();
    var district = $('#district').val();
    var batch = $('#batch').val();
    var status = $('#sampleStatus').val();
    var sampleMailSentStatus = $('#sampleMailSentStatus').val();
    var type = $('#type').val();
    $.post("/hepatitis/mail/get-samples-for-mail.php", {
        facility: facilityName,
        sType: sTypeName,
        sampleCollectionDate: $("#sampleCollectionDate").val(),
        gender: gender,
        state: state,
        district: district,
        batch: batch,
        status: status,
        mailSentStatus: sampleMailSentStatus,
        type: type
      },
      function(data) {
        if ($.trim(data) !== "") {
          $("#sampleDetails").html(data);
        }
      });
    $.unblockUI();
  }

  function convertSearchResultToPdf() {
    $.blockUI();
    var sample = $("#sample").val();
    var id = sample.toString();
    $.post("/hepatitis/results/generate-result-pdf.php", {
        source: 'print',
        id: id,
        resultMail: 'resultMail'
      },
      function(data) {
        if (data === "" || data === null || data === undefined) {
          $.unblockUI();
          alert('Cannot generate Result PDF for samples without result.');
        } else {
          $.blockUI();
          $("#pdfFile").val(data);
          document.getElementById('mailForm').submit();
        }
      });
  }

  $('#facility').change(function(e) {
    if ($(this).val() == '') {
      $('.emailSection').html('');
      $('#toName').val('');
      $('#toEmail').val('');
      $('#reportEmail').val('');
    } else {
      var toName = $(this).find(':selected').data('name');
      var toEmailId = $(this).find(':selected').data('email');
      var reportEmailId = $(this).find(':selected').data('report-email');
      if ($.trim(toEmailId) == '') {
        $('.emailSection').html('No valid Email ID available. Please add a valid email for this facility.');
      } else {
        $('.emailSection').html('<mark>This email will be sent to the facility with an email id <strong>' + toEmailId + '</strong></mark>');
      }
      $('#toName').val(toName);
      $('#toEmail').val(toEmailId);
      $('#reportEmail').val(reportEmailId);
    }
  });

  function getProvinceDistricts() {
    var pName = $("#state").val();
    if ($.trim(pName) != '') {
      $.post("/includes/siteInformationDropdownOptions.php", {
          pName: pName,
          testType: 'hepatitis'
        },
        function(data) {
          if ($.trim(data) != "") {
            details = data.split("###");
            $("#district").html(details[1]);
          } else {
            $("#district").html('<option value=""> -- Select -- </option>');
          }
        });
    } else {
      $("#district").html('<option value=""> -- Select -- </option>');
    }
  }

  function validateNow() {
    flag = deforayValidator.init({
      formId: 'mailForm'
    });

    if (flag) {
      $.blockUI();
      convertSearchResultToPdf();
    }
  }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';

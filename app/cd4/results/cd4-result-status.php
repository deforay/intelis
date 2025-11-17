<?php

use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("Manage Result Status");

require_once APPLICATION_PATH . '/header.php';


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$healthFacilites = $facilitiesService->getHealthFacilities('cd4');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);

$sQuery = "SELECT * FROM r_cd4_sample_types";
$sResult = $db->rawQuery($sQuery);

$batQuery = "SELECT batch_code FROM batch_details where test_type = 'cd4' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);

$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_cd4_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_cd4_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);

$rejectionReason = '<option value="">-- ' . _translate("Select Sample Rejection Reason") . ' --</option>';
foreach ($rejectionTypeResult as $type) {
  $rejectionReason .= '<optgroup label="' . strtoupper((string) $type['rejection_type']) . '">';
  foreach ($rejectionResult as $reject) {
    if ($type['rejection_type'] == $reject['rejection_type']) {
      $rejectionReason .= '<option value="' . $reject['rejection_reason_id'] . '">' . ($reject['rejection_reason_name']) . '</option>';
    }
  }
  $rejectionReason .= '</optgroup>';
}
?>
<style>
  .select2-selection__choice {
    color: black !important;
  }

  #rejectReasonDiv {
    border: 1px solid #ecf0f5;
    box-shadow: 3px 3px 15px #000;
    background-color: #ecf0f5;
    width: 50%;
    display: none;
    padding: 10px;
    border-radius: 10px;
  }

  .arrow-right {
    width: 0;
    height: 0;
    border-top: 15px solid transparent;
    border-bottom: 15px solid transparent;
    border-left: 15px solid #ecf0f5;
    position: absolute;
    left: 100%;
    top: 24px;
  }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-list-check"></em>
      <?php echo _translate("Results Approval"); ?>
    </h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
          <?php echo _translate("Home"); ?>
        </a></li>
      <li class="active">
        <?php echo _translate("Test Request"); ?>
      </li>
    </ol>
  </section>

  <!-- for sample rejection -->
  <div id="rejectReasonDiv">
    <a href="javascript:void(0)" style="float:right;color:red;" onclick="hideReasonDiv('rejectReasonDiv')"><em class="fa-solid fa-xmark"></em></a>
    <div class="arrow-right"></div>
    <input type="hidden" name="statusDropDownId" id="statusDropDownId" />
    <h3 style="color:red;">
      <?php echo _translate("Choose Rejection Reason"); ?>
    </h3>
    <select name="rejectionReason" id="rejectionReason" class="form-control" title="<?php echo _translate('Please choose reason'); ?>" onchange="updateRejectionReasonStatus(this);">
      <option value=''>
        <?php echo _translate("-- Select --"); ?>
      </option>
      <?php echo $rejectionReason; ?>
    </select>

  </div>
  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;">
            <tr>
              <td><strong>
                  <?php echo _translate("Sample Collection Date"); ?>&nbsp;:
                </strong></td>
              <td>
                <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="<?php echo _translate('Select Collection Date'); ?>" readonly style="width:220px;background:#fff;" />
              </td>
              <td>&nbsp;<strong>
                  <?php echo _translate("Batch Code"); ?>&nbsp;:
                </strong></td>
              <td>
                <select class="form-control" id="batchCode" name="batchCode" title="<?php echo _translate('Please select batch code'); ?>" style="width:220px;">
                  <option value="">
                    <?php echo _translate("-- Select --"); ?>
                  </option>
                  <?php
                  foreach ($batResult as $code) {
                  ?>
                    <option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
                  <?php
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr>
              <td>&nbsp;<strong>
                  <?php echo _translate("Sample Type"); ?>&nbsp;:
                </strong></td>
              <td>
                <select style="width:220px;" class="form-control" id="sampleType" name="sampleType" title="<?php echo _translate('Please select sample type'); ?>">
                  <option value="">
                    <?php echo _translate("-- Select --"); ?>
                  </option>
                  <?php
                  foreach ($sResult as $type) {
                  ?>
                    <option value="<?php echo $type['sample_id']; ?>"><?= $type['sample_name']; ?></option>
                  <?php
                  }
                  ?>
                </select>
              </td>
              <td>&nbsp;<strong>
                  <?php echo _translate("Facility Name"); ?>&nbsp;:
                </strong></td>
              <td>
                <select class="form-control" id="facilityName" name="facilityName" title="<?php echo _translate('Please select facility name'); ?>" multiple="multiple" style="width:220px;">
                  <?= $facilitiesDropdown; ?>
                </select>
              </td>
            </tr>
            <tr>
              <td>&nbsp;<strong>
                  <?php echo _translate("Show Samples that are"); ?> &nbsp;:
                </strong></td>
              <td>
                <select class="form-control" id="statusFilter" name="statusFilter" title="<?php echo _translate('Please choose a status'); ?>" style="width:220px;">
                  <option value="notApprovedOrRejected">
                    <?php echo _translate("Not Approved/Rejected"); ?>
                  </option>
                  <option value="approvedOrRejected">
                    <?php echo _translate("Already Approved/Rejected"); ?>
                  </option>
                </select>
              </td>
            </tr>
            <tr>
              <td colspan="3">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?= _translate('Search'); ?>" class="btn btn-success btn-sm">
                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>
                    <?= _translate('Reset'); ?>
                  </span></button>

              </td>
            </tr>

          </table>
          <div class="box-header with-border">
            <div class="col-md-3 col-sm-3">
              <input type="hidden" name="checkedTests" id="checkedTests" />
              <select class="form-control" id="status" name="status" title="<?php echo _translate('Please select test status'); ?>" disabled="disabled" onchange="showSampleRejectionReason()">
                <option value="">
                  <?php echo _translate("-- Select at least one sample to apply bulk action --"); ?>
                </option>
                <option value="7">
                  <?php echo _translate("Accepted"); ?>
                </option>
                <option value="4">
                  <?php echo _translate("Rejected"); ?>
                </option>
                <option value="2">
                  <?php echo _translate("Lost"); ?>
                </option>
              </select>
            </div>
             <div class="col-md-2 col-sm-2">
               <select class="form-control" id="approver" name="approver" title="<?php echo _translate('Please select approver'); ?>" disabled="disabled">
                <option value="">
                  <?php echo _translate("-- Select Approver --"); ?>
                </option>
                  <?php foreach ($userResult as $uName) { ?>
                      <option value="<?php echo $uName['user_id']; ?>"><?php echo ($uName['user_name']); ?></option>
                  <?php } ?>
                </select>
                  </div>
                  <div class="col-md-2 col-sm-2">
                 <select class="form-control" id="tester" name="tester" title="<?php echo _translate('Please select tester'); ?>" disabled="disabled">
                <option value="">
                  <?php echo _translate("-- Select Tester --"); ?>
                </option>
                  <?php foreach ($userResult as $uName) { ?>
                      <option value="<?php echo $uName['user_id']; ?>"><?php echo ($uName['user_name']); ?></option>
                  <?php } ?>
                </select>
                  </div>
                  <div class="col-md-2 col-sm-2">
                 <select class="form-control" id="reviewer" name="reviewer" title="<?php echo _translate('Please select reviewer'); ?>" disabled="disabled">
                <option value="">
                  <?php echo _translate("-- Select Reviewer --"); ?>
                </option>
                  <?php foreach ($userResult as $uName) { ?>
                      <option value="<?php echo $uName['user_id']; ?>"><?php echo ($uName['user_name']); ?></option>
                  <?php } ?>
                </select>
                 
            </div>
            <div style="display:none;" class="col-md-5 col-sm-5 bulkRejectionReason">
              <select class="form-control" id="bulkRejectionReason" name="bulkRejectionReason" title="<?php echo _translate('Please select test status'); ?>">
                <!-- <option value=''> -- Select -- </option> -->
                <?php echo $rejectionReason; ?>
              </select>
            </div>
            <div class="col-md-2 col-sm-2"><input type="button" onclick="submitTestStatus();" value="<?php echo _translate('Apply'); ?>" class="btn btn-success btn-sm"></div>
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <table aria-describedby="table" id="vlRequestDataTable" class="table table-bordered table-striped" aria-hidden="true">
              <thead>
                <tr>
                  <th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()" /></th>
                  <th>
                    <?php echo _translate("Sample ID"); ?>
                  </th>
                  <?php if (!$general->isStandaloneInstance()) { ?>
                    <th>
                      <?php echo _translate("Remote Sample ID"); ?>
                    </th>
                  <?php } ?>
                  <th scope="row">
                    <?php echo _translate("Sample Collection Date"); ?>
                  </th>
                  <th>
                    <?php echo _translate("Batch Code"); ?>
                  </th>
                  <th>
                    <?php echo _translate("Unique ART No"); ?>
                  </th>
                  <th>
                    <?php echo _translate("Patient's Name"); ?>
                  </th>
                  <th scope="row">
                    <?php echo _translate("Facility Name"); ?>
                  </th>
                  <th>
                    <?php echo _translate("Sample Type"); ?>
                  </th>
                  <th>
                    <?php echo _translate("Result"); ?>
                  </th>
                  <th>
                    <?php echo _translate("Last Modified on"); ?>
                  </th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="13" class="dataTables_empty">
                    <?php echo _translate("Loading data from server"); ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <!-- /.box-body -->
        </div>
        <!-- /.box -->

      </div>
      <!-- /.col -->
    </div>
    <!-- /.row -->
  </section>
  <!-- /.content -->
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
  var startDate = "";
  var endDate = "";
  var selectedTests = [];
  var selectedTestsId = [];
  $(document).ready(function() {
    $("#facilityName").select2({
      placeholder: "<?php echo _translate("Select Facilities"); ?>"
    });
    $('#sampleCollectionDate').daterangepicker({
        locale: {
          cancelLabel: "<?= _translate("Clear", true); ?>",
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
          'This Month': [moment().startOf('month'), moment().endOf('month')],
          'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
          'Last 30 Days': [moment().subtract(29, 'days'), moment()],
          'Last 90 Days': [moment().subtract(89, 'days'), moment()],
          'Last 120 Days': [moment().subtract(119, 'days'), moment()],
          'Last 180 Days': [moment().subtract(179, 'days'), moment()],
          'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
          'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
          'Current Year To Date': [moment().startOf('year'), moment()]
        }
      },
      function(start, end) {
        startDate = start.format('YYYY-MM-DD');
        endDate = end.format('YYYY-MM-DD');
      });
    $('#sampleCollectionDate').val("");
    loadVlRequestData();
  });

  var oTable = null;

  function loadVlRequestData() {
    $.blockUI();
    oTable = $('#vlRequestDataTable').dataTable({
      "bJQueryUI": false,
      "bAutoWidth": false,
      "bInfo": true,
      "bScrollCollapse": true,
      //"bStateSave" : true,
      "iDisplayLength": 100,
      "bRetrieve": true,
      "aoColumns": [{
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center"
        },
        <?php if (!$general->isStandaloneInstance()) { ?> {
            "sClass": "center"
          },
        <?php } ?> {
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        {
          "sClass": "center",
          "bSortable": false
        },

      ],
      "aaSorting": [
        [<?= ($general->isStandaloneInstance()) ? 9 : 10; ?>, "desc"]
      ],
      "fnDrawCallback": function() {
        var checkBoxes = document.getElementsByName("chk[]");
        len = checkBoxes.length;
        for (c = 0; c < len; c++) {
          if (jQuery.inArray(checkBoxes[c].id, selectedTestsId) != -1) {
            checkBoxes[c].setAttribute("checked", true);
          }
        }
      },
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "/cd4/results/get-cd4-result-status.php",
      "fnServerData": function(sSource, aoData, fnCallback) {
        aoData.push({
          "name": "batchCode",
          "value": $("#batchCode").val()
        });
        aoData.push({
          "name": "sampleCollectionDate",
          "value": $("#sampleCollectionDate").val()
        });
        aoData.push({
          "name": "facilityName",
          "value": $("#facilityName").val()
        });
        aoData.push({
          "name": "sampleType",
          "value": $("#sampleType").val()
        });
        aoData.push({
          "name": "statusFilter",
          "value": $("#statusFilter").val()
        });
        $.ajax({
          "dataType": 'json',
          "type": "POST",
          "url": sSource,
          "data": aoData,
          "success": fnCallback
        });
      }
    });
    $.unblockUI();
  }

  function searchVlRequestData() {
    $.blockUI();
    oTable.fnDraw();
    $.unblockUI();
  }

  function toggleTest(obj) {
    if ($(obj).is(':checked')) {
      if ($.inArray(obj.value, selectedTests) == -1) {
        selectedTests.push(obj.value);
        selectedTestsId.push(obj.id);
      }
    } else {
      selectedTests.splice($.inArray(obj.value, selectedTests), 1);
      selectedTestsId.splice($.inArray(obj.id, selectedTestsId), 1);
      $("#checkTestsData").attr("checked", false);
    }
    $("#checkedTests").val(selectedTests.join());
    if (selectedTests.length != 0) {
      $("#status").prop('disabled', false);
      $("#approver").prop('disabled', false);
      $("#tester").prop('disabled', false);
      $("#reviewer").prop('disabled', false);
    } else {
      $("#status").prop('disabled', true);
      $("#approver").prop('disabled', true);
      $("#tester").prop('disabled', true);
      $("#reviewer").prop('disabled', true);
    }

  }

  function toggleAllVisible() {
    //alert(tabStatus);
    $(".checkTests").each(function() {
      $(this).prop('checked', false);
      selectedTests.splice($.inArray(this.value, selectedTests), 1);
      selectedTestsId.splice($.inArray(this.id, selectedTestsId), 1);
      $("#status").prop('disabled', true);
      $("#approver").prop('disabled', true);
      $("#tester").prop('disabled', true);
      $("#reviewer").prop('disabled', true);
    });
    if ($("#checkTestsData").is(':checked')) {
      $(".checkTests").each(function() {
        $(this).prop('checked', true);
        selectedTests.push(this.value);
        selectedTestsId.push(this.id);
      });
      $("#status").prop('disabled', false);
      $("#approver").prop('disabled', false);
      $("#tester").prop('disabled', false);
      $("#reviewer").prop('disabled', false);
    } else {
      $(".checkTests").each(function() {
        $(this).prop('checked', false);
        selectedTests.splice($.inArray(this.value, selectedTests), 1);
        selectedTestsId.splice($.inArray(this.id, selectedTestsId), 1);
        $("#status").prop('disabled', true);
        $("#approver").prop('disabled', true);
        $("#tester").prop('disabled', true);
        $("#reviewer").prop('disabled', true);
      });
    }
    $("#checkedTests").val(selectedTests.join());
  }

  function submitTestStatus() {
            $.blockUI();

    var stValue = $("#status").val();
    var approver = $("#approver").val();
    var tester = $("#tester").val();
    var reviewer = $("#reviewer").val();
    var testIds = $("#checkedTests").val();
      if(testIds != ''){
      if ((stValue != '' && approver != '' && reviewer != '')) {
      conf = confirm("<?= _translate("Are you sure you want to modify the sample status?", true); ?>");
      if (conf) {
        $.post("/cd4/results/updateTestStatus.php", {
            status: stValue,
            id: testIds,
            rejectedReason: $("#bulkRejectionReason").val()
          },
          function(data) {
            if (data != "") {
              $("#checkedTests").val('');
              selectedTests = [];
              selectedTestsId = [];
              $("#checkTestsData").attr("checked", false);
              $("#status").val('');
              $("#status").prop('disabled', true);
              $("#bulkRejectionReason").val('');
              $(".bulkRejectionReason").hide();
              oTable.fnDraw();
              alert("<?= _translate("Updated successfully.", true); ?>");
            }
          });
      }
      }
      else{
        alert("<?= _translate("Please select Status, Approver & Reviewer fields to update", true); ?>");
      }
    } else {
      alert("<?= _translate("Please select at least one checkbox"); ?>");
    }
        $.unblockUI();

  }
   

  function updateStatus(obj, optVal) {
    if (obj.value == '4') {
      var confrm = confirm("<?= _translate("Do you wish to overwrite this result?", true); ?>");
      if (confrm) {
        var pos = $("#" + obj.id).offset();
        $("#rejectReasonDiv").show();
        $("#rejectReasonDiv").css({
          top: Math.round(pos.top) - 30,
          position: 'absolute',
          'z-index': 1,
          right: '15%'
        });
        $("#statusDropDownId").val(obj.id);
        return false;
      } else {
        $("#" + obj.id).val(optVal);
        return false;
      }
    } else {
      $("#rejectReasonDiv").hide();
    }
    if (obj.value != '') {
      conf = confirm("<?= _translate("Do you wish to change the status?", true); ?>");
      if (conf) {
        $.post("/cd4/results/update-status.php", {
            status: obj.value,
            id: obj.id
          },
          function(data) {
            if (data != "") {
              $("#checkedTests").val('');
              selectedTests = [];
              selectedTestsId = [];
              $("#checkTestsData").attr("checked", false);
              $("#status").val('');
              $("#status").prop('disabled', true);
              oTable.fnDraw();
              alert("<?= _translate("Updated successfully.", true); ?>");
            }
          });
      } else {
        $("#rejectReasonDiv").hide();
      }
    }
  }

  function updateRejectionReasonStatus(obj) {
    if (obj.value != '') {
      conf = confirm("<?= _translate("Do you wish to change the status?", true); ?>");
      if (conf) {
        $.post("/cd4/results/updateTestStatus.php", {
            status: '4',
            id: $("#statusDropDownId").val(),
            rejectedReason: obj.value
          },
          function(data) {
            if (data != "") {
              $("#checkedTests").val('');
              selectedTests = [];
              selectedTestsId = [];
              $("#checkTestsData").attr("checked", false);
              $("#status").val('');
              $("#status").prop('disabled', true);
              $("#rejectReasonDiv").hide();
              $("#statusDropDownId").val('');
              $("#rejectionReason").val('');
              oTable.fnDraw();
              alert("<?= _translate("Updated successfully", true); ?>");
            }
          });
      } else {
        $("#rejectReasonDiv").hide();
      }
    }
  }

  function showSampleRejectionReason() {
    if ($("#status").val() == '4') {
      $(".bulkRejectionReason").show();
    } else {
      $("#bulkRejectionReason").val('');
      $(".bulkRejectionReason").hide();
    }
  }

  function hideReasonDiv(id) {
    $("#" + id).hide();
  }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';

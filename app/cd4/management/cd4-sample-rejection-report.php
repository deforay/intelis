<?php

use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;

$title = _translate("CD4 | Sample Rejection Report");

require_once APPLICATION_PATH . '/header.php';

// $tsQuery = "SELECT * FROM r_sample_status";
// $tsResult = $db->rawQuery($tsQuery);


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


$healthFacilites = $facilitiesService->getHealthFacilities('cd4');
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");
$testingLabs = $facilitiesService->getTestingLabs('cd4');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");


?>
<style nonce="<?= $_SESSION['nonce']; ?>">
  .select2-selection__choice {
    color: black !important;
  }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-book"></em> <?php echo _translate("Sample Rejection Report"); ?></h1>
    <ol class="breadcrumb">
      <!-- <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li> -->
      <li><em class="fa-solid fa-book"></em> <?php echo _translate("CD4"); ?></li>
      <li><?php echo _translate("Management"); ?></li>
      <li class="active"><?php echo _translate("Rejection Result"); ?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
            <tr>
              <td><strong><?php echo _translate("Sample Collection Date"); ?>&nbsp;:</strong></td>
              <td>
                <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="<?php echo _translate('Select Collection Date'); ?>" readonly style="width:220px;background:#fff;" />
              </td>
              <td>&nbsp;<strong><?php echo _translate("Lab"); ?> &nbsp;:</strong></td>
              <td>
                <select class="form-control" id="labName" name="labName" title="<?php echo _translate('Please select lab name'); ?>" style="width:220px;">
                  <?= $testingLabsDropdown; ?>
                </select>
              </td>
            </tr>
            <tr>

              <td>&nbsp;<strong><?php echo _translate("Clinic Name"); ?> &nbsp;:</strong></td>
              <td>
                <select class="form-control" id="clinicName" name="clinicName" title="<?php echo _translate('Please select clinic name'); ?>" multiple="multiple" style="width:220px;">
                  <?= $facilitiesDropdown; ?>
                </select>
              </td>
              <td></td>
              <td></td>

            </tr>
            <tr>
              <td colspan="4">&nbsp;<input type="button" onclick="searchResultData();" value="<?php echo _translate("Search"); ?>" class="btn btn-success btn-sm">
                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>
              </td>
            </tr>

          </table>
          <!-- /.box-header -->
          <div class="box-body" id="pieChartDiv">

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
<script nonce="<?= $_SESSION['nonce']; ?>" src="/assets/js/moment.min.js"></script>
<script nonce="<?= $_SESSION['nonce']; ?>" type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script nonce="<?= $_SESSION['nonce']; ?>" src="/assets/js/highcharts.js"></script>
<script>
  $(function() {
    $("#labName").select2({
      placeholder: "<?php echo _translate("Select Labs"); ?>"
    });
    $("#clinicName").select2({
      placeholder: "<?php echo _translate("Select Clinics"); ?>"
    });
    $('#sampleCollectionDate').daterangepicker({
        locale: {
          cancelLabel: "<?= _translate("Clear", true); ?>",
          format: 'DD-MMM-YYYY',
          separator: ' to ',
        },
        startDate: moment().subtract('days', 365),
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
    searchResultData();
  });

  function searchResultData() {
    $.blockUI();
    $.post("/cd4/management/get-rejected-samples.php", {
        sampleCollectionDate: $("#sampleCollectionDate").val(),
        labName: $("#labName").val(),
        clinicName: $("#clinicName").val()
      },
      function(data) {
        if (data != '') {
          $("#pieChartDiv").html(data);
        }
      });
    $.unblockUI();
  }

  function exportInexcel() {
    $.blockUI();
    $.post("/cd4/management/generate-rejected-samples-export.php", {
        sampleCollectionDate: $("#sampleCollectionDate").val(),
        lab_name: $("#labName").val(),
        clinic_name: $("#clinicName").val()
      },
      function(data) {
        if (data == "" || data == null || data == undefined) {
          $.unblockUI();
          alert("<?php echo _translate("Unable to generate excel"); ?>.");
        } else {
          $.unblockUI();
          location.href = '/temporary/' + data;
        }
      });
  }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';

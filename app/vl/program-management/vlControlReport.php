<?php
$title = _("VL Control Report");

require_once(APPLICATION_PATH . '/header.php');
$sQuery = "SELECT * FROM r_sample_controls where r_sample_control_name!='s'";
$sResult = $db->rawQuery($sQuery);
?>
<style>
  .select2-selection__choice {
    color: #000000 !important;
  }

  .center {
    text-align: center;
  }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("VL Control Report"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
      <li class="active"><?php echo _("VL Control Report"); ?></li>
    </ol>
  </section>
  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <table class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:80%;">
            <tr>
              <td><strong><?php echo _("Sample Tested Date"); ?>&nbsp;</strong><span class="mandatory">*</span></td>
              <td><input type="text" id="sampleTestDate" name="sampleTestDate" class="form-control" placeholder="<?php echo _('Select Tested Date'); ?>" readonly style="width:220px;background:#fff;" /></td>
              <td><strong><?php echo _("Control Type"); ?>&nbsp;</strong><span class="mandatory">*</span></td>
              <td>
                <select id="cType" name="cType" class="form-control" title="<?php echo _('Choose control type'); ?>">
                  <option value=""><?php echo _("-- Select --"); ?></option>
                  <?php
                  foreach ($sResult as $control) {
                  ?>
                    <option value="<?php echo $control['r_sample_control_name']; ?>"><?php echo $control['r_sample_control_name']; ?></option>
                  <?php
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr>
              <td colspan="6">&nbsp;<input type="button" onclick="loadControlChart();" value="<?php echo _('Search'); ?>" class="btn btn-success btn-sm">
                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
              </td>
            </tr>
          </table>
          <!-- /.box-header -->
          <div class="box-body" style="margin-top:-30px;" id="chart">

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
<script src="/assets/js/highcharts.js"></script>
<script src="/assets/js/exporting.js"></script>
<script src="/assets/js/accessibility.js"></script>
<script type="text/javascript">
  var startDate = "";
  var endDate = "";
  $(document).ready(function() {
    $('#sampleTestDate').daterangepicker({
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
          'This Month': [moment().startOf('month'), moment().endOf('month')],
          'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
          'Last 30 Days': [moment().subtract(29, 'days'), moment()],
          'Last 90 Days': [moment().subtract(89, 'days'), moment()],
          'Last 120 Days': [moment().subtract(119, 'days'), moment()],
          'Last 180 Days': [moment().subtract(179, 'days'), moment()],
          'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')]
        }
      },
      function(start, end) {
        startDate = start.format('YYYY-MM-DD');
        endDate = end.format('YYYY-MM-DD');
      });
    $('#sampleTestDate').val("");
    //loadControlChart();
  });

  function loadControlChart() {
    if ($("#sampleTestDate").val() != '' && $("#cType").val() != '') {
      $.blockUI();
      $.post("/vl/program-management/getControlChart.php", {
          sampleTestDate: $("#sampleTestDate").val(),
          cType: $("#cType").val()
        },
        function(data) {
          $("#chart").html(data);
        });
      $.unblockUI();
    } else {
      alert("<?php echo _("Please choose Sample Test Date Range and Control Type to generate the report"); ?>");
    }
  }
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');

<?php
$title = _translate("VL Control Report");

require_once APPLICATION_PATH . '/header.php';

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

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
    <h1><em class="fa-solid fa-pen-to-square"></em>
      <?php echo _translate("VL Control Report"); ?>
    </h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
          <?php echo _translate("Home"); ?>
        </a></li>
      <li class="active">
        <?php echo _translate("VL Control Report"); ?>
      </li>
    </ol>
  </section>
  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <div class="box-header with-border">
            <a href="upload-vl-control.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Bulk Upload"); ?></a>
          </div>
          <table aria-describedby="table" class="table pageFilters" aria-hidden="true" style="margin-left:1%;width:80%;">
            <tr>
              <td><strong>
                  <?php echo _translate("Sample Tested Date"); ?>&nbsp;
                </strong><span class="mandatory">*</span></td>
              <td><input type="text" id="sampleTestDate" name="sampleTestDate" class="form-control" placeholder="<?php echo _translate('Select Tested Date'); ?>" readonly style="width:220px;background:#fff;" />
              </td>
              <td><strong>
                  <?php echo _translate("Control Type"); ?>&nbsp;
                </strong><span class="mandatory">*</span></td>
              <td>
                <select id="cType" name="cType" class="form-control" title="<?php echo _translate('Choose control type'); ?>">
                  <option value="">
                    <?php echo _translate("-- Select --"); ?>
                  </option>
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
              <td colspan="6">&nbsp;<input type="button" onclick="loadControlChart();" value="<?= _translate('Search'); ?>" class="btn btn-success btn-sm">
                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>
                    <?= _translate('Reset'); ?>
                  </span></button>
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
<script type="text/javascript" src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>

<script type="text/javascript">
  var startDate = "";
  var endDate = "";
  $(document).ready(function() {
    $('#sampleTestDate').daterangepicker({
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
    $('#sampleTestDate').val("");
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
      alert("<?php echo _translate("Please choose Sample Test Date Range and Control Type to generate the report", true); ?>");
    }
  }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
